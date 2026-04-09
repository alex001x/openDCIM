<?php
	require_once( 'db.inc.php' );
	require_once( 'facilities.inc.php' );
	require_once( 'classes/DCACL.class.php' );

	if ( !function_exists('resolveRightsFromCabinet') ) {
		function resolveRightsFromCabinet( $person, $cabinetID = null, $deviceID = null, $requireWrite = false, $requireDelete = false ) {
			$dcid = null;
			if ( $deviceID ) {
				$dev = new Device();
				$dev->DeviceID = intval($deviceID);
				if ( $dev->GetDevice() ) {
					if ( $dev->Cabinet > 0 ) {
						$cab = new Cabinet();
						$cab->CabinetID = $dev->Cabinet;
						if ( $cab->GetCabinet() ) {
							$dcid = $cab->DataCenterID;
						}
					} elseif ( $dev->Cabinet < 0 ) {
						$dcid = intval($dev->Position);
					}
				}
			} elseif ( $cabinetID ) {
				$cab = new Cabinet();
				$cab->CabinetID = intval($cabinetID);
				if ( $cab->GetCabinet() ) {
					$dcid = $cab->DataCenterID;
				}
			} elseif ( isset($GLOBALS['dcid']) ) {
				$tmp = intval($GLOBALS['dcid']);
				if ( $tmp > 0 ) {
					$dcid = $tmp;
				}
			}

			$siteadmin = ( $person->SiteAdmin ) ? true : false;
			$read = true;
			$write = true;
			$delete = true;
			if ( !$siteadmin && $dcid > 0 ) {
				if ( class_exists('DCACL') ) {
					$read = DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_READ);
					$write = DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_WRITE);
					$delete = DCACL::hasRight($person->UserID, $dcid, DCACL::RIGHT_DELETE);
				} else {
					$read = false;
					$write = false;
					$delete = false;
				}
			}

			$deny = false;
			if ( $requireDelete ) {
				$deny = !$delete;
			} elseif ( $requireWrite ) {
				$deny = !$write;
			} else {
				$deny = !$read;
			}

			return array(
				'dcid' => $dcid,
				'read' => $siteadmin ? true : $read,
				'write' => $siteadmin ? true : $write,
				'delete' => $siteadmin ? true : $delete,
				'siteadmin' => $siteadmin,
				'deny' => $deny
			);
		}
	}

	$subheader=__("Storage Room Maintenance");

	$dcid = (isset($_GET['dc'])) ? intval($_GET['dc']) : 0;
	$dcRights = resolveRightsFromCabinet($person, null, null);
	$allowedRead = false;
	if ( $person->SiteAdmin ) {
		$allowedRead = true;
	} elseif ( $dcid == 0 ) {
		// Global storageroom uses native rights only (no DCACL)
		$allowedRead = ( $person->AdminOwnDevices || $person->WriteAccess || $person->DeleteAccess ) ? true : false;
	} elseif ( $dcid > 0 ) {
		// Per-DC storageroom requires DCACL READ
		$allowedRead = $dcRights['read'];
	}
	if ( !$allowedRead ) {
		$errmsg = urlencode(__('Access Denied'));
		header('Location: '.redirect('index.php?msg='.$errmsg));
		exit;
	}

	$dev=new Device();

	if ( isset( $_POST["submit"]) && isset( $_POST["deviceid"]) ) {
		$allowedWrite = false;
		if ( $person->SiteAdmin ) {
			$allowedWrite = true;
		} elseif ( $dcid == 0 ) {
			// Global storageroom disposition uses native rights only (no DCACL)
			$allowedWrite = ( $person->DeleteAccess ) ? true : false;
		} elseif ( $dcid > 0 ) {
			// Per-DC storageroom disposition requires DCACL WRITE
			$allowedWrite = $dcRights['write'];
		}
		if ( !$allowedWrite ) {
			$errmsg = urlencode(__('Access Denied'));
			header('Location: '.redirect('index.php?msg='.$errmsg));
			exit;
		}
		$dispID = $_POST["dispositionid"];
		$dList = Disposition::getDisposition( $dispID );
		if ( count( $dList ) == 1 ) {
			$devList = $_POST["deviceid"];

			foreach( $devList as $d ) {
				$dev->DeviceID = $d;
				$dev->GetDevice();
				$dev->Dispose( $dispID );
			}
		}
	}

	$dList = Disposition::getDisposition();

	// Cabinet -1 is the Storage Area
	$dev->Cabinet=-1;
	
	if ( $dcid > 0 ){
		$dev->Position=$dcid;
		$srname=sprintf(__("Storage Room DC %d"), $dcid);
	}else{
		$dev->Position=0;
		$srname=__("General Storage Room");
	}
	$devList=$dev->ViewDevicesByCabinet(false,true);
?>
<!doctype html>
<html>
<head>
  <meta http-equiv="X-UA-Compatible" content="IE=Edge">
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  
  <title><?php echo __("Storage Room Maintenance");?></title>
  <link rel="stylesheet" href="css/inventory.php" type="text/css">
  <link rel="stylesheet" href="css/jquery-ui.css" type="text/css">
  <!--[if lt IE 9]>
  <link rel="stylesheet"  href="css/ie.css" type="text/css">
  <![endif]-->
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/jquery-ui.min.js"></script>
</head>
<body>
<?php include( 'header.inc.php' ); ?>
<div class="page storage">
<?php
	include( 'sidebar.inc.php' );
?>
<div class="main">
<?php echo '
<div class="center"><div>
<form method="POST">
<div class="table">
	<div class="title" id="title">',$srname,'</div>
	<div>
		<div>',__("Device"),'</div>
		<div>',__("Asset Tag"),'</div>
		<div>',__("Serial No."),'</div>
		<div></div>
	</div>';
	foreach($devList as $devID=>$device){
		// filter the list of devices in storage rooms to only show the devices for this room
		if($device->Position==$dcid){
			echo "<div><div><a href=\"devices.php?DeviceID=$device->DeviceID\">$device->Label</a></div><div>$device->AssetTag</div><div>$device->SerialNo</div><div><input type=\"checkbox\" name=\"deviceid[]\" value=\"$device->DeviceID\"></div></div>\n";
		}
	}

	print "<div><div>";
	print __("Dispose of selected devices to:");
	print "</div><div><select name=\"dispositionid\">";
	foreach( $dList as $disp ) {
		if ( $disp->Status == "Active" ) {
			print "<option value=$disp->DispositionID>$disp->Name</option>";
		}
	}
	print "</select></div><div><input type=\"submit\" name=\"submit\" value=\"Go\"></div>";
	print "</div></div>";
?>
</form>
</div> <!-- END div.table -->
</div></div>
</div><!-- END div.main -->
</div><!-- END div.page -->
</body>
</html>
