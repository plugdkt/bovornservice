<?php
// request_cancel.php
// Employee submits a cancellation request (sets pay_status = 4) and sends report to Manager.
// Immediately excluded from daily sales reports & closing totals until Manager approves (3) or rejects (restores).
// Backwards-compatible with PHP 5.4+

require("db_connect.php");
check_login([2]); // Clerk / employee only

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed: Cancellation request requires POST method.");
}

validate_csrf();

$id_pay = isset($_POST['id_pay']) ? intval($_POST['id_pay']) : 0;
$cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
$return_url = isset($_POST['return_url']) ? trim($_POST['return_url']) : 'pay_list.php';

// Basic safety check for return_url (relative paths only)
if (empty($return_url) || strpos($return_url, 'http://') === 0 || strpos($return_url, 'https://') === 0 || strpos($return_url, '//') === 0) {
    $return_url = 'pay_list.php';
}

// Remove any existing cancel_req query params from return_url
$return_url = preg_replace('/([?&])cancel_req=[^&]*(&|$)/', '$1', $return_url);
$return_url = rtrim($return_url, '?&');

$id_branch = isset($_SESSION['id_branch']) ? intval($_SESSION['id_branch']) : 0;

if ($id_pay <= 0) {
    header("Location: pay_list.php");
    exit;
}

if (empty($cancel_reason)) {
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header("Location: " . $return_url . $sep . "cancel_req=missing_reason");
    exit;
}

try {
    // 1. Fetch transaction record — strictly scoped to employee's branch
    $stmt_old = $pdo->prepare("SELECT * FROM `payment` WHERE `id_pay` = :id_pay AND `id_branch` = :id_branch");
    $stmt_old->execute(array(':id_pay' => $id_pay, ':id_branch' => $id_branch));
    $old_pay = $stmt_old->fetch(PDO::FETCH_ASSOC);

    if (!$old_pay) {
        $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
        header("Location: " . $return_url . $sep . "cancel_req=not_found");
        exit;
    }

    $current_status = intval($old_pay['pay_status']);
    if ($current_status !== 1 && $current_status !== 2) {
        // Already cancelled (3) or already pending cancellation (4)
        $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
        header("Location: " . $return_url . $sep . "cancel_req=already");
        exit;
    }

    // 2. Set pay_status = 4 (Pending Manager Approval) — allowed for both Draft (1) and Printed (2)
    $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = 4 WHERE `id_pay` = :id_pay AND `id_branch` = :id_branch AND `pay_status` IN (1, 2)");
    $stmt->execute(array(':id_pay' => $id_pay, ':id_branch' => $id_branch));

    // 3. Log activity for Manager review & Audit Log (stores old_pay including previous pay_status)
    if (function_exists('log_payment_activity')) {
        $cus = isset($old_pay['cus_name']) ? $old_pay['cus_name'] : '';
        $reg = isset($old_pay['car_regis_id']) ? $old_pay['car_regis_id'] : '';
        $amt = isset($old_pay['pay_total']) ? number_format($old_pay['pay_total'], 2) : '0.00';
        $emp = isset($_SESSION['name']) ? $_SESSION['name'] : 'พนักงาน';
        $summary = "พนักงาน (" . $emp . ") แจ้งขอยกเลิกบิล #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. (หักออกจากยอดรายวันแล้ว รอผู้จัดการอนุมัติ)";
        log_payment_activity($pdo, $id_pay, 'CANCEL_REQUEST', $cancel_reason, $old_pay, array('pay_status' => 4), $summary);
    }

    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header("Location: " . $return_url . $sep . "cancel_req=success");
    exit;
} catch (\PDOException $e) {
    error_log("Request cancel error: " . $e->getMessage());
    $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
    header("Location: " . $return_url . $sep . "cancel_req=error");
    exit;
}
?>
