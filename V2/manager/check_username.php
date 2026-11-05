<?php
// manager/check_username.php
// AJAX endpoint to verify username uniqueness globally
require("con_db.php");

header('Content-Type: application/json; charset=utf-8');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
$exclude_id = isset($_GET['exclude_id']) ? intval($_GET['exclude_id']) : 0;

if (empty($username)) {
    echo json_encode(['exists' => false]);
    exit;
}

try {
    if ($exclude_id > 0) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `employee` WHERE `username` = :username AND `id_emp` != :exclude_id");
        $stmt->execute([':username' => $username, ':exclude_id' => $exclude_id]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `employee` WHERE `username` = :username");
        $stmt->execute([':username' => $username]);
    }
    
    $count = intval($stmt->fetchColumn());
    echo json_encode(['exists' => $count > 0], JSON_UNESCAPED_UNICODE);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
