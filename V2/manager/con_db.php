<?php
// manager/con_db.php
// Database bridge and strict manager session validation for Bovorn Service V2

require_once(__DIR__ . "/../db_connect.php");

// Strict security enforcement: Only allow Managers (role = 1)
check_login([1]);
?>
