<?php

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class HttpConnector implements WebhookConnectorInterface {
	public function send( $webhook, $url, $method, $payload, $headers, $options = array() ) {
		$timeout = isset( $options["timeout"] ) ? intval( $options["timeout"] ) : 10;
		$maxBytes = isset( $options["max_bytes"] ) ? intval( $options["max_bytes"] ) : 1048576;
		$allowlist = isset( $options["allowlist"] ) ? $options["allowlist"] : "";

		$client = new Client();
		$body = null;

		$requestOptions = array(
			RequestOptions::TIMEOUT => $timeout,
			RequestOptions::CONNECT_TIMEOUT => min( 5, $timeout ),
			RequestOptions::HTTP_ERRORS => false,
			RequestOptions::ALLOW_REDIRECTS => array(
				"max" => 2,
				"strict" => true,
				"on_redirect" => function( $request, $response, $uri ) use ( $allowlist ) {
					WebhookSSRFGuard::validateUrl( (string)$uri, $allowlist );
				}
			),
			RequestOptions::HEADERS => $headers,
			RequestOptions::STREAM => true
		);

		if ( $method != "GET" ) {
			$requestOptions[RequestOptions::BODY] = json_encode( $payload );
		}

		try {
			$response = $client->request( $method, $url, $requestOptions );

			$stream = $response->getBody();
			$buffer = "";

			while ( ! $stream->eof() && strlen( $buffer ) < $maxBytes ) {
				$buffer .= $stream->read( min( 8192, $maxBytes - strlen( $buffer ) ) );
			}

			if ( ! $stream->eof() ) {
				throw new Exception( "Response too large" );
			}

			$body = $buffer;

			return array(
				"success" => true,
				"http_code" => $response->getStatusCode(),
				"body" => $body,
				"error" => ""
			);
		} catch ( Exception $e ) {
			return array(
				"success" => false,
				"http_code" => 0,
				"body" => $body,
				"error" => $e->getMessage()
			);
		}
	}
}

?>
