<?php

	use Psr\Http\Message\ServerRequestInterface as Request;
	use Psr\Http\Message\ResponseInterface as Response;

function validateWebhookCsrf( $token ) {
	if ( session_status() !== PHP_SESSION_ACTIVE ) {
		return true;
	}

	if ( ! isset( $_SESSION["webhook_csrf"] ) ) {
		return false;
	}

	return hash_equals( $_SESSION["webhook_csrf"], $token );
}

$app->get( '/webhooks', function( Request $request, Response $response ) use ( $person ) {
	$params = $request->getQueryParams() ?: $request->getParsedBody();
	$page = isset( $params["page"] ) ? $params["page"] : null;
	$context = isset( $params["context"] ) ? $params["context"] : null;
	$deviceID = isset( $params["DeviceID"] ) ? intval( $params["DeviceID"] ) : null;

	if ( ! $person->ReadAccess ) {
		$r['error'] = true;
		$r['errorcode'] = 401;
		$r['message'] = __("Access Denied");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$device = null;
	if ( $deviceID !== null ) {
		$device = new Device();
		$device->DeviceID = $deviceID;
		if ( ! $device->GetDevice() ) {
			$r['error'] = true;
			$r['errorcode'] = 404;
			$r['message'] = __("Device not found");
			return $response->withJson( $r, $r['errorcode'] );
		}
		if ( $device->Rights == "None" ) {
			$r['error'] = true;
			$r['errorcode'] = 403;
			$r['message'] = __("Access Denied");
			return $response->withJson( $r, $r['errorcode'] );
		}
	}

	$list = Webhook::getWebhookList( $page, $context, true );
	$result = array();

	foreach ( $list as $webhook ) {
		if ( ! WebhookPolicy::canView( $person, $webhook ) ) {
			continue;
		}

		$canExecute = WebhookPolicy::canExecute( $person, $webhook );
		if ( $device !== null ) {
			$canExecute = $canExecute && ( $device->SerialNo > "" );
		}

		$result[] = array(
			"WebhookID" => $webhook->WebhookID,
			"Name" => $webhook->Name,
			"UIType" => $webhook->UIType,
			"CanExecute" => $canExecute ? true : false
		);
	}

	$r['error'] = false;
	$r['errorcode'] = 200;
	$r['webhook'] = $result;

	return $response->withJson( $r, $r['errorcode'] );
});

$app->post( '/webhooks/execute/{WebhookID}', function( Request $request, Response $response, $args ) use ( $person ) {
	$webhookID = intval( $args["WebhookID"] );

	if ( ! $person->ReadAccess ) {
		$r['error'] = true;
		$r['errorcode'] = 401;
		$r['message'] = __("Access Denied");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$webhook = Webhook::getWebhook( $webhookID );
	if ( ! $webhook ) {
		$r['error'] = true;
		$r['errorcode'] = 404;
		$r['message'] = __("Webhook not found");
		return $response->withJson( $r, $r['errorcode'] );
	}

	if ( ! WebhookPolicy::canExecute( $person, $webhook ) ) {
		$r['error'] = true;
		$r['errorcode'] = 403;
		$r['message'] = __("Access Denied");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$vars = $request->getQueryParams() ?: $request->getParsedBody();
	if ( ! isset( $vars["DeviceID"] ) ) {
		$r['error'] = true;
		$r['errorcode'] = 400;
		$r['message'] = __("DeviceID is required");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$token = $request->getHeaderLine( "X-CSRF-Token" );
	if ( $token == "" && isset( $vars["csrf"] ) ) {
		$token = $vars["csrf"];
	}

	if ( ! validateWebhookCsrf( $token ) ) {
		$r['error'] = true;
		$r['errorcode'] = 403;
		$r['message'] = __("Invalid CSRF token");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$device = new Device();
	$device->DeviceID = intval( $vars["DeviceID"] );
	if ( ! $device->GetDevice() ) {
		$r['error'] = true;
		$r['errorcode'] = 404;
		$r['message'] = __("Device not found");
		return $response->withJson( $r, $r['errorcode'] );
	}

	if ( $device->Rights == "None" ) {
		$r['error'] = true;
		$r['errorcode'] = 403;
		$r['message'] = __("Access Denied");
		return $response->withJson( $r, $r['errorcode'] );
	}

	if ( $device->SerialNo == "" ) {
		$r['error'] = true;
		$r['errorcode'] = 400;
		$r['message'] = __("Device SerialNo is required");
		return $response->withJson( $r, $r['errorcode'] );
	}

	$result = WebhookRuntime::execute( $webhook, $device, $person );

	if ( $result["success"] ) {
		$r['error'] = false;
		$r['errorcode'] = 200;
	} else {
		$r['error'] = true;
		$r['errorcode'] = 502;
	}

	$r['webhook'] = array(
		"WebhookID" => $webhook->WebhookID,
		"Name" => $webhook->Name
	);
	$r['device'] = array(
		"DeviceID" => $device->DeviceID,
		"SerialNo" => $device->SerialNo,
		"Label" => $device->Label
	);
	$r['result'] = array(
		"success" => $result["success"],
		"httpcode" => $result["http_code"],
		"duration" => $result["duration"],
		"error" => $result["error"]
	);

	return $response->withJson( $r, $r['errorcode'] );
});

?>
