<?php
// migrate.php
// Migration script to copy and upgrade data from bovornserv_db to bovornserv_v2_db

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Bovorn Service V2 - Database Migration Tool</h2>";

$host = "localhost";
$user = "root";
$pass = "";

try {
    $pdo_setup = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (\PDOException $e) {
    try {
        $config = require __DIR__ . '/config.local.php';
        $user = $config['user'];
        $pass = $config['pass'];
        $pdo_setup = new PDO("mysql:host=$host", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (\PDOException $e_alt) {
        die("<p style='color:red;'>Could not connect to MySQL server: " . $e_alt->getMessage() . "</p>");
    }
}

// 1. Create Database and Schema
echo "Creating new database (bovornserv_v2_db)...<br/>";
$pdo_setup->exec("DROP DATABASE IF EXISTS `bovornserv_v2_db`;");
$pdo_setup->exec("CREATE DATABASE `bovornserv_v2_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
$pdo_setup->exec("USE `bovornserv_v2_db`;");

echo "Running schema.sql...<br/>";
$schema_sql = file_get_contents(__DIR__ . '/schema.sql');
// Remove comments and execute statements
$queries = explode(';', $schema_sql);
foreach ($queries as $query) {
    $query = trim($query);
    if (!empty($query)) {
        $pdo_setup->exec($query);
    }
}
echo "<span style='color:green;'>Tables created successfully!</span><br/><br/>";

// 2. Setup PDO for V2 and V1
$pdo_v2 = new PDO("mysql:host=$host;dbname=bovornserv_v2_db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

try {
    $pdo_v1 = new PDO("mysql:host=$host;dbname=bovornserv_db;charset=utf8", $user, $pass);
} catch (\PDOException $e) {
    die("<p style='color:red;'>Could not connect to original bovornserv_db database. Ensure XAMPP is running and the database exists.</p>");
}

// 3. Migrate Metadata
echo "Migrating branches...<br/>";
$pdo_v2->exec("INSERT INTO `branch` (id_branch, branch_name) SELECT id_branch, branch_name FROM bovornserv_db.branch ON DUPLICATE KEY UPDATE branch_name=VALUES(branch_name);");

echo "Migrating employee types...<br/>";
$pdo_v2->exec("INSERT INTO `emp_type` (id_emp_type, emp_type_name) SELECT id_emp_type, emp_type_name FROM bovornserv_db.emp_type ON DUPLICATE KEY UPDATE emp_type_name=VALUES(emp_type_name);");

echo "Migrating vehicle types...<br/>";
$pdo_v2->exec("INSERT INTO `car_type` (id_car_type, car_type_name) SELECT id_car_type, car_type_name FROM bovornserv_db.car_type ON DUPLICATE KEY UPDATE car_type_name=VALUES(car_type_name);");

echo "Migrating provinces...<br/>";
$pdo_v2->exec("INSERT INTO `province` (PROVINCE_ID, PROVINCE_NAME) SELECT PROVINCE_ID, PROVINCE_NAME FROM bovornserv_db.province ON DUPLICATE KEY UPDATE PROVINCE_NAME=VALUES(PROVINCE_NAME);");

// 4. Migrate Employees with Password Hashing
echo "Migrating employees and hashing passwords...<br/>";
$stmt = $pdo_v1->query("SELECT * FROM `employee`");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$insert_emp = $pdo_v2->prepare("INSERT INTO `employee` (id_emp, name, addr, phone, username, password, id_emp_type, id_branch) 
    VALUES (:id, :name, :addr, :phone, :username, :password, :type, :branch)
    ON DUPLICATE KEY UPDATE name=VALUES(name), addr=VALUES(addr), phone=VALUES(phone), password=VALUES(password), id_emp_type=VALUES(id_emp_type), id_branch=VALUES(id_branch)");

$migrated_emp_count = 0;
foreach ($employees as $emp) {
    // Generate bcrypt hash if the password is not already hashed (we check length of bcrypt hash which is 60 chars)
    $password_raw = $emp['password'];
    $password_hash = (strlen($password_raw) === 60 && strpos($password_raw, '$2y$') === 0) 
        ? $password_raw 
        : password_hash($password_raw, PASSWORD_BCRYPT);
        
    $insert_emp->execute([
        ':id' => $emp['id_emp'],
        ':name' => $emp['name'],
        ':addr' => $emp['addr'],
        ':phone' => $emp['phone'],
        ':username' => $emp['username'],
        ':password' => $password_hash,
        ':type' => $emp['id_emp_type'],
        ':branch' => $emp['id_branch']
    ]);
    $migrated_emp_count++;
}
echo "<span style='color:green;'>Migrated $migrated_emp_count employees successfully!</span><br/><br/>";

// 5. Migrate Payments
echo "Migrating payments (this may take a few seconds)...<br/>";

// Branch 1: Dok Khamtai
echo "Migrating payments from Branch 1 (Dok Khamtai)...<br/>";
$pdo_v2->exec("INSERT IGNORE INTO `payment` (
    id_pay, id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
    serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
    pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
) SELECT 
    id_pay, id_emp, 1, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
    serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
    pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
FROM bovornserv_db.payment;");

// Branch 2: Tha Wang Thong (With offset of 100,000 to prevent PK collision)
echo "Migrating payments from Branch 2 (Tha Wang Thong) with ID offsets...<br/>";
$pdo_v2->exec("INSERT IGNORE INTO `payment` (
    id_pay, id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
    serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
    pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
) SELECT 
    id_pay + 100000, id_emp, 2, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
    serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
    pay_sum, discount, pay_total, pay_status, pay_day, pay_month, pay_year, pay_time
FROM bovornserv_db.payment2;");

echo "<span style='color:green;'>Payments migrated successfully!</span><br/><br/>";
echo "<h3>Migration Complete! Everything has been upgraded. You can now use Bovorn Service V2.</h3>";
echo "<p><a href='login.php'>Go to V2 Login</a></p>";
?>
