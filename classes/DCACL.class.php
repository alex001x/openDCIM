<?php
/*
    openDCIM

    Datacenter ACL helper for per-DC permissions (bitmask).
    Rights bitmask:
    READ = 1
    WRITE = 2
    DELETE = 4

    Combos:
    0 = no access
    1 = read-only
    3 = read + write
    7 = all rights
*/

class DCACL {
    const RIGHT_READ = 1;
    const RIGHT_WRITE = 2;
    const RIGHT_DELETE = 4;

    // Returns int bitmask for a given user and datacenter, combining SiteAdmin/global rights fallback
    public static function getRights($userID, $dataCenterID) {
        global $dbh;
        global $person;

        $userID = sanitize($userID);
        $dataCenterID = intval($dataCenterID);

        // SiteAdmin => full rights
        if ($person && $person->UserID == $userID && $person->SiteAdmin) {
            return self::RIGHT_READ | self::RIGHT_WRITE | self::RIGHT_DELETE; // 7
        }

        // Explicit ACL entry takes precedence
        $sql = 'SELECT Rights FROM fac_PermissionsDC WHERE UserID=:uid AND DataCenterID=:dcid';
        $st = $dbh->prepare($sql);
        $st->execute(array(':uid' => $userID, ':dcid' => $dataCenterID));
        if ($row = $st->fetch()) {
            return intval($row['Rights']);
        }

        // Fallback to global rights (backward-compatible)
        $p = new People();
        $p->UserID = $userID;
        if ($p->GetPersonByUserID()) {
            $rights = 0;
            if ($p->ReadAccess)  { $rights |= self::RIGHT_READ; }
            if ($p->WriteAccess) { $rights |= self::RIGHT_WRITE; }
            if ($p->DeleteAccess){ $rights |= self::RIGHT_DELETE; }
            return $rights;
        }

        return 0;
    }

    // Returns true if user has the given right bit on this DC
    public static function hasRight($userID, $dataCenterID, $rightBit) {
        $rights = self::getRights($userID, $dataCenterID);
        return (($rights & intval($rightBit)) == intval($rightBit));
    }

    // Returns associative array DataCenterID => Rights for the user
    public static function getRightsByUser($userID) {
        global $dbh;
        $userID = sanitize($userID);

        $list = array();
        $sql = 'SELECT DataCenterID, Rights FROM fac_PermissionsDC WHERE UserID=:uid';
        $st = $dbh->prepare($sql);
        $st->execute(array(':uid' => $userID));
        while ($row = $st->fetch()) {
            $list[intval($row['DataCenterID'])] = intval($row['Rights']);
        }
        return $list;
    }

    // Returns list of DataCenterIDs where user has at least minRight (default READ)
    public static function getAllowedDCIDs($userID, $minRight = self::RIGHT_READ) {
        global $dbh;
        $userID = sanitize($userID);
        $minRight = intval($minRight);

        // If user is SiteAdmin, return all DC IDs
        $p = new People();
        $p->UserID = $userID;
        if ($p->GetPersonByUserID() && $p->SiteAdmin) {
            $ids = array();
            foreach ($dbh->query('SELECT DataCenterID FROM fac_DataCenter') as $row) {
                $ids[] = intval($row['DataCenterID']);
            }
            return $ids;
        }

        // Explicit rights
        $ids = array();
        $sql = 'SELECT DataCenterID, Rights FROM fac_PermissionsDC WHERE UserID=:uid';
        $st = $dbh->prepare($sql);
        $st->execute(array(':uid' => $userID));
        while ($row = $st->fetch()) {
            $rights = intval($row['Rights']);
            if (($rights & $minRight) == $minRight) {
                $ids[] = intval($row['DataCenterID']);
            }
        }

        // If no explicit ACLs found, fallback to global rights across all DCs
        if (empty($ids)) {
            $fallbackRights = 0;
            if ($p->ReadAccess)  { $fallbackRights |= self::RIGHT_READ; }
            if ($p->WriteAccess) { $fallbackRights |= self::RIGHT_WRITE; }
            if ($p->DeleteAccess){ $fallbackRights |= self::RIGHT_DELETE; }

            if (($fallbackRights & $minRight) == $minRight) {
                foreach ($dbh->query('SELECT DataCenterID FROM fac_DataCenter') as $row) {
                    $ids[] = intval($row['DataCenterID']);
                }
            }
        }

        return $ids;
    }

    // Replace rights for a user across DCs
    // $acls is an array of arrays: [ [ 'DataCenterID'=>int, 'Rights'=>int ], ... ]
    public static function setRightsForUser($userID, $acls) {
        global $dbh;
        $userID = sanitize($userID);

        $dbh->beginTransaction();
        try {
            // We will upsert entries, and delete those with 0 rights
            $up = $dbh->prepare('REPLACE INTO fac_PermissionsDC (UserID, DataCenterID, Rights) VALUES (:uid, :dcid, :rights)');
            $del = $dbh->prepare('DELETE FROM fac_PermissionsDC WHERE UserID=:uid AND DataCenterID=:dcid');

            foreach ($acls as $entry) {
                $dcid = isset($entry['DataCenterID']) ? intval($entry['DataCenterID']) : 0;
                $rights = isset($entry['Rights']) ? intval($entry['Rights']) : 0;

                if ($dcid <= 0) { continue; }

                if ($rights > 0) {
                    $up->execute(array(':uid' => $userID, ':dcid' => $dcid, ':rights' => $rights));
                } else {
                    $del->execute(array(':uid' => $userID, ':dcid' => $dcid));
                }
            }

            $dbh->commit();
            return true;
        } catch (Exception $ex) {
            $dbh->rollBack();
            error_log('DCACL::setRightsForUser failed: '.$ex->getMessage());
            return false;
        }
    }
}

?>

