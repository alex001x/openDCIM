<?php

class WebhookSecret {
	private static function normalizeKey( $keyMaterial ) {
		return hash( "sha256", $keyMaterial, true );
	}

	private static function getKeyMaterial() {
		global $config;

		$envKey = getenv( "OPENDCIM_WEBHOOK_SECRET" );
		if ( $envKey > "" ) {
			return $envKey;
		}

		if ( defined( "WEBHOOK_SECRET_KEY" ) && WEBHOOK_SECRET_KEY > "" ) {
			return WEBHOOK_SECRET_KEY;
		}

		if ( isset( $config->ParameterArray["WebhookSecretKey"] ) && $config->ParameterArray["WebhookSecretKey"] > "" ) {
			return $config->ParameterArray["WebhookSecretKey"];
		}

		return false;
	}

	private static function ensureKeyMaterial() {
		global $config;

		$keyMaterial = self::getKeyMaterial();
		if ( $keyMaterial !== false ) {
			return $keyMaterial;
		}

		$keyMaterial = bin2hex( random_bytes( 32 ) );
		if ( class_exists( "Config" ) ) {
			Config::UpdateParameter( "WebhookSecretKey", $keyMaterial );
			$config->ParameterArray["WebhookSecretKey"] = $keyMaterial;
		}

		return $keyMaterial;
	}

	private static function encryptValue( $plaintext ) {
		$keyMaterial = self::ensureKeyMaterial();
		if ( $keyMaterial === false ) {
			return false;
		}

		$key = self::normalizeKey( $keyMaterial );
		$iv = random_bytes( 12 );
		$tag = "";
		$ciphertext = openssl_encrypt( $plaintext, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag );
		if ( $ciphertext === false ) {
			return false;
		}

		return "v1:" . base64_encode( $iv . $tag . $ciphertext );
	}

	private static function decryptValue( $payload ) {
		$keyMaterial = self::getKeyMaterial();
		if ( $keyMaterial === false ) {
			return false;
		}

		if ( strpos( $payload, "v1:" ) !== 0 ) {
			return false;
		}

		$data = base64_decode( substr( $payload, 3 ), true );
		if ( $data === false || strlen( $data ) < 28 ) {
			return false;
		}

		$iv = substr( $data, 0, 12 );
		$tag = substr( $data, 12, 16 );
		$ciphertext = substr( $data, 28 );

		$key = self::normalizeKey( $keyMaterial );
		return openssl_decrypt( $ciphertext, "aes-256-gcm", $key, OPENSSL_RAW_DATA, $iv, $tag );
	}

	public static function setSecret( $webhookID, $plaintext ) {
		global $dbh;

		$encrypted = self::encryptValue( $plaintext );
		if ( $encrypted === false ) {
			return false;
		}

		$st = $dbh->prepare( "insert into fac_WebhookSecrets set WebhookID=:WebhookID, EncryptedValue=:EncryptedValue on duplicate key update EncryptedValue=:EncryptedValue" );
		return $st->execute( array( ":WebhookID"=>$webhookID, ":EncryptedValue"=>$encrypted ) );
	}

	public static function getSecret( $webhookID ) {
		global $dbh;

		$st = $dbh->prepare( "select EncryptedValue from fac_WebhookSecrets where WebhookID=:WebhookID" );
		$st->execute( array( ":WebhookID"=>$webhookID ) );
		$payload = $st->fetchColumn();
		if ( $payload === false ) {
			return false;
		}

		return self::decryptValue( $payload );
	}

	public static function deleteSecret( $webhookID ) {
		global $dbh;

		$st = $dbh->prepare( "delete from fac_WebhookSecrets where WebhookID=:WebhookID" );
		return $st->execute( array( ":WebhookID"=>$webhookID ) );
	}
}

?>
