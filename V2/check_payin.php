<?php
// check_payin.php
// Verification screen for sales receipts in Bovorn Service V2

require("db_connect.php");
check_login([2]); // Only clerk (role = 2) is allowed

$id_pay = isset($_GET['id_pay']) ? intval($_GET['id_pay']) : (isset($_SESSION['last_pay_id']) ? intval($_SESSION['last_pay_id']) : 0);

if ($id_pay === 0) {
    header("Location: billing.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT p.*, c.car_type_name, pr.PROVINCE_NAME, e.name as emp_name 
                           FROM `payment` p
                           JOIN `car_type` c ON p.id_car_type = c.id_car_type
                           JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                           JOIN `employee` e ON p.id_emp = e.id_emp
                           WHERE p.id_pay = :id_pay");
    $stmt->execute([':id_pay' => $id_pay]);
    $pay = $stmt->fetch();
    
    if (!$pay) {
        die("Error: Transaction not found.");
    }
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตรวจสอบข้อมูลบิล - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">ตรวจสอบข้อมูลใบเสร็จรับเงิน</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    เลขที่บิล: <strong style="color: var(--primary);">#<?php echo $pay['id_pay']; ?></strong>
                </div>
            </div>
            
            <div class="content-body">
                <div class="card" style="border-top: 4px solid var(--primary);">
                    <div class="card-header">
                        <h3 class="card-title">🔍 ตรวจสอบความถูกต้องก่อนสั่งพิมพ์ใบเสร็จ</h3>
                        <div>
                            <?php if ($pay['pay_status'] == 1): ?>
                                <span class="badge badge-warning">สถานะ: รอดำเนินการพิมพ์</span>
                            <?php elseif ($pay['pay_status'] == 2): ?>
                                <span class="badge badge-success">สถานะ: พิมพ์บิลแล้ว (ล็อก)</span>
                            <?php else: ?>
                                <span class="badge badge-danger">สถานะ: ยกเลิกบิล</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-md);">
                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary);">👤 ข้อมูลลูกค้า</h4>
                            <p style="margin-bottom: 0.5rem;"><strong>ชื่อลูกค้า:</strong> <?php echo htmlspecialchars($pay['cus_name']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>ที่อยู่:</strong> <?php echo htmlspecialchars($pay['cus_addr']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>เบอร์โทรศัพท์:</strong> <?php echo htmlspecialchars($pay['cus_phone']); ?></p>
                        </div>
                        
                        <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-md);">
                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary);">🚗 ข้อมูลยานพาหนะ</h4>
                            <p style="margin-bottom: 0.5rem;"><strong>ประเภทรถ:</strong> <?php echo htmlspecialchars($pay['car_type_name']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>ทะเบียนรถ:</strong> <?php echo htmlspecialchars($pay['car_regis_id']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>จังหวัดจดทะเบียน:</strong> <?php echo htmlspecialchars($pay['PROVINCE_NAME']); ?></p>
                        </div>
                        
                        <div style="background-color: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-md);">
                            <h4 style="font-weight: 600; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.5rem; color: var(--primary);">🗓️ วันเวลาบันทึก</h4>
                            <p style="margin-bottom: 0.5rem;"><strong>วันที่ชำระ:</strong> <?php echo $pay['pay_day'] . ' / ' . $pay['pay_month'] . ' / ' . ($pay['pay_year'] + 543); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>เวลาบันทึก:</strong> <?php echo htmlspecialchars($pay['pay_time']); ?> น.</p>
                            <p style="margin-bottom: 0.5rem;"><strong>พนักงานขาย:</strong> <?php echo htmlspecialchars($pay['emp_name']); ?></p>
                            <p style="margin-bottom: 0.5rem;"><strong>ช่องทางชำระเงิน:</strong> 
                                <?php if ((isset($pay['pay_type']) ? $pay['pay_type'] : 1) == 2): ?>
                                    <span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-weight: 600; font-size: 0.8rem; padding: 2px 8px; vertical-align: middle;">📱 เงินโอน</span>
                                <?php else: ?>
                                    <span class="badge" style="background-color: #fef3c7; color: #d97706; font-weight: 600; font-size: 0.8rem; padding: 2px 8px; vertical-align: middle;">💵 เงินสด</span>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    
                    <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 1rem;">รายการชำระเงิน</h3>
                    <table class="table-modern" style="margin-bottom: 2rem;">
                        <thead>
                            <tr>
                                <th style="width: 80px; text-align: center;">ลำดับ</th>
                                <th>รายการบริการ</th>
                                <th style="width: 200px; text-align: right;">จำนวนเงิน (บาท)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="text-align: center;">1</td>
                                <td>ต่อ พ.ร.บ. คุ้มครองผู้ประสบภัยจากรถ</td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_1'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">2</td>
                                <td>ตรวจสภาพรถ (ตรอ.) <?php echo !empty($pay['serv_2_1']) ? '(เลขที่: ' . htmlspecialchars($pay['serv_2_1']) . ')' : ''; ?></td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_2'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">3</td>
                                <td>ต่อภาษีประจำปี (กรณีปกติ)</td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_3'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">4</td>
                                <td>ต่อภาษีย้อนหลัง <?php echo $pay['serv_4_1'] > 0 ? '(' . $pay['serv_4_1'] . ' ปี ตั้งแต่ พ.ศ. ' . htmlspecialchars($pay['serv_4_2']) . ')' : ''; ?></td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_4'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">5</td>
                                <td>ค่าปรับล่าช้าภาษี</td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_5'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">6</td>
                                <td>ค่าสมุดเล่มทะเบียน</td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_6'], 2); ?></td>
                            </tr>
                            <tr>
                                <td style="text-align: center;">7</td>
                                <td>ค่าบริการอื่นๆ <?php echo !empty($pay['serv_7_1']) ? '(รายละเอียด: ' . htmlspecialchars($pay['serv_7_1']) . ')' : ''; ?></td>
                                <td style="text-align: right; font-weight: 500;"><?php echo number_format($pay['serv_7'], 2); ?></td>
                            </tr>
                            <tr style="background-color: var(--bg-main); font-weight: 600;">
                                <td colspan="2" style="text-align: right; font-size: 0.95rem;">ยอดรวมเงิน (Gross Total):</td>
                                <td style="text-align: right; font-size: 0.95rem; font-weight: 600;"><?php echo number_format($pay['pay_sum'], 2); ?></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align: right; font-weight: 500; color: var(--danger);">ส่วนลดร้านค้า (Discount):</td>
                                <td style="text-align: right; font-weight: 600; color: var(--danger);">-<?php echo number_format($pay['discount'], 2); ?></td>
                            </tr>
                            <tr style="background-color: var(--primary-light); font-weight: 700; color: var(--primary);">
                                <td colspan="2" style="text-align: right; font-size: 1.1rem;">ยอดชำระสุทธิ (Net Total):</td>
                                <td style="text-align: right; font-size: 1.15rem; font-weight: 700;"><?php echo number_format($pay['pay_total'], 2); ?></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <?php if ($pay['pay_status'] == 1): ?>
                                <a href="edit_receipt.php?id_pay=<?php echo $pay['id_pay']; ?>" class="btn btn-secondary">
                                    ✏️ แก้ไขข้อมูลบิลนี้
                                </a>
                            <?php endif; ?>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <a href="billing.php" class="btn btn-secondary">🔙 ย้อนกลับหน้าการขาย</a>
                            <a href="receipt.php?id_pay=<?php echo $pay['id_pay']; ?>" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">
                                🖨️ ยืนยันพิมพ์ใบเสร็จ
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
