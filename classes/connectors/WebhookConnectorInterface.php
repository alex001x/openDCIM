<?php

interface WebhookConnectorInterface {
	public function send( $webhook, $url, $method, $payload, $headers, $options = array() );
}

?>
