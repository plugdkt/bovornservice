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

// ── Auto-setup system settings table ───────────────────────────────
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
?>