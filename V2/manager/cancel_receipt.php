<?php
// manager/cancel_receipt.php
// Cancel transaction (sets status to 3) with audit log tracking for Bovorn Service V2
// Backwards-compatible with PHP 5.4+

require("con_db.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed: Cancellation requires POST method.");
}

validate_csrf();

$id_pay = isset($_POST['id_pay']) ? intval($_POST['id_pay']) : 0;
$cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
$return_url = isset($_POST['return_url']) ? trim($_POST['return_url']) : 'pay_list.php?cancel=success';

// Basic safety check for return_url
if (empty($return_url) || strpos($return_url, 'http://') === 0 || strpos($return_url, 'https://') === 0) {
    $return_url = 'pay_list.php?cancel=success';
}

if ($id_pay > 0) {
    if (empty($cancel_reason)) {
        $cancel_reason = 'ผู้จัดการขอยกเลิกใบเสร็จ (ไม่ระบุเหตุผล)';
    }

    try {
        // 1. Fetch current transaction record for audit snapshot
        $stmt_old = $pdo->prepare("SELECT * FROM `payment` WHERE `id_pay` = :id_pay");
        $stmt_old->execute(array(':id_pay' => $id_pay));
        $old_pay = $stmt_old->fetch();

        if (!$old_pay) {
            die("Error: Transaction not found.");
        }

        // 2. Mark status as cancelled (3)
        $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = 3 WHERE `id_pay` = :id_pay");
        $stmt->execute(array(':id_pay' => $id_pay));

        // 3. Log activity for audit and anti-fraud
        if (function_exists('log_payment_activity')) {
            $cus = isset($old_pay['cus_name']) ? $old_pay['cus_name'] : '';
            $reg = isset($old_pay['car_regis_id']) ? $old_pay['car_regis_id'] : '';
            $amt = isset($old_pay['pay_total']) ? number_format($old_pay['pay_total'], 2) : '0.00';
            $summary = "ยกเลิกบิล #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. (เหตุผล: " . $cancel_reason . ")";
            log_payment_activity($pdo, $id_pay, 'CANCEL', $cancel_reason, $old_pay, null, $summary);
        }
        
        $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
        header("Location: " . $return_url . $sep . "cancel=success");
        exit;
    } catch (\PDOException $e) {
        error_log("Cancel receipt error: " . $e->getMessage());
        die("Database Error: Could not cancel receipt. " . $e->getMessage());
    }
} else {
    header("Location: pay_list.php");
    exit;
}
?>
