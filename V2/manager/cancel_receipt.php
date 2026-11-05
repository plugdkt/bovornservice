<?php
// manager/cancel_receipt.php
// Cancel transaction transaction (sets status to 3) for Bovorn Service V2

require("con_db.php");

$id_pay = isset($_GET['id_pay']) ? intval($_GET['id_pay']) : 0;

if ($id_pay > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE `payment` SET `pay_status` = 3 WHERE `id_pay` = :id_pay");
        $stmt->execute([':id_pay' => $id_pay]);
        
        header("Location: pay_list.php?cancel=success");
        exit;
    } catch (\PDOException $e) {
        die("Database Error: Could not cancel receipt. " . $e->getMessage());
    }
} else {
    header("Location: pay_list.php");
    exit;
}
?>
