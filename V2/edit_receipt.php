<?php
// edit_receipt.php
// Edit transaction details for Bovorn Service V2 (Draft status only)

require("db_connect.php");
check_login([2]); // Only clerk (role = 2) is allowed

$id_pay = isset($_GET['id_pay']) ? intval($_GET['id_pay']) : 0;

if ($id_pay === 0) {
    header("Location: billing.php");
    exit;
}

try {
    // 1. Fetch transaction
    $stmt = $pdo->prepare("SELECT * FROM `payment` WHERE `id_pay` = :id_pay");
    $stmt->execute([':id_pay' => $id_pay]);
    $pay = $stmt->fetch();
    
    if (!$pay) {
        die("Error: Transaction not found.");
    }
    
    // 2. Security Check: Only allow editing if pay_status is 1 (Draft)
    if ($pay['pay_status'] != 1) {
        die("Access Denied: This receipt has already been printed or cancelled, and cannot be edited.");
    }
    
    // Fetch car types & provinces for dropdowns
    $stmt_car = $pdo->query("SELECT * FROM `car_type`");
    $car_types = $stmt_car->fetchAll();
    
    $stmt_prov = $pdo->query("SELECT * FROM `province` ORDER BY `PROVINCE_NAME` ASC");
    $provinces = $stmt_prov->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// 3. Process Form POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_cus = trim($_POST['name_cus']);
    $addr_cus = trim($_POST['addr_cus']);
    $phone_cus = trim($_POST['phone_cus']);
    $car_type = intval($_POST['car_type']);
    $car_regis_id = trim($_POST['car_regis_id']);
    $prov = intval($_POST['prov']);
    
    $serv_1 = floatval($_POST['serv_1']);
    $serv_2 = floatval($_POST['serv_2']);
    $serv_2_1 = trim($_POST['serv_2_1']);
    $serv_3 = floatval($_POST['serv_3']);
    $serv_4 = floatval($_POST['serv_4']);
    $serv_4_1 = intval($_POST['serv_4_1']);
    $serv_4_2 = trim($_POST['serv_4_2']);
    $serv_5 = floatval($_POST['serv_5']);
    $serv_6 = floatval($_POST['serv_6']);
    $serv_7 = floatval($_POST['serv_7']);
    $serv_7_1 = trim($_POST['serv_7_1']);
    $discount = floatval($_POST['discount']);
    $pay_type = isset($_POST['pay_type']) ? intval($_POST['pay_type']) : 1;

    $pay_sum   = $serv_1 + $serv_2 + $serv_3 + $serv_4 + $serv_5 + $serv_6 + $serv_7;
    $pay_total = $pay_sum - $discount;

    // Determine pay_cash and pay_transfer
    if ($pay_type == 1) {
        $pay_cash     = $pay_total;
        $pay_transfer = 0;
    } elseif ($pay_type == 2) {
        $pay_cash     = 0;
        $pay_transfer = $pay_total;
    } else {
        $pay_cash     = floatval(isset($_POST['pay_cash'])     ? $_POST['pay_cash']     : 0);
        $pay_transfer = floatval(isset($_POST['pay_transfer']) ? $_POST['pay_transfer'] : 0);
        if (($pay_cash + $pay_transfer) != $pay_total) {
            $pay_transfer = max(0, $pay_total - $pay_cash);
        }
    }
    
    try {
        $sql = "UPDATE `payment` SET 
            cus_name = :cus_name,
            cus_addr = :cus_addr,
            cus_phone = :cus_phone,
            car_regis_id = :car_regis_id,
            id_car_type = :id_car_type,
            province_id = :province_id,
            serv_1 = :serv_1,
            serv_2 = :serv_2,
            serv_2_1 = :serv_2_1,
            serv_3 = :serv_3,
            serv_4 = :serv_4,
            serv_4_1 = :serv_4_1,
            serv_4_2 = :serv_4_2,
            serv_5 = :serv_5,
            serv_6 = :serv_6,
            serv_7 = :serv_7,
            serv_7_1 = :serv_7_1,
            pay_sum = :pay_sum,
            discount = :discount,
            pay_total = :pay_total,
            pay_type = :pay_type,
            pay_cash = :pay_cash,
            pay_transfer = :pay_transfer
            WHERE id_pay = :id_pay AND pay_status = 1";
            
        $stmt_update = $pdo->prepare($sql);
        $stmt_update->execute([
            ':cus_name'   => $name_cus,
            ':cus_addr'   => $addr_cus,
            ':cus_phone'  => $phone_cus,
            ':car_regis_id' => $car_regis_id,
            ':id_car_type' => $car_type,
            ':province_id' => $prov,
            ':serv_1'     => $serv_1,
            ':serv_2'     => $serv_2,
            ':serv_2_1'   => $serv_2_1,
            ':serv_3'     => $serv_3,
            ':serv_4'     => $serv_4,
            ':serv_4_1'   => $serv_4_1,
            ':serv_4_2'   => $serv_4_2,
            ':serv_5'     => $serv_5,
            ':serv_6'     => $serv_6,
            ':serv_7'     => $serv_7,
            ':serv_7_1'   => $serv_7_1,
            ':pay_sum'    => $pay_sum,
            ':discount'   => $discount,
            ':pay_total'  => $pay_total,
            ':pay_type'   => $pay_type,
            ':pay_cash'   => $pay_cash,
            ':pay_transfer' => $pay_transfer,
            ':id_pay'     => $id_pay
        ]);
        
        $_SESSION['last_pay_id'] = $id_pay;
        header("Location: billing.php");
        exit;
        
    } catch (\PDOException $e) {
        $db_error = "เกิดข้อผิดพลาดในการบันทึกการแก้ไข: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขข้อมูลบิล #<?php echo $pay['id_pay']; ?> - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* ── Modern Edit Form — mirrors billing.php modal design ── */

        .modern-section-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            border-left: 4px solid var(--primary);
            padding-left: 0.75rem;
            margin-bottom: 1.25rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* CSS Grid — customer info block */
        .modern-form-grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 1.25rem;
            margin-bottom: 2rem;
            background-color: var(--bg-main);
            padding: 1.5rem;
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
        }

        .form-col-4  { grid-column: span 4; }
        .form-col-8  { grid-column: span 8; }
        .form-col-12 { grid-column: span 12; }

        @media (max-width: 768px) {
            .form-col-4, .form-col-8, .form-col-12 { grid-column: span 12; }
        }

        .modern-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .modern-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .modern-input, .modern-select {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.6rem 0.85rem;
            font-size: 0.95rem;
            background-color: var(--bg-card);
            color: var(--text-main);
            transition: var(--transition);
            width: 100%;
            box-sizing: border-box;
        }

        .modern-input:focus, .modern-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        /* Modern Service Table */
        .modern-service-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.75rem;
            border-radius: var(--radius-md);
            overflow: hidden;
            border: 1px solid var(--border);
            background-color: var(--bg-card);
        }

        .modern-service-table th {
            background-color: var(--primary-light);
            color: var(--primary);
            font-weight: 700;
            padding: 10px 14px;
            text-align: left;
            border-bottom: 2px solid var(--border);
            font-size: 0.9rem;
        }

        .modern-service-table th.center,
        .modern-service-table td.center { text-align: center; }

        .modern-service-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.925rem;
        }

        .modern-service-table tr:hover td { background-color: var(--bg-main); }

        .modern-service-table input[type="number"] {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.5rem 0.75rem;
            font-size: 0.95rem;
            text-align: right;
            background-color: var(--bg-card);
            color: var(--text-main);
            width: 100%;
            box-sizing: border-box;
            transition: var(--transition);
        }

        .modern-service-table input[type="number"]:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
            outline: none;
        }

        .table-inline-flex {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .modern-table-input {
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            padding: 0.4rem 0.75rem;
            font-size: 0.9rem;
            background-color: var(--bg-card);
            color: var(--text-main);
            outline: none;
            transition: var(--transition);
        }

        .modern-table-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        /* Action row */
        .modern-actions-row {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            border-top: 1px solid var(--border);
            padding-top: 1.25rem;
        }

        .btn-modern-submit {
            background-color: var(--primary);
            border: 1px solid var(--primary);
            color: var(--text-inverse);
            padding: 0.6rem 2rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-modern-submit:hover {
            background-color: #1d4ed8;
            border-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        .btn-modern-cancel {
            background-color: var(--bg-main);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 0.6rem 2rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
        }

        .btn-modern-cancel:hover {
            background-color: var(--border);
            color: var(--text-main);
        }

        /* Bill ID badge at top */
        .bill-id-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: var(--primary-light);
            color: var(--primary);
            border: 1px solid var(--primary);
            border-radius: var(--radius-md);
            padding: 0.35rem 0.9rem;
            font-size: 0.875rem;
            font-weight: 700;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div class="header-title">✏️ แก้ไขรายการขาย</div>
                    <span class="bill-id-badge">🧾 เลขบิล #<?php echo $pay['id_pay']; ?></span>
                </div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    <a href="billing.php" style="color: var(--primary); text-decoration: none; display: inline-flex; align-items: center; gap: 0.25rem;">
                        ← กลับหน้ารายการขาย
                    </a>
                </div>
            </div>
            
            <div class="content-body">
                <?php if (isset($db_error)): ?>
                    <div style="background-color: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; padding: 0.875rem 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem;">
                        ⚠️ <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>
                
                <form action="edit_receipt.php?id_pay=<?php echo $pay['id_pay']; ?>" method="POST" id="editForm" onsubmit="return validateForm()">
                    <div class="card">
                        <!-- Section 1: Customer Info -->
                        <div class="modern-section-title">
                            👤 ข้อมูลลูกค้าและพาหนะ
                        </div>
                        
                        <div class="modern-form-grid">
                            <div class="modern-form-group form-col-4">
                                <label for="name_cus" class="modern-label">ชื่อ - นามสกุลลูกค้า <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="name_cus" id="name_cus" class="modern-input" required
                                    value="<?php echo htmlspecialchars($pay['cus_name']); ?>"
                                    placeholder="เช่น นายสมชาย ใจดี">
                            </div>
                            
                            <div class="modern-form-group form-col-8">
                                <label for="addr_cus" class="modern-label">ที่อยู่ลูกค้า <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="addr_cus" id="addr_cus" class="modern-input" required
                                    value="<?php echo htmlspecialchars($pay['cus_addr']); ?>"
                                    placeholder="บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด">
                            </div>
                            
                            <div class="modern-form-group form-col-4">
                                <label for="phone_cus" class="modern-label">เบอร์โทรศัพท์ <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="phone_cus" id="phone_cus" class="modern-input" required
                                    value="<?php echo htmlspecialchars($pay['cus_phone']); ?>"
                                    placeholder="เช่น 0891234567">
                            </div>
                            
                            <div class="modern-form-group form-col-4">
                                <label for="car_type" class="modern-label">ประเภทรถยนต์ <span style="color:var(--danger)">*</span></label>
                                <select name="car_type" id="car_type" class="modern-select" required>
                                    <option value="">-- เลือกประเภทรถ --</option>
                                    <?php foreach ($car_types as $c): ?>
                                        <option value="<?php echo $c['id_car_type']; ?>" <?php echo $c['id_car_type'] == $pay['id_car_type'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['car_type_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="modern-form-group form-col-4">
                                <label for="car_regis_id" class="modern-label">ทะเบียนรถ <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="car_regis_id" id="car_regis_id" class="modern-input" required
                                    value="<?php echo htmlspecialchars($pay['car_regis_id']); ?>"
                                    placeholder="เช่น กข 1234">
                            </div>
                            
                            <div class="modern-form-group form-col-4">
                                <label for="prov" class="modern-label">จังหวัดทะเบียนรถ <span style="color:var(--danger)">*</span></label>
                                <select name="prov" id="prov" class="modern-select" required>
                                    <option value="">-- เลือกจังหวัด --</option>
                                    <?php foreach ($provinces as $p): ?>
                                        <option value="<?php echo $p['PROVINCE_ID']; ?>" <?php echo $p['PROVINCE_ID'] == $pay['province_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($p['PROVINCE_NAME']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Section 2: Services -->
                        <div class="modern-section-title">
                            💰 รายการค่าบริการและชำระเงิน
                        </div>
                        
                        <table class="modern-service-table">
                            <thead>
                                <tr>
                                    <th style="width: 80px;" class="center">ลำดับ</th>
                                    <th>รายการบริการ</th>
                                    <th style="width: 250px;" class="center">จำนวนเงิน (บาท)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="center">1</td>
                                    <td>ต่อ พ.ร.บ. คุ้มครองผู้ประสบภัยจากรถ</td>
                                    <td><input type="number" step="0.01" name="serv_1" id="serv_1" class="calc-val" value="<?php echo $pay['serv_1']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">2</td>
                                    <td>
                                        <div class="table-inline-flex">
                                            <span>ตรวจสภาพรถ (ตรอ.)</span>
                                            <input type="text" name="serv_2_1" id="serv_2_1" class="modern-table-input" style="width: 220px;" placeholder="เลขที่ ตรอ."
                                                value="<?php echo htmlspecialchars($pay['serv_2_1']); ?>">
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" name="serv_2" id="serv_2" class="calc-val" value="<?php echo $pay['serv_2']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">3</td>
                                    <td>ต่อภาษีประจำปี (กรณีปกติ)</td>
                                    <td><input type="number" step="0.01" name="serv_3" id="serv_3" class="calc-val" value="<?php echo $pay['serv_3']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">4</td>
                                    <td>
                                        <div class="table-inline-flex">
                                            <span>ต่อภาษีย้อนหลัง</span>
                                            <select name="serv_4_1" id="serv_4_1" class="modern-table-input" style="width: 80px;">
                                                <option value="0" <?php echo $pay['serv_4_1'] == 0 ? 'selected' : ''; ?>>---</option>
                                                <option value="1" <?php echo $pay['serv_4_1'] == 1 ? 'selected' : ''; ?>>1 ปี</option>
                                                <option value="2" <?php echo $pay['serv_4_1'] == 2 ? 'selected' : ''; ?>>2 ปี</option>
                                                <option value="3" <?php echo $pay['serv_4_1'] == 3 ? 'selected' : ''; ?>>3 ปี</option>
                                                <option value="4" <?php echo $pay['serv_4_1'] == 4 ? 'selected' : ''; ?>>4 ปี</option>
                                                <option value="5" <?php echo $pay['serv_4_1'] == 5 ? 'selected' : ''; ?>>5 ปี</option>
                                            </select>
                                            <span>ปี ตั้งแต่</span>
                                            <input type="text" name="serv_4_2" id="serv_4_2" class="modern-table-input" style="width: 100px;" placeholder="เช่น 2565"
                                                value="<?php echo htmlspecialchars($pay['serv_4_2']); ?>">
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" name="serv_4" id="serv_4" class="calc-val" value="<?php echo $pay['serv_4']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">5</td>
                                    <td>ค่าปรับล่าช้าภาษี</td>
                                    <td><input type="number" step="0.01" name="serv_5" id="serv_5" class="calc-val" value="<?php echo $pay['serv_5']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">6</td>
                                    <td>ค่าสมุดเล่มทะเบียน</td>
                                    <td><input type="number" step="0.01" name="serv_6" id="serv_6" class="calc-val" value="<?php echo $pay['serv_6']; ?>"></td>
                                </tr>
                                <tr>
                                    <td class="center">7</td>
                                    <td>
                                        <div class="table-inline-flex">
                                            <span>ค่าบริการอื่นๆ</span>
                                            <input type="text" name="serv_7_1" id="serv_7_1" class="modern-table-input" style="width: 250px;" placeholder="ระบุบริการอื่นๆ"
                                                value="<?php echo htmlspecialchars($pay['serv_7_1']); ?>">
                                        </div>
                                    </td>
                                    <td><input type="number" step="0.01" name="serv_7" id="serv_7" class="calc-val" value="<?php echo $pay['serv_7']; ?>"></td>
                                </tr>
                                <tr style="background-color: var(--bg-main); font-weight: 600;">
                                    <td colspan="2" style="text-align: right; font-size: 0.95rem; border-top: 1px solid var(--border);">ยอดรวมเงินสะสม (Gross Total):</td>
                                    <td style="text-align: right; font-size: 1.05rem; padding-right: 20px; color: var(--text-main); border-top: 1px solid var(--border);" id="grossTotal">0.00</td>
                                </tr>
                                <tr>
                                    <td class="center">8</td>
                                    <td style="text-align: right; font-weight: 500; font-size: 0.95rem; vertical-align: middle;">ส่วนลดร้านค้า (Discount):</td>
                                    <td><input type="number" step="0.01" name="discount" id="discount" class="calc-val" value="<?php echo $pay['discount']; ?>"></td>
                                </tr>
                                <tr style="background-color: var(--primary-light); font-weight: bold; color: var(--primary);">
                                    <td colspan="2" style="text-align: right; font-size: 1.05rem; border-top: 1px solid var(--border);">ยอดชำระสุทธิ (Net Total):</td>
                                    <td style="text-align: right; font-size: 1.25rem; padding-right: 20px; font-weight: 800; border-top: 1px solid var(--border);" id="netTotal">0.00</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Modern Payment Method Selector -->
                        <div style="margin-top: 1.5rem; background-color: var(--bg-main); padding: 1rem 1.5rem; border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <div style="font-weight: 600; color: var(--text-main); display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.85rem;">
                                <span>💳 ช่องทางการชำระเงิน :</span>
                            </div>
                            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--border); background-color: var(--bg-card); color: var(--text-muted); font-weight: 600; transition: all 0.2s ease;" id="pay_cash_label">
                                    <input type="radio" name="pay_type" value="1" <?php echo (isset($pay['pay_type']) ? $pay['pay_type'] : 1) == 1 ? 'checked' : ''; ?> style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                    💵 เงินสด
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--border); background-color: var(--bg-card); color: var(--text-muted); font-weight: 600; transition: all 0.2s ease;" id="pay_transfer_label">
                                    <input type="radio" name="pay_type" value="2" <?php echo (isset($pay['pay_type']) ? $pay['pay_type'] : 1) == 2 ? 'checked' : ''; ?> style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                    📱 เงินโอน
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--border); background-color: var(--bg-card); color: var(--text-muted); font-weight: 600; transition: all 0.2s ease;" id="pay_mixed_label">
                                    <input type="radio" name="pay_type" value="3" <?php echo (isset($pay['pay_type']) ? $pay['pay_type'] : 1) == 3 ? 'checked' : ''; ?> style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                    💵📱 ทั้งเงินสดและเงินโอน
                                </label>
                            </div>
                            <!-- Sub-fields for mixed payment (type 3) -->
                            <div id="mixed_payment_fields" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px dashed var(--primary); gap: 1.25rem; flex-wrap: wrap; align-items: flex-end;">
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">💵 ยอดชำระด้วยเงินสด (บาท) <span style="color:var(--danger)">*</span></label>
                                    <input type="number" step="0.01" min="0" name="pay_cash" id="pay_cash_input"
                                        style="border: 1.5px solid var(--primary); border-radius: var(--radius-md); padding: 0.55rem 0.85rem; font-size: 0.95rem; background: var(--bg-card); color: var(--text-main); width: 180px;"
                                        placeholder="0.00" value="<?php echo isset($pay['pay_cash']) ? $pay['pay_cash'] : 0; ?>" oninput="syncMixedTransfer()">
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">📱 ยอดชำระด้วยเงินโอน (บาท) <span style="color:var(--danger)">*</span></label>
                                    <input type="number" step="0.01" min="0" name="pay_transfer" id="pay_transfer_input"
                                        style="border: 1.5px solid var(--primary); border-radius: var(--radius-md); padding: 0.55rem 0.85rem; font-size: 0.95rem; background: var(--bg-card); color: var(--text-main); width: 180px;"
                                        placeholder="0.00" value="<?php echo isset($pay['pay_transfer']) ? $pay['pay_transfer'] : 0; ?>" oninput="syncMixedCash()">
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">ยอดรวม</label>
                                    <div id="mixed_sum_display" style="font-size: 1rem; font-weight: 700; color: var(--primary); padding: 0.55rem 0;">0.00 บาท</div>
                                </div>
                                <div id="mixed_warn" style="display:none; font-size:0.82rem; color:var(--danger); font-weight:600; width:100%; margin-top:0.25rem;"></div>
                            </div>
                        </div>

                        <!-- Modern Action Buttons -->
                        <div class="modern-actions-row">
                            <a href="billing.php" class="btn-modern-cancel">← ยกเลิกการแก้ไข</a>
                            <button type="submit" class="btn-modern-submit">💾 บันทึกการแก้ไข</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const inputs = document.querySelectorAll('.calc-val');
        const grossTotalEl = document.getElementById('grossTotal');
        const netTotalEl   = document.getElementById('netTotal');

        inputs.forEach(input => {
            input.addEventListener('input', calculateTotals);
            input.addEventListener('focus', function() { this.select(); });
            input.addEventListener('blur',  function() {
                if (this.value.trim() === '') this.value = '0.00';
            });
        });

        function calculateTotals() {
            var grossSum = 0;
            for (var i = 1; i <= 7; i++) {
                var el = document.getElementById('serv_' + i);
                grossSum += el ? (parseFloat(el.value) || 0) : 0;
            }
            var discountEl = document.getElementById('discount');
            var discount = discountEl ? (parseFloat(discountEl.value) || 0) : 0;
            var netSum = grossSum - discount;

            if (grossTotalEl) grossTotalEl.textContent = grossSum.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (netTotalEl)   netTotalEl.textContent   = netSum.toLocaleString('th-TH',   { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            var pt = document.querySelector('input[name="pay_type"]:checked');
            if (pt && pt.value === '3' && typeof updateMixedSum === 'function') {
                updateMixedSum();
            }
        }

        // Run on page load to display current values
        calculateTotals();
        updatePayTypeStyles();

        function updatePayTypeStyles() {
            var payTypeChecked = document.querySelector('input[name="pay_type"]:checked');
            var payType = payTypeChecked ? payTypeChecked.value : '1';
            var cashLabel     = document.getElementById('pay_cash_label');
            var transferLabel = document.getElementById('pay_transfer_label');
            var mixedLabel    = document.getElementById('pay_mixed_label');
            var mixedFields   = document.getElementById('mixed_payment_fields');

            if (!cashLabel || !transferLabel) return;

            var labels = [cashLabel, transferLabel, mixedLabel];
            for (var j = 0; j < labels.length; j++) {
                if (labels[j]) {
                    labels[j].style.borderColor = 'var(--border)';
                    labels[j].style.backgroundColor = 'var(--bg-card)';
                    labels[j].style.color = 'var(--text-muted)';
                }
            }

            var active = payType === '1' ? cashLabel : (payType === '2' ? transferLabel : mixedLabel);
            if (active) {
                active.style.borderColor = 'var(--primary)';
                active.style.backgroundColor = 'var(--primary-light)';
                active.style.color = 'var(--primary)';
            }

            if (mixedFields) {
                mixedFields.style.display = (payType === '3') ? 'flex' : 'none';
                if (payType === '3') updateMixedSum();
            }
        }

        function validateForm() {
            if (document.getElementById('name_cus').value.trim() === '') {
                alert('กรุณากรอกชื่อลูกค้า'); return false;
            }
            if (document.getElementById('addr_cus').value.trim() === '') {
                alert('กรุณากรอกที่อยู่ลูกค้า'); return false;
            }
            if (document.getElementById('phone_cus').value.trim() === '') {
                alert('กรุณากรอกเบอร์โทรลูกค้า'); return false;
            }
            if (document.getElementById('car_type').value === '') {
                alert('กรุณาเลือกประเภทรถ'); return false;
            }
            if (document.getElementById('car_regis_id').value.trim() === '') {
                alert('กรุณากรอกเลขทะเบียนรถ'); return false;
            }
            if (document.getElementById('prov').value === '') {
                alert('กรุณาเลือกจังหวัด'); return false;
            }

            const inspectionFee = parseFloat(document.getElementById('serv_2').value) || 0;
            if (inspectionFee > 0 && document.getElementById('serv_2_1').value.trim() === '') {
                alert('มีค่าบริการตรวจสภาพรถ กรุณากรอกเลขที่ ตรอ.');
                document.getElementById('serv_2_1').focus();
                return false;
            }

            // Validate type 3
            var selPt = document.querySelector('input[name="pay_type"]:checked');
            if (selPt && selPt.value === '3') {
                var netVal = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
                var cInp   = parseFloat(document.getElementById('pay_cash_input').value) || 0;
                var tInp   = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
                if (cInp <= 0 && tInp <= 0) {
                    alert('กรุณากรอกยอดเงินสดและเงินโอนให้ครบ');
                    document.getElementById('pay_cash_input').focus();
                    return false;
                }
                if (Math.abs((cInp + tInp) - netVal) > 0.01) {
                    alert('ยอดเงินสด + เงินโอน ต้องเท่ากับยอดสุทธิ ' + netVal.toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท');
                    document.getElementById('pay_cash_input').focus();
                    return false;
                }
            }

            return true;
        }

        function updateMixedSum() {
            var netVal = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            var cInput = parseFloat(document.getElementById('pay_cash_input').value) || 0;
            var tInput = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
            var sumEl  = document.getElementById('mixed_sum_display');
            var warnEl = document.getElementById('mixed_warn');
            var total  = cInput + tInput;
            sumEl.textContent = total.toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท';
            if (netVal > 0 && Math.abs(total - netVal) > 0.01) {
                warnEl.textContent = '⚠️ ยอดรวมต้องเท่ากับยอดสุทธิ ' + netVal.toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท';
                warnEl.style.display = 'block';
                sumEl.style.color = 'var(--danger)';
            } else {
                warnEl.style.display = 'none';
                sumEl.style.color = 'var(--primary)';
            }
        }

        function syncMixedTransfer() {
            var netVal    = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            var cInput    = parseFloat(document.getElementById('pay_cash_input').value) || 0;
            var remaining = Math.max(0, netVal - cInput);
            document.getElementById('pay_transfer_input').value = remaining.toFixed(2);
            updateMixedSum();
        }

        function syncMixedCash() {
            var netVal    = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            var tInput    = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
            var remaining = Math.max(0, netVal - tInput);
            document.getElementById('pay_cash_input').value = remaining.toFixed(2);
            updateMixedSum();
        }
    </script>
</body>
</html>
