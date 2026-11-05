<?php
// manager/del_emp.php
// Delete employee account handler (Manager view) for Bovorn Service V2

require("con_db.php");

$id_emp = isset($_GET['id_emp']) ? intval($_GET['id_emp']) : 0;
$current_manager_id = $_SESSION['id_emp'];

if ($id_emp > 0) {
    // Safety locks: Do not delete ID 2 (Core Admin) or current logged in user
    if ($id_emp == 2) {
        header("Location: user_manager.php?error=" . urlencode("ไม่สามารถลบบัญชีผู้จัดการหลัก (ID: 2) ได้"));
        exit;
    }
    
    if ($id_emp == $current_manager_id) {
        header("Location: user_manager.php?error=" . urlencode("ไม่สามารถลบบัญชีผู้ใช้ที่คุณกำลังเข้าสู่ระบบอยู่ได้"));
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM `employee` WHERE `id_emp` = :id_emp");
        $stmt->execute([':id_emp' => $id_emp]);
        
        header("Location: user_manager.php?del=success");
        exit;
    } catch (\PDOException $e) {
        header("Location: user_manager.php?error=" . urlencode("เกิดข้อผิดพลาดของระบบ: ไม่สามารถลบพนักงานรายนี้ได้เนื่องจากข้อมูลผูกกับระบบการขาย"));
        exit;
    }
} else {
    header("Location: user_manager.php");
    exit;
}
?>
