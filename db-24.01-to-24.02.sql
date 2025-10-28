-- openDCIM Database Upgrade: 24.01 -> 24.02
-- Adds per-datacenter ACL table fac_PermissionsDC

START TRANSACTION;

CREATE TABLE IF NOT EXISTS fac_PermissionsDC (
  UserID varchar(50) NOT NULL,
  DataCenterID int(11) NOT NULL,
  Rights int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (UserID, DataCenterID),
  KEY DataCenterID (DataCenterID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

COMMIT;

