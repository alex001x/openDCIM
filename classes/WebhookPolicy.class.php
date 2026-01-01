<?php

class WebhookPolicy {
	public static function getUserRoles( $person ) {
		$roles = array();

		if ( $person->ReadAccess ) {
			$roles[] = "Read";
		}
		if ( $person->WriteAccess ) {
			$roles[] = "Write";
		}
		if ( $person->SiteAdmin ) {
			$roles[] = "SiteAdmin";
		}

		return array_unique( $roles );
	}

	public static function parseRoles( $roles ) {
		if ( is_array( $roles ) ) {
			return $roles;
		}

		$list = array();
		foreach ( explode( ",", (string)$roles ) as $role ) {
			$role = trim( $role );
			if ( $role > "" ) {
				$list[] = $role;
			}
		}

		return $list;
	}

	public static function canView( $person, $webhook ) {
		if ( $person->SiteAdmin ) {
			return true;
		}

		if ( isset( $webhook->Enabled ) && intval( $webhook->Enabled ) == 0 ) {
			return false;
		}

		return self::isRoleAllowed( $person, $webhook->ViewRoles );
	}

	public static function canExecute( $person, $webhook ) {
		if ( $person->SiteAdmin ) {
			return true;
		}

		if ( isset( $webhook->Enabled ) && intval( $webhook->Enabled ) == 0 ) {
			return false;
		}

		return self::isRoleAllowed( $person, $webhook->ExecuteRoles );
	}

	private static function isRoleAllowed( $person, $allowedRoles ) {
		$allowed = self::parseRoles( $allowedRoles );
		$userRoles = self::getUserRoles( $person );

		return count( array_intersect( $allowed, $userRoles ) ) > 0;
	}
}

?>
