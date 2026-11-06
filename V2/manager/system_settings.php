<?php
// manager/system_settings.php
// System Settings dashboard (Manager view) for Bovorn Service V2
// Configures referral fees dynamically

require("con_db.php");
check_login([1]); // Only manager (emp_type = 1) is allowed

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    validate_csrf();
    $tax_car_fee = trim($_POST['tax_car_fee']);
    $tax_motorcycle_fee = trim($_POST['tax_motorcycle_fee']);
    
    if ($tax_car_fee === '' || $tax_motorcycle_fee === '') {
        header("Location: system_settings.php?error=" . urlencode("กรุณากรอกข้อมูลค่าธรรมเนียมให้ครบถ้วน"));
        exit;
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE `system_settings` SET `setting_value` = :val WHERE `setting_key` = :key");
            
            $stmt->execute([':val' => floatval($tax_car_fee), ':key' => 'tax_car_fee']);
            $stmt->execute([':val' => floatval($tax_motorcycle_fee), ':key' => 'tax_motorcycle_fee']);
            
            $pdo->commit();
            header("Location: system_settings.php?save=success");
            exit;
        } catch (\PDOException $e) {
            $pdo->rollBack();
            header("Location: system_settings.php?error=" . urlencode("เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage()));
            exit;
        }
    }
}

// Fetch current values
$current_car_fee = floatval(get_system_setting('tax_car_fee', 10));
$current_motorcycle_fee = floatval(get_system_setting('tax_motorcycle_fee', 5));

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .settings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .form-section-title {
            font-size: 1rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 2px solid var(--border);
            padding-bottom: 0.5rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-desc {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">⚙️ ตั้งค่าระบบ Bovorn Service V2</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    บทบาท: <strong style="color: var(--primary);">ผู้จัดการระบบ</strong>
                </div>
            </div>
            
            <div class="content-body">
                <?php if (isset($_GET['save']) && $_GET['save'] === 'success'): ?>
                    <div class="badge badge-success" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        บันทึกการตั้งค่าระบบเรียบร้อยแล้ว
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card" style="max-width: 720px; margin: 0 auto;">
                    <div class="card-header">
                        <h3 class="card-title">📝 กำหนดอัตราค่าธรรมเนียมส่งต่อภาษี (Tax Referral Fees)</h3>
                    </div>
                    
                    <form action="system_settings.php" method="POST">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="save_settings">
                        
                        <div class="form-section-title">
                            <span>📋</span> อัตราส่งต่อรายวัน
                        </div>
                        
                        <div class="form-group">
                            <label for="tax_car_fee" class="form-label" style="font-weight: 700;">ค่าส่งต่อภาษี รถยนต์ (บาทต่อคัน) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" min="0" name="tax_car_fee" id="tax_car_fee" class="form-control" value="<?php echo htmlspecialchars($current_car_fee); ?>" required>
                            <div class="info-desc">ค่าบริการธรรมเนียมจัดส่งต่อคัน สำหรับประเภทรถยนต์ทั้งหมด (จะถูกใช้ในรายงานการนำส่งภาษีรายวัน)</div>
                        </div>
                        
                        <div class="form-group" style="margin-top: 1.5rem;">
                            <label for="tax_motorcycle_fee" class="form-label" style="font-weight: 700;">ค่าส่งต่อภาษี รถจักรยานยนต์ / จยย. (บาทต่อคัน) <span style="color:var(--danger)">*</span></label>
                            <input type="number" step="0.01" min="0" name="tax_motorcycle_fee" id="tax_motorcycle_fee" class="form-control" value="<?php echo htmlspecialchars($current_motorcycle_fee); ?>" required>
                            <div class="info-desc">ค่าบริการธรรมเนียมจัดส่งต่อคัน สำหรับประเภทรถจักรยานยนต์/มอเตอร์ไซค์</div>
                        </div>
                        
                        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 1.25rem; margin-top: 2rem;">
                            <button type="submit" class="btn btn-primary" style="padding-left: 2.5rem; padding-right: 2.5rem; font-weight: 600;">💾 บันทึกการตั้งค่า</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
