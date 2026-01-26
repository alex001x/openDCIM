<?php
/**
 * Fonctions de résolution des droits d'accès
 * Séparées pour améliorer la modularité
 */

function resolveRightsFromCabinetID( $person, $cabinetID ) {
	global $dbh;

	$result = array(
		'dcid' => 0,
		'canRead' => false,
		'canWrite' => false,
		'canDelete' => false,
		'deny' => true
	);

	if ( !is_object($person) || !isset($dbh) ) {
		return $result;
	}

	$cabinetID = intval($cabinetID);
	$dcid = 0;
	$resolved = false;

	if ( $cabinetID > 0 ) {
		$st = $dbh->prepare('SELECT DataCenterID FROM fac_Cabinet WHERE CabinetID=:cabid');
		if ( $st->execute(array(':cabid' => $cabinetID)) && ($row = $st->fetch()) ) {
			$dcid = intval($row['DataCenterID']);
			$resolved = true;
		}
	} elseif ( $cabinetID == -1 ) {
		$dcid = 0;
		$resolved = true;
	}

	if ( !$resolved ) {
		return $result;
	}

	if ( $person->SiteAdmin ) {
		$result['dcid'] = $dcid;
		$result['canRead'] = true;
		$result['canWrite'] = true;
		$result['canDelete'] = true;
		$result['deny'] = false;
		return $result;
	}

	$canRead = (bool)$person->ReadAccess;
	$canWrite = (bool)$person->WriteAccess;
	$canDelete = (bool)$person->DeleteAccess;

	if ( $dcid > 0 ) {
		if ( !class_exists('DCACL') ) {
			return $result;
		}
		$canRead = $canRead && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_READ);
		$canWrite = $canWrite && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_WRITE);
		$canDelete = $canDelete && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_DELETE);
	}

	$result['dcid'] = $dcid;
	$result['canRead'] = $canRead;
	$result['canWrite'] = $canWrite;
	$result['canDelete'] = $canDelete;
	$result['deny'] = !($canRead || $canWrite || $canDelete);

	return $result;
}

function resolveRightsFromDevice( $person, $deviceID ) {
	global $dbh;

	$result = array(
		'dcid' => 0,
		'canRead' => false,
		'canWrite' => false,
		'canDelete' => false,
		'deny' => true
	);

	if ( !is_object($person) || !isset($dbh) ) {
		return $result;
	}

	$deviceID = intval($deviceID);
	$dcid = 0;
	$resolved = false;

	if ( $deviceID > 0 ) {
		$st = $dbh->prepare('SELECT Cabinet, Position FROM fac_Device WHERE DeviceID=:devid');
		if ( $st->execute(array(':devid' => $deviceID)) && ($row = $st->fetch()) ) {
			$cabinetID = intval($row['Cabinet']);
			if ( $cabinetID > 0 ) {
				$stCab = $dbh->prepare('SELECT DataCenterID FROM fac_Cabinet WHERE CabinetID=:cabid');
				if ( $stCab->execute(array(':cabid' => $cabinetID)) && ($cabRow = $stCab->fetch()) ) {
					$dcid = intval($cabRow['DataCenterID']);
					$resolved = true;
				}
			} elseif ( $cabinetID == -1 ) {
				$dcid = intval($row['Position']);
				$resolved = true;
			}
		}
	}

	if ( !$resolved ) {
		return $result;
	}

	if ( $person->SiteAdmin ) {
		$result['dcid'] = $dcid;
		$result['canRead'] = true;
		$result['canWrite'] = true;
		$result['canDelete'] = true;
		$result['deny'] = false;
		return $result;
	}

	$canRead = (bool)$person->ReadAccess;
	$canWrite = (bool)$person->WriteAccess;
	$canDelete = (bool)$person->DeleteAccess;

	if ( $dcid > 0 ) {
		if ( !class_exists('DCACL') ) {
			return $result;
		}
		$canRead = $canRead && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_READ);
		$canWrite = $canWrite && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_WRITE);
		$canDelete = $canDelete && DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_DELETE);
	}

	$result['dcid'] = $dcid;
	$result['canRead'] = $canRead;
	$result['canWrite'] = $canWrite;
	$result['canDelete'] = $canDelete;
	$result['deny'] = !($canRead || $canWrite || $canDelete);

	return $result;
}