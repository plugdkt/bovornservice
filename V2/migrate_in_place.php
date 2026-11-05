<?php
// V2/migrate_in_place.php
// Upgrades the database in-place on production server

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Bovorn Service V2 - Database In-Place Upgrade Tool</h2>";

require("db_connect.php"); // Connects to the database (bovornserv_db) using PDO

try {
    // 1. Rename original payment tables to keep backup
    echo "Creating backups of original tables...<br/>";
    
    // Check if backup payment table already exists, if not, rename the original
    $stmt = $pdo->query("SHOW TABLES LIKE 'payment_v1'");
    if (!$stmt->fetch()) {
        $pdo->exec("RENAME TABLE `payment` TO `payment_v1`");
        echo "- Renamed payment to payment_v1<br/>";
    } else {
        echo "- Backup payment_v1 already exists<br/>";
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'payment2_v1'");
    if (!$stmt->fetch()) {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'payment2'");
        if ($stmt_check->fetch()) {
            $pdo->exec("RENAME TABLE `payment2` TO `payment2_v1`");
            echo "- Renamed payment2 to payment2_v1<br/>";
        }
    } else {
        echo "- Backup payment2_v1 already exists<br/>";
    }
    
    // 2. Create the V2 consolidated tables
    echo "Creating new V2 table structure...<br/>";
    
    // Create branch table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS `branch` (
      `id_branch` int(11) NOT NULL AUTO_INCREMENT,
      `branch_name` varchar(255) NOT NULL,
      PRIMARY KEY (`id_branch`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // Create new payment table (consolidated V2 table)
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payment` (
      `id_pay` int(11) NOT NULL AUTO_INCREMENT,
      `id_emp` int(11) NOT NULL,
      `id_branch` int(11) NOT NULL,
      `cus_name` varchar(255) NOT NULL,
      `cus_addr` text NOT NULL,
      `cus_phone` varchar(50) NOT NULL,
      `car_regis_id` varchar(50) NOT NULL,
      `id_car_type` int(11) NOT NULL,
      `province_id` int(11) NOT NULL,
      `serv_1` float NOT NULL,
      `serv_2` float NOT NULL,
      `serv_2_1` varchar(100) NOT NULL,
      `serv_3` float NOT NULL,
      `serv_4` float NOT NULL,
      `serv_4_1` int(11) NOT NULL,
      `serv_4_2` varchar(255) NOT NULL,
      `serv_5` float NOT NULL,
      `serv_6` float NOT NULL,
      `serv_7` float NOT NULL,
      `serv_7_1` varchar(255) NOT NULL,
      `pay_sum` float NOT NULL,
      `discount` float NOT NULL,
      `pay_total` float NOT NULL,
      `pay_status` tinyint(1) NOT NULL,
      `pay_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1 = Cash, 2 = Transfer',
      `pay_day` int(11) NOT NULL,
      `pay_month` int(11) NOT NULL,
      `pay_year` int(11) NOT NULL,
      `pay_time` time NOT NULL,
      PRIMARY KEY (`id_pay`),
      KEY `id_branch` (`id_branch`),
      KEY `id_emp` (`id_emp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    
    // 3. Populate Metadata
    $pdo->exec("INSERT IGNORE INTO `branch` (id_branch, branch_name) VALUES (1, 'สาขาดอกคำใต้'), (2, 'สาขาท่าวังทอง');");
    
    // 4. Migrate Branch 1 data
    echo "Migrating data from Branch 1 (payment_v1)...<br/>";
    $pdo->exec("INSERT IGNORE INTO `payment` (
        id_pay, id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
        serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
        pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
    ) SELECT 
        id_pay, id_emp, 1, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
        serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
        pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
    FROM `payment_v1`;");
    
    // 5. Migrate Branch 2 data if backup exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'payment2_v1'");
    if (!$stmt->fetch()) {
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'payment2_v1'");
        // Check legacy payment2 table
        $stmt_legacy = $pdo->query("SHOW TABLES LIKE 'payment2'");
        if ($stmt_legacy->fetch()) {
            echo "Migrating data from Branch 2 (payment2) with ID offset...<br/>";
            $pdo->exec("INSERT IGNORE INTO `payment` (
                id_pay, id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
                serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
                pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
            ) SELECT 
                id_pay + 100000, id_emp, 2, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
                serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
                pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
            FROM `payment2`;");
        }
    } else {
        echo "Migrating data from Branch 2 backup (payment2_v1)...<br/>";
        $pdo->exec("INSERT IGNORE INTO `payment` (
            id_pay, id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
            serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
            pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
        ) SELECT 
            id_pay + 100000, id_emp, 2, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
            serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
            pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
        FROM `payment2_v1`;");
    }
    
    // 6. Hash employee passwords
    echo "Hashing employee passwords to bcrypt (for V2 compatibility)...<br/>";
    
    // Alter employee password column size to prevent truncation of 60-char bcrypt hashes
    $pdo->exec("ALTER TABLE `employee` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;");
    
    $stmt_emp = $pdo->query("SELECT id_emp, password FROM `employee`");
    $employees = $stmt_emp->fetchAll();
    
    $update_emp = $pdo->prepare("UPDATE `employee` SET password = :password WHERE id_emp = :id");
    foreach ($employees as $emp) {
        $password_raw = $emp['password'];
        // Hash only if not already hashed (bcrypt hashes are 60 chars long)
        if (strlen($password_raw) !== 60 || strpos($password_raw, '$2y$') !== 0) {
            $password_hash = password_hash($password_raw, PASSWORD_BCRYPT);
            $update_emp->execute([
                ':password' => $password_hash,
                ':id' => $emp['id_emp']
            ]);
        }
    }
    
    echo "<h3 style='color:green;'>Upgrade Completed Successfully! You can now use Bovorn V2.</h3>";
    
} catch (\PDOException $e) {
    echo "<h3 style='color:red;'>Upgrade Failed: " . $e->getMessage() . "</h3>";
}
?>
