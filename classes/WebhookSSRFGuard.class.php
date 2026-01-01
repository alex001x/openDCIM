<?php

class WebhookSSRFGuard {
	private static function parseAllowlist( $allowlist ) {
		$hosts = array();

		foreach ( explode( ",", (string)$allowlist ) as $host ) {
			$host = trim( strtolower( $host ) );
			if ( $host > "" ) {
				$hosts[] = $host;
			}
		}

		return $hosts;
	}

	private static function hostAllowed( $host, $allowlist ) {
		$host = strtolower( $host );

		foreach ( $allowlist as $allowed ) {
			if ( $allowed == $host ) {
				return true;
			}
			if ( strpos( $allowed, "*." ) === 0 ) {
				$suffix = substr( $allowed, 1 );
				if ( substr( $host, -strlen( $suffix ) ) === $suffix ) {
					return true;
				}
			}
		}

		return false;
	}

	private static function isPublicIp( $ip ) {
		$flags = FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE;
		return filter_var( $ip, FILTER_VALIDATE_IP, $flags ) !== false;
	}

	private static function resolveIps( $host ) {
		$ips = array();

		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return array( $host );
		}

		$ipv4 = gethostbynamel( $host );
		if ( is_array( $ipv4 ) ) {
			$ips = array_merge( $ips, $ipv4 );
		}

		$records = dns_get_record( $host, DNS_AAAA );
		if ( is_array( $records ) ) {
			foreach ( $records as $record ) {
				if ( isset( $record["ipv6"] ) ) {
					$ips[] = $record["ipv6"];
				}
			}
		}

		return array_unique( $ips );
	}

	public static function validateUrl( $url, $allowlistHosts ) {
		$parts = parse_url( $url );

		if ( ! isset( $parts["scheme"] ) || ! isset( $parts["host"] ) ) {
			throw new Exception( "Invalid URL" );
		}

		$scheme = strtolower( $parts["scheme"] );
		if ( ! in_array( $scheme, array( "http", "https" ) ) ) {
			throw new Exception( "Invalid URL scheme" );
		}

		$host = strtolower( $parts["host"] );
		if ( $host == "localhost" || $host == "localhost.localdomain" ) {
			throw new Exception( "Host not allowed" );
		}

		$allowlist = self::parseAllowlist( $allowlistHosts );
		if ( count( $allowlist ) == 0 || ! self::hostAllowed( $host, $allowlist ) ) {
			throw new Exception( "Host not allowed" );
		}

		$ips = self::resolveIps( $host );
		if ( count( $ips ) == 0 ) {
			throw new Exception( "Unable to resolve host" );
		}

		foreach ( $ips as $ip ) {
			if ( ! self::isPublicIp( $ip ) ) {
				throw new Exception( "Non-public IP resolved" );
			}
		}

		return true;
	}
}

?>
