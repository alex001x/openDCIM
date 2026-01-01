<?php

class OcsConnector extends HttpConnector {
	private function buildOcsUrl( $url, $serialNo ) {
		if ( strpos( $url, "{{Device.SerialNo}}" ) !== false ) {
			return $url;
		}

		$parts = parse_url( $url );
		$query = array();

		if ( isset( $parts["query"] ) ) {
			parse_str( $parts["query"], $query );
		}

		if ( ! isset( $query["serial"] ) ) {
			$query["serial"] = $serialNo;
		}

		$base = $url;
		if ( isset( $parts["query"] ) ) {
			$base = str_replace( "?" . $parts["query"], "", $url );
		}

		return $base . "?" . http_build_query( $query );
	}

	public function send( $webhook, $url, $method, $payload, $headers, $options = array() ) {
		$serialNo = isset( $payload["Device"]["SerialNo"] ) ? $payload["Device"]["SerialNo"] : "";
		$url = $this->buildOcsUrl( $url, $serialNo );

		if ( $method == "" ) {
			$method = "GET";
		}

		return parent::send( $webhook, $url, $method, $payload, $headers, $options );
	}
}

?>
