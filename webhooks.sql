-- Webhooks and Integrations (manual migration)

CREATE TABLE IF NOT EXISTS fac_Webhooks (
  WebhookID int(11) NOT NULL AUTO_INCREMENT,
  Name varchar(80) NOT NULL,
  Context varchar(20) NOT NULL DEFAULT 'Device',
  Page varchar(80) NOT NULL DEFAULT 'devices.php',
  UIType varchar(10) NOT NULL DEFAULT 'button',
  Enabled tinyint(1) NOT NULL DEFAULT 1,
  ViewRoles varchar(80) NOT NULL DEFAULT 'Read,Write,SiteAdmin',
  ExecuteRoles varchar(80) NOT NULL DEFAULT 'Write,SiteAdmin',
  Endpoint varchar(255) NOT NULL,
  Method varchar(10) NOT NULL DEFAULT 'POST',
  AllowlistHosts text NOT NULL,
  Timeout int(11) NOT NULL DEFAULT 10,
  Connector varchar(30) NOT NULL DEFAULT 'Http',
  PRIMARY KEY (WebhookID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fac_WebhookSecrets (
  SecretID int(11) NOT NULL AUTO_INCREMENT,
  WebhookID int(11) NOT NULL,
  EncryptedValue text NOT NULL,
  CreatedAt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (SecretID),
  UNIQUE KEY WebhookID (WebhookID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS fac_WebhookExecutionLogs (
  ExecutionID int(11) NOT NULL AUTO_INCREMENT,
  WebhookID int(11) NOT NULL,
  UserID varchar(80) NOT NULL,
  DeviceID int(11) NOT NULL,
  Status varchar(20) NOT NULL,
  HTTPCode int(11) NOT NULL,
  Duration int(11) NOT NULL,
  ErrorMessage varchar(255) NOT NULL,
  CreatedAt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ExecutionID),
  KEY WebhookID (WebhookID),
  KEY DeviceID (DeviceID),
  KEY UserID (UserID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT IGNORE INTO fac_Config (Parameter, Value, UnitOfMeasure, ValType, DefaultVal)
VALUES ('WebhookSecretKey', '', 'Secret', 'string', '');
