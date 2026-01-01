<?php

class Webhook {
	var $WebhookID;
	var $Name;
	var $Context;
	var $Page;
	var $UIType;
	var $Enabled;
	var $ViewRoles;
	var $ExecuteRoles;
	var $Endpoint;
	var $Method;
	var $AllowlistHosts;
	var $Timeout;
	var $Connector;

	function MakeSafe() {
		$this->WebhookID = intval( $this->WebhookID );
		$this->Name = sanitize( $this->Name );
		$this->Context = sanitize( $this->Context );
		$this->Page = sanitize( $this->Page );
		$this->UIType = sanitize( $this->UIType );
		$this->Enabled = intval( $this->Enabled ) ? 1 : 0;
		$this->ViewRoles = sanitize( $this->ViewRoles );
		$this->ExecuteRoles = sanitize( $this->ExecuteRoles );
		$this->Endpoint = sanitize( $this->Endpoint );
		$this->Method = sanitize( strtoupper( $this->Method ) );
		$this->AllowlistHosts = sanitize( $this->AllowlistHosts );
		$this->Timeout = intval( $this->Timeout );
		$this->Connector = sanitize( $this->Connector );
	}

	function MakeDisplay() {
		$this->Name = stripslashes( $this->Name );
		$this->Context = stripslashes( $this->Context );
		$this->Page = stripslashes( $this->Page );
		$this->UIType = stripslashes( $this->UIType );
		$this->ViewRoles = stripslashes( $this->ViewRoles );
		$this->ExecuteRoles = stripslashes( $this->ExecuteRoles );
		$this->Endpoint = stripslashes( $this->Endpoint );
		$this->Method = stripslashes( $this->Method );
		$this->AllowlistHosts = stripslashes( $this->AllowlistHosts );
		$this->Connector = stripslashes( $this->Connector );
	}

	static function RowToObject( $row ) {
		$wh = new Webhook();
		foreach ( $row as $prop => $val ) {
			if ( ! is_numeric( $prop ) ) {
				$wh->$prop = $val;
			}
		}
		$wh->MakeDisplay();
		return $wh;
	}

	static function getWebhook( $webhookID ) {
		global $dbh;

		$st = $dbh->prepare( "select * from fac_Webhooks where WebhookID=:WebhookID" );
		$st->execute( array( ":WebhookID"=>$webhookID ) );
		if ( $row = $st->fetch() ) {
			return self::RowToObject( $row );
		}

		return false;
	}

	static function getWebhookList( $page = null, $context = null, $enabledOnly = false ) {
		global $dbh;

		$where = array();
		$args = array();

		if ( $page !== null ) {
			$where[] = "Page=:Page";
			$args[":Page"] = $page;
		}
		if ( $context !== null ) {
			$where[] = "Context=:Context";
			$args[":Context"] = $context;
		}
		if ( $enabledOnly ) {
			$where[] = "Enabled=1";
		}

		$sql = "select * from fac_Webhooks";
		if ( count( $where ) > 0 ) {
			$sql .= " where " . implode( " and ", $where );
		}
		$sql .= " order by Name asc";

		$st = $dbh->prepare( $sql );
		$st->execute( $args );

		$list = array();
		while ( $row = $st->fetch() ) {
			$list[] = self::RowToObject( $row );
		}

		return $list;
	}

	function createWebhook() {
		global $dbh;

		$this->MakeSafe();

		$sql = "insert into fac_Webhooks set Name=:Name, Context=:Context, Page=:Page, UIType=:UIType, Enabled=:Enabled,
			ViewRoles=:ViewRoles, ExecuteRoles=:ExecuteRoles, Endpoint=:Endpoint, Method=:Method, AllowlistHosts=:AllowlistHosts,
			Timeout=:Timeout, Connector=:Connector";

		$st = $dbh->prepare( $sql );
		$success = $st->execute( array(
			":Name"=>$this->Name,
			":Context"=>$this->Context,
			":Page"=>$this->Page,
			":UIType"=>$this->UIType,
			":Enabled"=>$this->Enabled,
			":ViewRoles"=>$this->ViewRoles,
			":ExecuteRoles"=>$this->ExecuteRoles,
			":Endpoint"=>$this->Endpoint,
			":Method"=>$this->Method,
			":AllowlistHosts"=>$this->AllowlistHosts,
			":Timeout"=>$this->Timeout,
			":Connector"=>$this->Connector
		) );

		if ( $success ) {
			$this->WebhookID = $dbh->lastInsertId();
			(class_exists( "LogActions" ))?LogActions::LogThis( $this ):'';
			return $this->WebhookID;
		}

		return false;
	}

	function updateWebhook() {
		global $dbh;

		$this->MakeSafe();

		$old = self::getWebhook( $this->WebhookID );

		$sql = "update fac_Webhooks set Name=:Name, Context=:Context, Page=:Page, UIType=:UIType, Enabled=:Enabled,
			ViewRoles=:ViewRoles, ExecuteRoles=:ExecuteRoles, Endpoint=:Endpoint, Method=:Method, AllowlistHosts=:AllowlistHosts,
			Timeout=:Timeout, Connector=:Connector where WebhookID=:WebhookID";

		$st = $dbh->prepare( $sql );
		if ( $st->execute( array(
			":Name"=>$this->Name,
			":Context"=>$this->Context,
			":Page"=>$this->Page,
			":UIType"=>$this->UIType,
			":Enabled"=>$this->Enabled,
			":ViewRoles"=>$this->ViewRoles,
			":ExecuteRoles"=>$this->ExecuteRoles,
			":Endpoint"=>$this->Endpoint,
			":Method"=>$this->Method,
			":AllowlistHosts"=>$this->AllowlistHosts,
			":Timeout"=>$this->Timeout,
			":Connector"=>$this->Connector,
			":WebhookID"=>$this->WebhookID
		) ) ) {
			(class_exists( "LogActions" ))?LogActions::LogThis( $this, $old ):'';
			return true;
		}

		return false;
	}

	function deleteWebhook() {
		global $dbh;

		$this->WebhookID = intval( $this->WebhookID );
		$old = self::getWebhook( $this->WebhookID );

		WebhookSecret::deleteSecret( $this->WebhookID );

		$st = $dbh->prepare( "delete from fac_Webhooks where WebhookID=:WebhookID" );
		if ( $st->execute( array( ":WebhookID"=>$this->WebhookID ) ) ) {
			(class_exists( "LogActions" ))?LogActions::LogThis( $old ):'';
			return true;
		}

		return false;
	}
}

?>
