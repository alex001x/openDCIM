<?php

class WebhookRuntime {
	public static function renderTemplate( $template, $context ) {
		$tokens = array(
			"{{Device.DeviceID}}" => $context["Device"]["DeviceID"],
			"{{Device.SerialNo}}" => $context["Device"]["SerialNo"],
			"{{Device.Label}}" => $context["Device"]["Label"],
			"{{Device.AssetTag}}" => $context["Device"]["AssetTag"],
			"{{Device.PrimaryIP}}" => $context["Device"]["PrimaryIP"]
		);

		return str_replace( array_keys( $tokens ), array_values( $tokens ), $template );
	}

	public static function buildContext( $device ) {
		return array(
			"Device" => array(
				"DeviceID" => $device->DeviceID,
				"SerialNo" => $device->SerialNo,
				"Label" => $device->Label,
				"AssetTag" => $device->AssetTag,
				"PrimaryIP" => $device->PrimaryIP
			)
		);
	}

	private static function getConnector( $webhook ) {
		$connector = strtolower( $webhook->Connector );

		if ( $connector == "ocs" ) {
			return new OcsConnector();
		}

		return new HttpConnector();
	}

	private static function normalizeMethod( $method ) {
		$method = strtoupper( $method );
		$allowed = array( "GET", "POST", "PUT", "PATCH", "DELETE" );

		if ( ! in_array( $method, $allowed ) ) {
			return "POST";
		}

		return $method;
	}

	private static function buildHeaders( $webhook ) {
		$headers = array(
			"Content-Type" => "application/json",
			"User-Agent" => "openDCIM Webhook Runtime"
		);

		$secret = WebhookSecret::getSecret( $webhook->WebhookID );
		if ( $secret !== false && $secret > "" ) {
			$headers["Authorization"] = "Bearer " . $secret;
		}

		return $headers;
	}

	private static function logExecution( $webhookID, $userID, $deviceID, $status, $httpCode, $duration, $errorMessage ) {
		global $dbh;

		$st = $dbh->prepare( "insert into fac_WebhookExecutionLogs set WebhookID=:WebhookID, UserID=:UserID, DeviceID=:DeviceID,
			Status=:Status, HTTPCode=:HTTPCode, Duration=:Duration, ErrorMessage=:ErrorMessage" );
		$st->execute( array(
			":WebhookID"=>$webhookID,
			":UserID"=>$userID,
			":DeviceID"=>$deviceID,
			":Status"=>$status,
			":HTTPCode"=>$httpCode,
			":Duration"=>$duration,
			":ErrorMessage"=>$errorMessage
		) );
	}

	public static function execute( $webhook, $device, $person ) {
		$context = self::buildContext( $device );
		$url = self::renderTemplate( $webhook->Endpoint, $context );
		$method = self::normalizeMethod( $webhook->Method );
		$headers = self::buildHeaders( $webhook );
		$payload = $context;
		$connector = self::getConnector( $webhook );

		$start = microtime( true );
		try {
			WebhookSSRFGuard::validateUrl( $url, $webhook->AllowlistHosts );

			$result = $connector->send( $webhook, $url, $method, $payload, $headers, array(
				"timeout" => $webhook->Timeout,
				"allowlist" => $webhook->AllowlistHosts,
				"max_bytes" => 1048576
			) );
		} catch ( Exception $e ) {
			$result = array(
				"success" => false,
				"http_code" => 0,
				"body" => "",
				"error" => $e->getMessage()
			);
		}

		$duration = intval( ( microtime( true ) - $start ) * 1000 );
		$status = $result["success"] ? "success" : "failed";
		$httpCode = intval( $result["http_code"] );
		$errorMessage = $result["error"];

		self::logExecution( $webhook->WebhookID, $person->UserID, $device->DeviceID, $status, $httpCode, $duration, $errorMessage );

		return array(
			"success" => $result["success"],
			"http_code" => $httpCode,
			"duration" => $duration,
			"error" => $errorMessage,
			"body" => $result["body"]
		);
	}
}

?>
