<?php
// db_connect.php
// Database connection using PDO for Bovorn Service V2
// Fully backwards-compatible with PHP 5.4+ through PHP 8.x

// Security Headers
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    @ini_set('session.cookie_samesite', 'Lax'); // PHP 7.3+, safely ignored on older PHP
    ini_set('session.use_strict_mode', '1');
}

// ── Polyfills for PHP 5.4 / 5.5 / 5.6 Compatibility ────────────────

// Polyfill for password_hash and password_verify on PHP < 5.5
if (!function_exists('password_hash')) {
    define('PASSWORD_BCRYPT', 1);
    define('PASSWORD_DEFAULT', 1);

    function password_hash($password, $algo, array $options = array()) {
        if ($algo !== PASSWORD_BCRYPT) {
            trigger_error("password_hash(): Unsupported hashing algorithm", E_USER_WARNING);
            return null;
        }
        $cost = isset($options['cost']) ? intval($options['cost']) : 10;
        if ($cost < 4 || $cost > 31) {
            trigger_error("password_hash(): Invalid cost parameter specified", E_USER_WARNING);
            return null;
        }
        
        $salt = "";
        $salt_chars = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'), array('.', '/'));
        for ($i = 0; $i < 22; $i++) {
            $salt .= $salt_chars[mt_rand(0, 63)];
        }
        
        $prefix = sprintf("$2y$%02d$", $cost);
        $hash = crypt($password, $prefix . $salt);
        if (strlen($hash) < 60) {
            return false;
        }
        return $hash;
    }
}

if (!function_exists('password_verify')) {
    function password_verify($password, $hash) {
        if (empty($password) || empty($hash)) {
            return false;
        }
        $new_hash = crypt($password, $hash);
        $len = strlen($hash);
        if ($len !== strlen($new_hash) || $len < 60) {
            return false;
        }
        $status = 0;
        for ($i = 0; $i < $len; $i++) {
            $status |= (ord($hash[$i]) ^ ord($new_hash[$i]));
        }
        return $status === 0;
    }
}

// Polyfill for array_column on PHP < 5.5
if (!function_exists('array_column')) {
    function array_column(array $input, $columnKey, $indexKey = null) {
        $array = array();
        foreach ($input as $value) {
            if (!isset($value[$columnKey])) {
                continue;
            }
            if (is_null($indexKey)) {
                $array[] = $value[$columnKey];
            } else {
                if (!isset($value[$indexKey]) || !is_scalar($value[$indexKey])) {
                    continue;
                }
                $array[$value[$indexKey]] = $value[$columnKey];
            }
        }
        return $array;
    }
}

// Polyfill for random_bytes on PHP < 7.0
if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        if (function_exists('openssl_random_pseudo_bytes')) {
            $crypto_strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
            if ($bytes !== false && $crypto_strong) {
                return $bytes;
            }
        }
        if (function_exists('mcrypt_create_iv')) {
            $bytes = mcrypt_create_iv($length, MCRYPT_DEV_URANDOM);
            if ($bytes !== false) {
                return $bytes;
            }
        }
        $bytes = '';
        for ($i = 0; $i < $length; $i++) {
            $bytes .= chr(mt_rand(0, 255));
        }
        return $bytes;
    }
}

// Polyfill for hash_equals on PHP < 5.6
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (!is_string($known_string) || !is_string($user_string)) {
            return false;
        }
        $len = strlen($known_string);
        if ($len !== strlen($user_string)) {
            return false;
        }
        $status = 0;
        for ($i = 0; $i < $len; $i++) {
            $status |= (ord($known_string[$i]) ^ ord($user_string[$i]));
        }
        return $status === 0;
    }
}

if (!defined('LEGACY_UNKNOWN_LABEL')) {
    define('LEGACY_UNKNOWN_LABEL', 'ไม่ระบุ (ข้อมูลเก่า)');
}

// Database credentials live in config.local.php (git-ignored); see config.example.php
$config_file = __DIR__ . '/config.local.php';
if (!is_file($config_file)) {
    die("Missing config.local.php — copy config.example.php to config.local.php and set the database credentials");
}
$config = require $config_file;
$host = $config['host'];
$db = $config['name'];
$user = $config['user'];
$pass = $config['pass'];
$charset = "utf8mb4";

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = array(
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
);

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     die("Database Connection Failed: " . $e->getMessage());
}

// Function to assist with checking authentication session
function check_login($allowed_roles = array()) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['id_emp'])) {
        header("Location: " . dirname($_SERVER['PHP_SELF']) . "/../login.php");
        exit;
    }
    
    if (!empty($allowed_roles)) {
        if (!in_array($_SESSION['id_emp_type'], $allowed_roles)) {
            http_response_code(403);
            die("Access Denied: You do not have permission to view this page.");
        }
    }
}

// ── CSRF Protection Helpers ────────────────────────────────────────
function csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

function validate_csrf() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_SERVER['HTTP_X_CSRF_TOKEN']) ? $_SERVER['HTTP_X_CSRF_TOKEN'] : '');
    if (empty($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die("Invalid or missing CSRF token. Request blocked for security.");
    }
}

// ── Auto-setup system settings & daily expenses table ────────────────
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS `system_settings` (
      `setting_key` varchar(50) NOT NULL,
      `setting_value` varchar(255) NOT NULL,
      `setting_desc` varchar(150) DEFAULT NULL,
      PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    $pdo->query("INSERT IGNORE INTO `system_settings` (`setting_key`, `setting_value`, `setting_desc`) VALUES
    ('tax_car_fee', '10', 'ค่าส่งต่อภาษี รถยนต์ (บาท/คัน)'),
    ('tax_motorcycle_fee', '5', 'ค่าส่งต่อภาษี รถจักรยานยนต์ (บาท/คัน)');");

    $pdo->query("CREATE TABLE IF NOT EXISTS `daily_expense` (
      `id_expense` int(11) NOT NULL AUTO_INCREMENT,
      `id_branch` tinyint(1) NOT NULL,
      `expense_date` date NOT NULL,
      `expense_desc` varchar(255) NOT NULL,
      `expense_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
      `id_emp` mediumint(5) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id_expense`),
      KEY `idx_branch_date` (`id_branch`, `expense_date`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Sales audit log table for anti-fraud tracking
    $pdo->query("CREATE TABLE IF NOT EXISTS `payment_log` (
      `id_log` int(11) NOT NULL AUTO_INCREMENT,
      `id_pay` int(10) NOT NULL,
      `id_emp` mediumint(5) NOT NULL,
      `emp_name` varchar(100) NOT NULL,
      `id_branch` tinyint(1) NOT NULL,
      `action` varchar(30) NOT NULL,
      `action_label` varchar(100) NOT NULL,
      `reason` varchar(255) DEFAULT '',
      `changes_summary` text DEFAULT NULL,
      `old_data` mediumtext DEFAULT NULL,
      `new_data` mediumtext DEFAULT NULL,
      `ip_address` varchar(45) DEFAULT NULL,
      `user_agent` varchar(255) DEFAULT NULL,
      `created_at` datetime NOT NULL,
      PRIMARY KEY (`id_log`),
      KEY `idx_pay_log_id_pay` (`id_pay`),
      KEY `idx_pay_log_action` (`action`),
      KEY `idx_pay_log_branch` (`id_branch`),
      KEY `idx_pay_log_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

} catch (\PDOException $e) {
    // Fail silently to prevent interrupting user actions if DB permissions restrict DDL
}

// Helper function to fetch a system setting
function get_system_setting($key, $default = '') {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT `setting_value` FROM `system_settings` WHERE `setting_key` = :key");
        $stmt->execute(array(':key' => $key));
        $val = $stmt->fetchColumn();
        return $val !== false ? $val : $default;
    } catch (\PDOException $e) {
        return $default;
    }
}


/**
 * Log payment activity for anti-fraud and audit tracking
 * 
 * @param PDO $pdo
 * @param int $id_pay
 * @param string $action ('CREATE', 'EDIT', 'PRINT', 'REPRINT', 'CANCEL')
 * @param string $reason
 * @param array|null $old_data
 * @param array|null $new_data
 * @param string $custom_summary
 * @return bool
 */
function log_payment_activity($pdo, $id_pay, $action, $reason = '', $old_data = null, $new_data = null, $custom_summary = '') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $id_emp = isset($_SESSION['id_emp']) ? intval($_SESSION['id_emp']) : 0;
    $emp_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'พนักงาน/ระบบ';
    $id_branch = isset($_SESSION['id_branch']) ? intval($_SESSION['id_branch']) : 1;
    
    if ($id_branch === 0 && !empty($old_data['id_branch'])) {
        $id_branch = intval($old_data['id_branch']);
    } elseif ($id_branch === 0 && !empty($new_data['id_branch'])) {
        $id_branch = intval($new_data['id_branch']);
    }
    
    $action_labels = array(
        'CREATE'  => 'สร้างบิลใหม่',
        'EDIT'    => 'แก้ไขข้อมูลบิล',
        'PRINT'   => 'พิมพ์ใบเสร็จ (ครั้งแรก)',
        'REPRINT' => 'พิมพ์ใบเสร็จซ้ำ',
        'CANCEL'  => 'ยกเลิกใบเสร็จ'
    );
    $action_label = isset($action_labels[$action]) ? $action_labels[$action] : $action;

    $changes_summary = $custom_summary;
    if ($action === 'EDIT' && is_array($old_data) && is_array($new_data) && empty($changes_summary)) {
        $diffs = array();
        $fields_map = array(
            'cus_name'     => 'ชื่อลูกค้า',
            'cus_phone'    => 'เบอร์โทร',
            'cus_addr'     => 'ที่อยู่',
            'car_regis_id' => 'ทะเบียนรถ',
            'id_car_type'  => 'ประเภทรถ',
            'province_id'  => 'จังหวัด',
            'serv_1'       => 'พ.ร.บ.',
            'serv_2'       => 'ตรวจสภาพ (ตรอ.)',
            'serv_2_1'     => 'เลขที่ ตรอ.',
            'serv_3'       => 'ฝากต่อภาษี',
            'serv_4'       => 'บริการ 4',
            'serv_4_1'     => 'ประเภทบริการ 4',
            'serv_4_2'     => 'รายละเอียดบริการ 4',
            'serv_5'       => 'บริการ 5',
            'serv_6'       => 'ประกันภัย',
            'serv_7'       => 'บริการ 7',
            'serv_7_1'     => 'รายละเอียดบริการ 7',
            'discount'     => 'ส่วนลด',
            'pay_sum'      => 'ยอดรวมก่อนลด',
            'pay_total'    => 'ยอดชำระสุทธิ',
            'pay_type'     => 'วิธีชำระเงิน',
            'pay_cash'     => 'เงินสด',
            'pay_transfer' => 'เงินโอน'
        );

        $num_fields = array('serv_1', 'serv_2', 'serv_3', 'serv_4', 'serv_5', 'serv_6', 'serv_7', 'discount', 'pay_sum', 'pay_total', 'pay_cash', 'pay_transfer');
        foreach ($fields_map as $f => $lbl) {
            $ov = isset($old_data[$f]) ? $old_data[$f] : null;
            $nv = isset($new_data[$f]) ? $new_data[$f] : null;
            if (in_array($f, $num_fields)) {
                $of = floatval($ov);
                $nf = floatval($nv);
                if (abs($of - $nf) > 0.001) {
                    $diffs[] = $lbl . ": " . number_format($of, 2) . " ➔ " . number_format($nf, 2) . " บ.";
                }
            } elseif ($f === 'pay_type') {
                $pts = array(1 => 'เงินสด', 2 => 'เงินโอน', 3 => 'ผสม (สด+โอน)');
                if ($ov != $nv) {
                    $diffs[] = $lbl . ": " . (isset($pts[$ov]) ? $pts[$ov] : $ov) . " ➔ " . (isset($pts[$nv]) ? $pts[$nv] : $nv);
                }
            } else {
                if (trim((string)$ov) !== trim((string)$nv)) {
                    $diffs[] = $lbl . ": '" . (string)$ov . "' ➔ '" . (string)$nv . "'";
                }
            }
        }
        $changes_summary = !empty($diffs) ? implode(" | ", $diffs) : 'ไม่มีการแก้ไขข้อมูลในฟิลด์สำคัญ';
    }

    $ip = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $ip = substr($ip, 0, 45);
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';
    $now = date('Y-m-d H:i:s');

    $old_json = is_array($old_data) ? json_encode($old_data, 256) : null;
    $new_json = is_array($new_data) ? json_encode($new_data, 256) : null;

    try {
        $stmt = $pdo->prepare("INSERT INTO `payment_log` (
            `id_pay`, `id_emp`, `emp_name`, `id_branch`, `action`, `action_label`,
            `reason`, `changes_summary`, `old_data`, `new_data`, `ip_address`, `user_agent`, `created_at`
        ) VALUES (
            :id_pay, :id_emp, :emp_name, :id_branch, :action, :action_label,
            :reason, :changes_summary, :old_data, :new_data, :ip_address, :user_agent, :created_at
        )");
        return $stmt->execute(array(
            ':id_pay'          => $id_pay,
            ':id_emp'          => $id_emp,
            ':emp_name'        => $emp_name,
            ':id_branch'       => $id_branch,
            ':action'          => $action,
            ':action_label'    => $action_label,
            ':reason'          => $reason,
            ':changes_summary' => $changes_summary,
            ':old_data'        => $old_json,
            ':new_data'        => $new_json,
            ':ip_address'      => $ip,
            ':user_agent'      => $ua,
            ':created_at'      => $now
        ));
    } catch (\PDOException $e) {
        error_log("Failed to insert payment_log: " . $e->getMessage());
        return false;
    }
}
