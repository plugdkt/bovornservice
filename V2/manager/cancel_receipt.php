<?php
// manager/cancel_receipt.php
// Cancel transaction (sets status to 3), or Approve/Reject Clerk Cancel Request (status 4)
// with full audit log tracking for Bovorn Service V2. Backwards-compatible with PHP 5.4+

require("con_db.php");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die("Method Not Allowed: Cancellation requires POST method.");
}

validate_csrf();

$id_pay = isset($_POST['id_pay']) ? intval($_POST['id_pay']) : 0;
$action = isset($_POST['action']) ? trim($_POST['action']) : 'direct_cancel';
$cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
$return_url = isset($_POST['return_url']) ? trim($_POST['return_url']) : 'pay_list.php';

// Basic safety check for return_url (relative paths only)
if (empty($return_url) || strpos($return_url, 'http://') === 0 || strpos($return_url, 'https://') === 0 || strpos($return_url, '//') === 0) {
    $return_url = 'pay_list.php';
}
// Clean old cancel query param
$return_url = preg_replace('/([?&])cancel=[^&]*(&|$)/', '$1', $return_url);
$return_url = rtrim($return_url, '?&');

if ($id_pay > 0) {
    try {
        // 1. Fetch current transaction record for audit snapshot
        $stmt_old = $pdo->prepare("SELECT * FROM `payment` WHERE `id_pay` = :id_pay");
        $stmt_old->execute(array(':id_pay' => $id_pay));
        $old_pay = $stmt_old->fetch(PDO::FETCH_ASSOC);

        if (!$old_pay) {
            die("Error: Transaction not found.");
        }

        $cus = isset($old_pay['cus_name']) ? $old_pay['cus_name'] : '';
        $reg = isset($old_pay['car_regis_id']) ? $old_pay['car_regis_id'] : '';
        $amt = isset($old_pay['pay_total']) ? number_format($old_pay['pay_total'], 2) : '0.00';

        if ($action === 'reject_cancel') {
            // Restore previous pay_status from CANCEL_REQUEST audit log (default 2 = Completed)
            $restore_status = 2;
            try {
                $stmt_log = $pdo->prepare("SELECT `old_data` FROM `payment_log` WHERE `id_pay` = :id_pay AND `action` = 'CANCEL_REQUEST' ORDER BY `id_log` DESC LIMIT 1");
                $stmt_log->execute(array(':id_pay' => $id_pay));
                $log_row = $stmt_log->fetch(PDO::FETCH_ASSOC);
                if ($log_row && !empty($log_row['old_data'])) {
                    $decoded = json_decode($log_row['old_data'], true);
                    if (is_array($decoded) && isset($decoded['pay_status']) && in_array(intval($decoded['pay_status']), array(1, 2))) {
                        $restore_status = intval($decoded['pay_status']);
                    }
                }
            } catch (\PDOException $e) {
                // fallback to 2
            }

            if (empty($cancel_reason)) {
                $cancel_reason = 'ผู้จัดการปฏิเสธคำขอยกเลิกและคืนสถานะบิลเป็นปกติ';
            }

            $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = :st WHERE `id_pay` = :id_pay");
            $stmt->execute(array(':st' => $restore_status, ':id_pay' => $id_pay));

            if (function_exists('log_payment_activity')) {
                $summary = "ผู้จัดการปฏิเสธคำขอยกเลิกบิล #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. คืนสถานะบิลเป็นปกติ (เหตุผล: " . $cancel_reason . ")";
                log_payment_activity($pdo, $id_pay, 'CANCEL_REJECT', $cancel_reason, $old_pay, array('pay_status' => $restore_status), $summary);
            }

            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "cancel=rejected");
            exit;
        } elseif ($action === 'restore_cancel') {
            // Restore a cancelled bill (pay_status = 3) back to its original status (1 or 2)
            $restore_status = 2;
            try {
                $stmt_log = $pdo->prepare("SELECT `old_data` FROM `payment_log` WHERE `id_pay` = :id_pay AND `action` IN ('CANCEL_REQUEST', 'CANCEL') ORDER BY `id_log` DESC");
                $stmt_log->execute(array(':id_pay' => $id_pay));
                $log_rows = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
                foreach ($log_rows as $lr) {
                    if (!empty($lr['old_data'])) {
                        $decoded = json_decode($lr['old_data'], true);
                        if (is_array($decoded) && isset($decoded['pay_status']) && in_array(intval($decoded['pay_status']), array(1, 2))) {
                            $restore_status = intval($decoded['pay_status']);
                            break;
                        }
                    }
                }
            } catch (\PDOException $e) {
                // fallback to 2
            }

            if (empty($cancel_reason)) {
                $cancel_reason = 'ผู้จัดการกู้คืนใบเสร็จที่ยกเลิกกลับเป็นสถานะปกติ';
            }

            $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = :st WHERE `id_pay` = :id_pay AND `pay_status` = 3");
            $stmt->execute(array(':st' => $restore_status, ':id_pay' => $id_pay));

            if (function_exists('log_payment_activity')) {
                $status_lbl = ($restore_status === 1) ? 'รอพิมพ์' : 'สำเร็จ/พิมพ์แล้ว';
                $summary = "ผู้จัดการกู้คืนบิลที่ยกเลิก #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. กลับเป็นสถานะ [" . $status_lbl . "]";
                log_payment_activity($pdo, $id_pay, 'RESTORE', $cancel_reason, $old_pay, array('pay_status' => $restore_status), $summary);
            }

            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "cancel=restored");
            exit;
        } elseif ($action === 'approve_cancel') {
            if (empty($cancel_reason)) {
                $cancel_reason = 'ผู้จัดการอนุมัติคำขอยกเลิกจากพนักงาน';
            }

            $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = 3 WHERE `id_pay` = :id_pay");
            $stmt->execute(array(':id_pay' => $id_pay));

            if (function_exists('log_payment_activity')) {
                $summary = "ผู้จัดการอนุมัติคำขอยกเลิกบิล #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. (หมายเหตุ: " . $cancel_reason . ")";
                log_payment_activity($pdo, $id_pay, 'CANCEL', $cancel_reason, $old_pay, array('pay_status' => 3), $summary);
            }

            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "cancel=approved");
            exit;
        } else {
            // Direct cancel by Manager
            if (empty($cancel_reason)) {
                $cancel_reason = 'ผู้จัดการขอยกเลิกใบเสร็จ (ไม่ระบุเหตุผล)';
            }

            $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = 3 WHERE `id_pay` = :id_pay");
            $stmt->execute(array(':id_pay' => $id_pay));

            if (function_exists('log_payment_activity')) {
                $summary = "ยกเลิกบิล #" . $id_pay . ": " . $cus . " (" . $reg . ") ยอด " . $amt . " บ. (เหตุผล: " . $cancel_reason . ")";
                log_payment_activity($pdo, $id_pay, 'CANCEL', $cancel_reason, $old_pay, array('pay_status' => 3), $summary);
            }

            $sep = (strpos($return_url, '?') !== false) ? '&' : '?';
            header("Location: " . $return_url . $sep . "cancel=success");
            exit;
        }
    } catch (\PDOException $e) {
        error_log("Cancel receipt error: " . $e->getMessage());
        die("Database Error: Could not process cancellation. " . $e->getMessage());
    }
} else {
    header("Location: pay_list.php");
    exit;
}
?>
