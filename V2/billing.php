<?php
// billing.php
// Clerk sales entry form for Bovorn Service V2

require("db_connect.php");
check_login([2]); // Only clerk (role = 2) is allowed

$id_emp = $_SESSION['id_emp'];
$id_branch = $_SESSION['id_branch'];

// Fetch employee details to show name at the bottom
$emp_name = "";
try {
    $stmt_emp = $pdo->prepare("SELECT name FROM employee WHERE id_emp = :id_emp");
    $stmt_emp->execute([':id_emp' => $id_emp]);
    $emp_name = $stmt_emp->fetchColumn();
} catch (\PDOException $e) {
    $emp_name = "พนักงานบริการ";
}

// Fetch car types
try {
    $stmt_car = $pdo->query("SELECT * FROM `car_type`");
    $car_types = $stmt_car->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Fetch provinces
try {
    $stmt_prov = $pdo->query("SELECT * FROM `province` ORDER BY `PROVINCE_NAME` ASC");
    $provinces = $stmt_prov->fetchAll();
} catch (\PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

// Process Form Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    date_default_timezone_set('Asia/Bangkok');
    
    $name_cus = trim($_POST['name_cus']);
    $addr_cus = trim($_POST['addr_cus']);
    $phone_cus = trim($_POST['phone_cus']);
    $car_type = intval($_POST['car_type']);
    $car_regis_id = trim($_POST['car_regis_id']);
    $prov = intval($_POST['prov']);
    
    // Service charges (default to 0 if empty)
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
    
    // Calculations
    $pay_sum = $serv_1 + $serv_2 + $serv_3 + $serv_4 + $serv_5 + $serv_6 + $serv_7;
    $pay_total = $pay_sum - $discount;
    
    // Determine pay_cash and pay_transfer based on pay_type
    if ($pay_type == 1) {
        $pay_cash     = $pay_total;
        $pay_transfer = 0;
    } elseif ($pay_type == 2) {
        $pay_cash     = 0;
        $pay_transfer = $pay_total;
    } else { // type 3: mixed
        $pay_cash     = floatval(isset($_POST['pay_cash'])     ? $_POST['pay_cash']     : 0);
        $pay_transfer = floatval(isset($_POST['pay_transfer']) ? $_POST['pay_transfer'] : 0);
        // Clamp to pay_total if sum exceeds it
        if (($pay_cash + $pay_transfer) != $pay_total) {
            $pay_transfer = max(0, $pay_total - $pay_cash);
        }
    }
    
    $pay_day = date('d');
    $pay_month = date('m');
    $pay_year = date('Y');
    $pay_time = date('H:i:s');
    
    try {
        $sql = "INSERT INTO `payment` (
            id_emp, id_branch, cus_name, cus_addr, cus_phone, car_regis_id, id_car_type, province_id,
            serv_1, serv_2, serv_2_1, serv_3, serv_4, serv_4_1, serv_4_2, serv_5, serv_6, serv_7, serv_7_1,
            pay_sum, discount, pay_total, pay_status, pay_type, pay_cash, pay_transfer, pay_day, pay_month, pay_year, pay_time
        ) VALUES (
            :id_emp, :id_branch, :cus_name, :cus_addr, :cus_phone, :car_regis_id, :id_car_type, :province_id,
            :serv_1, :serv_2, :serv_2_1, :serv_3, :serv_4, :serv_4_1, :serv_4_2, :serv_5, :serv_6, :serv_7, :serv_7_1,
            :pay_sum, :discount, :pay_total, 1, :pay_type, :pay_cash, :pay_transfer, :pay_day, :pay_month, :pay_year, :pay_time
        )";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id_emp' => $id_emp,
            ':id_branch' => $id_branch,
            ':cus_name' => $name_cus,
            ':cus_addr' => $addr_cus,
            ':cus_phone' => $phone_cus,
            ':car_regis_id' => $car_regis_id,
            ':id_car_type' => $car_type,
            ':province_id' => $prov,
            ':serv_1' => $serv_1,
            ':serv_2' => $serv_2,
            ':serv_2_1' => $serv_2_1,
            ':serv_3' => $serv_3,
            ':serv_4' => $serv_4,
            ':serv_4_1' => $serv_4_1,
            ':serv_4_2' => $serv_4_2,
            ':serv_5' => $serv_5,
            ':serv_6' => $serv_6,
            ':serv_7' => $serv_7,
            ':serv_7_1' => $serv_7_1,
            ':pay_sum' => $pay_sum,
            ':discount' => $discount,
            ':pay_total' => $pay_total,
            ':pay_type'     => $pay_type,
            ':pay_cash'     => $pay_cash,
            ':pay_transfer' => $pay_transfer,
            ':pay_day'      => $pay_day,
            ':pay_month' => $pay_month,
            ':pay_year' => $pay_year,
            ':pay_time' => $pay_time
        ]);
        
        // Save printed bill details to session for check page
        $new_id = $pdo->lastInsertId();
        $_SESSION['last_pay_id'] = $new_id;
        
        header("Location: billing.php");
        exit;
        
    } catch (\PDOException $e) {
        error_log("Billing insert error: " . $e->getMessage());
        $db_error = "เกิดข้อผิดพลาดในการบันทึกข้อมูล กรุณาลองใหม่อีกครั้ง";
    }
}

// Fetch today's transactions for this branch (excluding cancelled status = 3)
$today_day = intval(date('d'));
$today_month = intval(date('m'));
$today_year = intval(date('Y'));

try {
    $stmt_today = $pdo->prepare("SELECT p.*, c.car_type_name, pr.PROVINCE_NAME 
                                 FROM `payment` p
                                 JOIN `car_type` c ON p.id_car_type = c.id_car_type
                                 JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                                 WHERE p.id_branch = :id_branch 
                                   AND p.pay_day = :pay_day 
                                   AND p.pay_month = :pay_month 
                                   AND p.pay_year = :pay_year
                                   AND p.pay_status != 3
                                 ORDER BY p.id_pay DESC");
    $stmt_today->execute([
        ':id_branch' => $id_branch,
        ':pay_day' => $today_day,
        ':pay_month' => $today_month,
        ':pay_year' => $today_year
    ]);
    $today_payments = $stmt_today->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summaries
    $today_count = count($today_payments);
    $today_revenue = 0;
    $today_cash = 0;
    $today_transfer = 0;
    foreach ($today_payments as $tp) {
        $today_revenue += $tp['pay_total'];
        // Use stored pay_cash / pay_transfer columns (accurate for all 3 types)
        $today_cash     += isset($tp['pay_cash'])     ? floatval($tp['pay_cash'])     : 0;
        $today_transfer += isset($tp['pay_transfer']) ? floatval($tp['pay_transfer']) : 0;
    }
} catch (\PDOException $e) {
    $today_payments = [];
    $today_count = 0;
    $today_revenue = 0;
    $today_cash = 0;
    $today_transfer = 0;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบการขาย - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Modal Overlay */
        .billing-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .billing-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        /* Modal Wrapper */
        .billing-modal-wrapper {
            background-color: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border);
            width: 95%;
            max-width: 980px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: var(--shadow-xl);
            animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            padding: 2.25rem;
            position: relative;
        }
        @keyframes modalSlideIn {
            from {
                transform: scale(0.9) translateY(20px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }
        .billing-modal-close {
            position: absolute;
            top: 1rem;
            right: 1.5rem;
            font-size: 2.25rem;
            color: var(--text-muted);
            border: none;
            background: none;
            cursor: pointer;
            transition: var(--transition);
            line-height: 1;
        }
        .billing-modal-close:hover {
            color: var(--text-main);
        }
        
        /* Modern Design System for Billing Entry Form */
        .modern-billing-card {
            background-color: var(--bg-card);
            border-radius: var(--radius-lg);
            border: 1px solid var(--border);
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
        }

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

        /* Modern Grid Layout for Customer Info */
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

        .form-col-4 { grid-column: span 4; }
        .form-col-8 { grid-column: span 8; }
        
        @media (max-width: 768px) {
            .form-col-4, .form-col-8 {
                grid-column: span 12;
            }
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

        .modern-service-table th.center, .modern-service-table td.center {
            text-align: center;
        }

        .modern-service-table td {
            padding: 10px 14px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
            color: var(--text-main);
            font-size: 0.925rem;
        }

        .modern-service-table tr:hover td {
            background-color: var(--bg-main);
        }

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

        /* Modern Inline inputs inside table cell */
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

        /* Modern actions buttons */
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
        }

        .btn-modern-cancel:hover {
            background-color: var(--border);
            color: var(--text-main);
        }

        .legacy-footer-info {
            text-align: center;
            margin-top: 1.5rem;
            font-size: 0.925rem;
            color: #495057;
            font-family: sans-serif;
            border-top: 1px dashed #cbd5e1;
            padding-top: 1.25rem;
        }

        /* ── Autocomplete Dropdown (body-level, position:fixed to escape modal overflow) ── */
        .autocomplete-list {
            position: fixed;
            background: var(--bg-card, #fff);
            border: 1px solid var(--primary, #2563eb);
            border-radius: 8px;
            box-shadow: 0 10px 32px rgba(0,0,0,0.18);
            z-index: 99999;
            max-height: 280px;
            overflow-y: auto;
            display: none;
        }
        .autocomplete-list.open { display: block; }
        .autocomplete-item {
            padding: 0.65rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--border);
            transition: background 0.15s;
        }
        .autocomplete-item:last-child { border-bottom: none; }
        .autocomplete-item:hover, .autocomplete-item.active {
            background-color: var(--primary-light);
        }
        .autocomplete-item-name {
            font-weight: 700;
            font-size: 0.95rem;
            color: var(--text-main);
        }
        .autocomplete-item-detail {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }
        .autocomplete-item-plate {
            display: inline-block;
            background: var(--primary-light);
            color: var(--primary);
            border-radius: 4px;
            padding: 1px 6px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-right: 4px;
        }
        .autocomplete-empty {
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">ระบบการขาย (หน้าจอขายและจัดการบิล)</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    สาขา: <strong style="color: var(--primary);"><?php echo $id_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'; ?></strong>
                </div>
            </div>
            
            <div class="content-body">
                <?php if (isset($db_error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>

                <!-- DAILY SALES SUMMARIES -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Card 1: Today Transactions -->
                    <div class="card" style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); color: #fff; padding: 1.5rem; border: none; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-lg); margin-bottom: 0;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin: 0; color: #fff;">📦 จำนวนรายการขายวันนี้</h4>
                            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">
                                <?php echo number_format($today_count); ?> <span style="font-size: 1rem; font-weight: 500;">รายการ</span>
                            </div>
                        </div>
                        <span style="font-size: 2.25rem; opacity: 0.8;">🧾</span>
                    </div>
                    
                    <!-- Card 2: Today Revenue -->
                    <div class="card" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); color: #fff; padding: 1.5rem; border: none; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-lg); margin-bottom: 0;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin: 0; color: #fff;">💰 ยอดขายรวมวันนี้</h4>
                            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">
                                <?php echo number_format($today_revenue); ?> <span style="font-size: 1rem; font-weight: 500;">บาท</span>
                            </div>
                        </div>
                        <span style="font-size: 2.25rem; opacity: 0.8;">💸</span>
                    </div>

                    <!-- Card 3: Today Cash -->
                    <div class="card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #fff; padding: 1.5rem; border: none; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-lg); margin-bottom: 0;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin: 0; color: #fff;">💵 ยอดเงินสดวันนี้</h4>
                            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">
                                <?php echo number_format($today_cash); ?> <span style="font-size: 1rem; font-weight: 500;">บาท</span>
                            </div>
                        </div>
                        <span style="font-size: 2.25rem; opacity: 0.8;">💵</span>
                    </div>

                    <!-- Card 4: Today Transfer -->
                    <div class="card" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #fff; padding: 1.5rem; border: none; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-lg); margin-bottom: 0;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin: 0; color: #fff;">📱 ยอดเงินโอนวันนี้</h4>
                            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.25rem;">
                                <?php echo number_format($today_transfer); ?> <span style="font-size: 1rem; font-weight: 500;">บาท</span>
                            </div>
                        </div>
                        <span style="font-size: 2.25rem; opacity: 0.8;">📱</span>
                    </div>
                </div>

                <!-- ADD SALES TRANSACTION BUTTON -->
                <div style="display: flex; justify-content: flex-end; margin-bottom: 1.5rem;">
                    <button type="button" class="btn btn-primary" onclick="openBillingModal()" style="font-size: 1rem; padding: 0.65rem 1.5rem; display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 600; border-radius: var(--radius-md); box-shadow: var(--shadow-sm); cursor: pointer;">
                        ➕ เพิ่มรายการขาย
                    </button>
                </div>

                <!-- DAILY TRANSACTIONS TABLE -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <h3 class="card-title">📋 รายการขายประจำวันนี้ (<?php echo date('d/m/') . (date('Y') + 543); ?>)</h3>
                    </div>
                    
                    <?php if (empty($today_payments)): ?>
                        <div style="text-align: center; padding: 4rem; color: var(--text-muted);">
                            <span style="font-size: 3rem;">📂</span>
                            <p style="margin-top: 1rem; font-weight: 500; font-size: 1rem;">ยังไม่มีรายการขายเกิดขึ้นในวันนี้</p>
                        </div>
                    <?php else: ?>
                        <div class="table-container">
                            <table class="table-modern" style="font-size: 0.9rem;">
                                <thead>
                                    <tr>
                                        <th style="width: 80px;" class="text-center">เลขบิล</th>
                                        <th style="width: 90px;" class="text-center">เวลา</th>
                                        <th>ชื่อลูกค้า</th>
                                        <th style="width: 140px;" class="text-center">ทะเบียนรถ</th>
                                        <th style="width: 130px;" class="text-right text-primary">ยอดสุทธิ</th>
                                        <th style="width: 120px;" class="text-center">การชำระเงิน</th>
                                        <th style="width: 150px;" class="text-center">สถานะ</th>
                                        <th style="width: 220px;" class="text-center no-print">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($today_payments as $tp): ?>
                                        <tr>
                                            <td class="text-center" style="font-weight: 600;">#<?php echo $tp['id_pay']; ?></td>
                                            <td class="text-center" style="color: var(--text-muted); font-size: 0.85rem;"><?php echo substr($tp['pay_time'], 0, 5); ?> น.</td>
                                            <td><strong><?php echo htmlspecialchars($tp['cus_name']); ?></strong></td>
                                            <td class="text-center"><?php echo htmlspecialchars($tp['car_regis_id'] . ' (' . $tp['PROVINCE_NAME'] . ')'); ?></td>
                                            <td class="text-right text-primary" style="font-weight: 600;"><?php echo number_format($tp['pay_total']); ?> บาท</td>
                                            <td class="text-center">
                                                <?php
                                                $pt = isset($tp['pay_type']) ? intval($tp['pay_type']) : 1;
                                                if ($pt == 2): ?>
                                                    <span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">📱 เงินโอน</span>
                                                <?php elseif ($pt == 3): ?>
                                                    <span class="badge" style="background-color: #d1fae5; color: #065f46; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">💵📱 สดและโอน</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #fef3c7; color: #d97706; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">💵 เงินสด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($tp['pay_status'] == 1): ?>
                                                    <span class="badge badge-warning" style="font-size: 0.75rem; padding: 3px 8px;">รอดำเนินการพิมพ์</span>
                                                <?php elseif ($tp['pay_status'] == 2): ?>
                                                    <span class="badge badge-success" style="font-size: 0.75rem; padding: 3px 8px;">พิมพ์บิลแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center no-print" style="display: flex; gap: 0.45rem; justify-content: center; align-items: center;">
                                                <!-- Print Receipt button -->
                                                <a href="receipt.php?id_pay=<?php echo $tp['id_pay']; ?>" class="btn btn-primary btn-sm" target="_blank" style="font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    🖨️ พิมพ์ใบเสร็จ
                                                </a>
                                                
                                                <!-- Edit button -->
                                                <?php if ($tp['pay_status'] == 1): ?>
                                                    <a href="edit_receipt.php?id_pay=<?php echo $tp['id_pay']; ?>" class="btn btn-secondary btn-sm" style="font-size: 11px; padding: 4px 10px; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                        ✏️ แก้ไข
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn btn-secondary btn-sm disabled" style="font-size: 11px; padding: 4px 10px; opacity: 0.5; cursor: not-allowed; display: inline-flex; align-items: center; gap: 0.25rem; background-color: var(--border); border-color: var(--border); color: var(--text-muted);" title="พิมพ์ใบเสร็จแล้ว ไม่สามารถแก้ไขได้">
                                                        🔒 ล็อกแล้ว
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- POPUP MODAL: Add Sales Transaction (Modernized Layout Form Style) -->
    <div class="billing-modal-overlay" id="billingModal">
        <div class="billing-modal-wrapper">
            <button type="button" class="billing-modal-close" onclick="closeBillingModal()">&times;</button>
            
            <h2 class="legacy-main-title" style="margin-bottom: 1.5rem;">เพิ่มรายการขายใหม่</h2>
            
            <form action="billing.php" method="POST" id="billingForm" onsubmit="return validateForm()">
                <?php echo csrf_field(); ?>
                <div class="modern-billing-card">
                    
                    <!-- Section 1: Customer Info -->
                    <div class="modern-section-title">
                        👤 ข้อมูลลูกค้าและพาหนะ
                    </div>
                    
                    <div class="modern-form-grid">
                        <div class="modern-form-group form-col-4">
                            <label for="car_regis_id" class="modern-label">ทะเบียนรถ <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="car_regis_id" id="car_regis_id" class="modern-input" required placeholder="พิมพ์ทะเบียน เช่น กข 1234 เพื่อดึงข้อมูลลูกค้าเดิม" autocomplete="off">
                        </div>
                        
                        <div class="modern-form-group form-col-4">
                            <label for="prov" class="modern-label">จังหวัดทะเบียนรถ <span style="color:var(--danger)">*</span></label>
                            <select name="prov" id="prov" class="modern-select" required>
                                <option value="">-- เลือกจังหวัด --</option>
                                <?php foreach ($provinces as $p): ?>
                                    <option value="<?php echo $p['PROVINCE_ID']; ?>"><?php echo htmlspecialchars($p['PROVINCE_NAME']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modern-form-group form-col-4">
                            <label for="car_type" class="modern-label">ประเภทรถยนต์ <span style="color:var(--danger)">*</span></label>
                            <select name="car_type" id="car_type" class="modern-select" required>
                                <option value="">-- เลือกประเภทรถ --</option>
                                <?php foreach ($car_types as $c): ?>
                                    <option value="<?php echo $c['id_car_type']; ?>"><?php echo htmlspecialchars($c['car_type_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="modern-form-group form-col-4">
                            <label for="name_cus" class="modern-label">ชื่อ - นามสกุลลูกค้า <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name_cus" id="name_cus" class="modern-input" required placeholder="หรือพิมพ์ชื่อเพื่อค้นหา เช่น นายสมชาย ใจดี" autocomplete="off">
                        </div>
                        
                        <div class="modern-form-group form-col-8">
                            <label for="addr_cus" class="modern-label">ที่อยู่ลูกค้า <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="addr_cus" id="addr_cus" class="modern-input" required placeholder="บ้านเลขที่ ถนน ตำบล อำเภอ จังหวัด">
                        </div>
                        
                        <div class="modern-form-group form-col-4">
                            <label for="phone_cus" class="modern-label">เบอร์โทรศัพท์ <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="phone_cus" id="phone_cus" class="modern-input" required placeholder="เช่น 0891234567">
                        </div>
                    </div>
                    
                    <!-- Section 2: Services Info -->
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
                                <td><input type="number" step="0.01" name="serv_1" id="serv_1" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">2</td>
                                <td>
                                    <div class="table-inline-flex">
                                        <span>ตรวจสภาพรถ (ตรอ.)</span>
                                        <input type="text" name="serv_2_1" id="serv_2_1" class="modern-table-input" style="width: 220px;" placeholder="เลขที่ ตรอ.">
                                    </div>
                                </td>
                                <td><input type="number" step="0.01" name="serv_2" id="serv_2" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">3</td>
                                <td>ต่อภาษีประจำปี (กรณีปกติ)</td>
                                <td><input type="number" step="0.01" name="serv_3" id="serv_3" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">4</td>
                                <td>
                                    <div class="table-inline-flex">
                                        <span>ต่อภาษีย้อนหลัง</span>
                                        <select name="serv_4_1" id="serv_4_1" class="modern-table-input" style="width: 80px;">
                                            <option value="0">---</option>
                                            <option value="1">1 ปี</option>
                                            <option value="2">2 ปี</option>
                                            <option value="3">3 ปี</option>
                                            <option value="4">4 ปี</option>
                                            <option value="5">5 ปี</option>
                                        </select>
                                        <span>ปี ตั้งแต่</span>
                                        <input type="text" name="serv_4_2" id="serv_4_2" class="modern-table-input" style="width: 100px;" placeholder="เช่น 2565">
                                    </div>
                                </td>
                                <td><input type="number" step="0.01" name="serv_4" id="serv_4" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">5</td>
                                <td>ค่าปรับล่าช้าภาษี</td>
                                <td><input type="number" step="0.01" name="serv_5" id="serv_5" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">6</td>
                                <td>ค่าสมุดเล่มทะเบียน</td>
                                <td><input type="number" step="0.01" name="serv_6" id="serv_6" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr>
                                <td class="center">7</td>
                                <td>
                                    <div class="table-inline-flex">
                                        <span>ค่าบริการอื่นๆ</span>
                                        <input type="text" name="serv_7_1" id="serv_7_1" class="modern-table-input" style="width: 250px;" placeholder="ระบุบริการอื่นๆ">
                                    </div>
                                </td>
                                <td><input type="number" step="0.01" name="serv_7" id="serv_7" class="calc-val" value="0.00"></td>
                            </tr>
                            <tr style="background-color: var(--bg-main); font-weight: 600;">
                                <td colspan="2" style="text-align: right; font-size: 0.95rem; border-top: 1px solid var(--border);">ยอดรวมเงินสะสม (Gross Total):</td>
                                <td style="text-align: right; font-size: 1.05rem; padding-right: 20px; color: var(--text-main); border-top: 1px solid var(--border);" id="grossTotal">0.00</td>
                            </tr>
                            <tr>
                                <td class="center">8</td>
                                <td style="text-align: right; font-weight: 500; font-size: 0.95rem; vertical-align: middle;">ส่วนลดร้านค้า (Discount):</td>
                                <td><input type="number" step="0.01" name="discount" id="discount" class="calc-val" value="0.00"></td>
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
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--primary); background-color: var(--primary-light); color: var(--primary); font-weight: 600; transition: all 0.2s ease;" id="pay_cash_label">
                                <input type="radio" name="pay_type" value="1" checked style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                💵 เงินสด
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--border); background-color: var(--bg-card); color: var(--text-muted); font-weight: 600; transition: all 0.2s ease;" id="pay_transfer_label">
                                <input type="radio" name="pay_type" value="2" style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                📱 เงินโอน
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem 1rem; border-radius: var(--radius-md); border: 2px solid var(--border); background-color: var(--bg-card); color: var(--text-muted); font-weight: 600; transition: all 0.2s ease;" id="pay_mixed_label">
                                <input type="radio" name="pay_type" value="3" style="accent-color: var(--primary); cursor: pointer;" onchange="updatePayTypeStyles()">
                                💵📱 ทั้งเงินสดและเงินโอน
                            </label>
                        </div>
                        <!-- Sub-fields for mixed payment (type 3) -->
                        <div id="mixed_payment_fields" style="display: none; margin-top: 1rem; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px dashed var(--primary); gap: 1.25rem; flex-wrap: wrap; align-items: flex-end;">
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">💵 ยอดชำระด้วยเงินสด (บาท) <span style="color:var(--danger)">*</span></label>
                                <input type="number" step="0.01" min="0" name="pay_cash" id="pay_cash_input"
                                    style="border: 1.5px solid var(--primary); border-radius: var(--radius-md); padding: 0.55rem 0.85rem; font-size: 0.95rem; background: var(--bg-card); color: var(--text-main); width: 180px;"
                                    placeholder="0.00" value="0" oninput="syncMixedTransfer()">
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">📱 ยอดชำระด้วยเงินโอน (บาท) <span style="color:var(--danger)">*</span></label>
                                <input type="number" step="0.01" min="0" name="pay_transfer" id="pay_transfer_input"
                                    style="border: 1.5px solid var(--primary); border-radius: var(--radius-md); padding: 0.55rem 0.85rem; font-size: 0.95rem; background: var(--bg-card); color: var(--text-main); width: 180px;"
                                    placeholder="0.00" value="0" oninput="syncMixedCash()">
                            </div>
                            <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                <label style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">ยอดรวม</label>
                                <div id="mixed_sum_display" style="font-size: 1rem; font-weight: 700; color: var(--primary); padding: 0.55rem 0;">0.00 บาท</div>
                            </div>
                            <div id="mixed_warn" style="display:none; font-size:0.82rem; color:var(--danger); font-weight:600; width:100%; margin-top:0.25rem;"></div>
                        </div>
                    </div>

                    <!-- Modern Action Buttons inside Card -->
                    <div class="modern-actions-row">
                        <button type="button" class="btn-modern-cancel" onclick="closeBillingModal()">ยกเลิก</button>
                        <button type="submit" class="btn-modern-submit">บันทึกรายการขาย</button>
                    </div>
                </div>
            </form>
            
            <!-- Footer Info inside Modal -->
            <div class="legacy-footer-info" style="border-top: none; margin-top: 1.5rem; padding-top: 0;">
                พนักงานขาย : <strong><?php echo htmlspecialchars($emp_name); ?></strong> &nbsp;&nbsp;&nbsp;&nbsp; สาขา : <strong><?php echo htmlspecialchars($id_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'); ?></strong>
            </div>
        </div>
    </div>

    <script>
        // Modal Controls
        function openBillingModal() {
            // Reset form fields
            document.getElementById('billingForm').reset();
            resetTotals();
            updatePayTypeStyles();
            
            const modal = document.getElementById('billingModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
                document.getElementById('car_regis_id').focus();
            }, 10);
        }

        function updatePayTypeStyles() {
            const payTypeChecked = document.querySelector('input[name="pay_type"]:checked');
            const payType = payTypeChecked ? payTypeChecked.value : '1';
            const cashLabel     = document.getElementById('pay_cash_label');
            const transferLabel = document.getElementById('pay_transfer_label');
            const mixedLabel    = document.getElementById('pay_mixed_label');
            const mixedFields   = document.getElementById('mixed_payment_fields');
            
            if (!cashLabel || !transferLabel) return;
            
            // Reset all labels
            [cashLabel, transferLabel, mixedLabel].forEach(l => {
                if (l) {
                    l.style.borderColor = 'var(--border)';
                    l.style.backgroundColor = 'var(--bg-card)';
                    l.style.color = 'var(--text-muted)';
                }
            });
            
            // Highlight selected label
            if (payType === '1') {
                cashLabel.style.borderColor = 'var(--primary)';
                cashLabel.style.backgroundColor = 'var(--primary-light)';
                cashLabel.style.color = 'var(--primary)';
            } else if (payType === '2') {
                transferLabel.style.borderColor = 'var(--primary)';
                transferLabel.style.backgroundColor = 'var(--primary-light)';
                transferLabel.style.color = 'var(--primary)';
            } else {
                if (mixedLabel) {
                    mixedLabel.style.borderColor = 'var(--primary)';
                    mixedLabel.style.backgroundColor = 'var(--primary-light)';
                    mixedLabel.style.color = 'var(--primary)';
                }
            }
            
            // Show/hide mixed sub-fields
            if (mixedFields) {
                if (payType === '3') {
                    mixedFields.style.display = 'flex';
                    updateMixedSum();
                } else {
                    mixedFields.style.display = 'none';
                }
            }
        }

        function closeBillingModal() {
            const modal = document.getElementById('billingModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
            // Close autocomplete dropdown if open
            if (typeof window.__closeBillingModalHook === 'function') {
                window.__closeBillingModalHook();
            }
        }

        // Close modal when clicking outside the modal wrapper
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('billingModal');
            if (event.target === modal) {
                closeBillingModal();
            }
        });

        // Real-time automatic calculations inside modal
        const inputs = document.querySelectorAll('.calc-val');
        const grossTotalEl = document.getElementById('grossTotal');
        const netTotalEl = document.getElementById('netTotal');
        
        inputs.forEach(input => {
            input.addEventListener('input', calculateTotals);
            // Select content on focus for easier typing
            input.addEventListener('focus', function() {
                this.select();
            });
            // Default blank to 0 on blur
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') this.value = '0.00';
            });
        });
        
        function calculateTotals() {
            let grossSum = 0;
            for (let i = 1; i <= 7; i++) {
                var el = document.getElementById('serv_' + i);
                grossSum += el ? (parseFloat(el.value) || 0) : 0;
            }
            var discountEl = document.getElementById('discount');
            var discount = discountEl ? (parseFloat(discountEl.value) || 0) : 0;
            var netSum = grossSum - discount;
            
            if (grossTotalEl) grossTotalEl.textContent = grossSum.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            if (netTotalEl)   netTotalEl.textContent   = netSum.toLocaleString('th-TH',   { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            
            // If mixed payment is selected, refresh the mixed sum display
            var pt = document.querySelector('input[name="pay_type"]:checked');
            if (pt && pt.value === '3' && typeof updateMixedSum === 'function') {
                updateMixedSum();
            }
        }
        
        function resetTotals() {
            setTimeout(() => {
                grossTotalEl.textContent = "0.00";
                netTotalEl.textContent = "0.00";
            }, 50);
        }
        
        function validateForm() {
            // Check customer validation
            if (document.getElementById('name_cus').value.trim() === "") {
                alert("กรุณากรอกชื่อลูกค้า");
                return false;
            }
            if (document.getElementById('addr_cus').value.trim() === "") {
                alert("กรุณากรอกที่อยู่ลูกค้า");
                return false;
            }
            if (document.getElementById('phone_cus').value.trim() === "") {
                alert("กรุณากรอกเบอร์โทรลูกค้า");
                return false;
            }
            if (document.getElementById('car_type').value === "") {
                alert("กรุณาเลือกประเภทรถ");
                return false;
            }
            if (document.getElementById('car_regis_id').value.trim() === "") {
                alert("กรุณากรอกเลขทะเบียนรถ");
                return false;
            }
            if (document.getElementById('prov').value === "") {
                alert("กรุณาเลือกจังหวัด");
                return false;
            }
            
            // If checking inspection cost, must provide inspection code
            const inspectionFee = parseFloat(document.getElementById('serv_2').value) || 0;
            if (inspectionFee > 0 && document.getElementById('serv_2_1').value.trim() === "") {
                alert("มีค่าบริการตรวจสภาพรถ กรุณากรอกเลขที่ ตรอ.");
                document.getElementById('serv_2_1').focus();
                return false;
            }
            
            // Validate type 3: mixed payment totals must equal net total
            const selectedPayType = document.querySelector('input[name="pay_type"]:checked');
            if (selectedPayType && selectedPayType.value === '3') {
                const netVal = parseFloat(netTotalEl.textContent.replace(/,/g, '')) || 0;
                const cInput = parseFloat(document.getElementById('pay_cash_input').value) || 0;
                const tInput = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
                if (cInput <= 0 && tInput <= 0) {
                    alert('กรุณากรอกยอดเงินสดและเงินโอนให้ครบ');
                    document.getElementById('pay_cash_input').focus();
                    return false;
                }
                if (Math.abs((cInput + tInput) - netVal) > 0.01) {
                    alert('ยอดเงินสด + เงินโอน (' + (cInput + tInput).toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท) ต้องเท่ากับยอดสุทธิ (' + netVal.toLocaleString('th-TH', {minimumFractionDigits:2}) + ' บาท)');
                    document.getElementById('pay_cash_input').focus();
                    return false;
                }
            }
            
            return true;
        }

        // Mixed payment helpers
        function updateMixedSum() {
            const netVal  = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            const cInput  = parseFloat(document.getElementById('pay_cash_input').value) || 0;
            const tInput  = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
            const sumEl   = document.getElementById('mixed_sum_display');
            const warnEl  = document.getElementById('mixed_warn');
            const total   = cInput + tInput;
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
            // Auto-fill transfer = net total - cash
            const netVal = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            const cInput = parseFloat(document.getElementById('pay_cash_input').value) || 0;
            const remaining = Math.max(0, netVal - cInput);
            document.getElementById('pay_transfer_input').value = remaining.toFixed(2);
            updateMixedSum();
        }

        function syncMixedCash() {
            // Auto-fill cash = net total - transfer
            const netVal = parseFloat((netTotalEl.textContent || '0').replace(/,/g, '')) || 0;
            const tInput = parseFloat(document.getElementById('pay_transfer_input').value) || 0;
            const remaining = Math.max(0, netVal - tInput);
            document.getElementById('pay_cash_input').value = remaining.toFixed(2);
            updateMixedSum();
        }



        /* ═══════════════════════════════════════════════
           AUTOCOMPLETE — Customer lookup by licence plate or name, then autofill
           (dropdown appended to <body> to escape modal overflow clipping)
           ═══════════════════════════════════════════════ */
        const autocompleteClosers = [];
        window.__closeBillingModalHook = () => autocompleteClosers.forEach(close => close());

        // searchBy: 'plate' or 'name'; nextFieldId: where the cursor goes after autofill
        function setupCustomerAutocomplete(inputId, searchBy, nextFieldId) {
            const input = document.getElementById(inputId);
            let currentIndex = -1;
            let debounceTimer;
            let lastResults  = [];

            // Create the dropdown element at <body> level to escape overflow clipping
            const listEl = document.createElement('div');
            listEl.className = 'autocomplete-list';
            document.body.appendChild(listEl);

            // Position the dropdown aligned to the input using fixed coords
            function positionList() {
                const rect = input.getBoundingClientRect();
                listEl.style.top   = (rect.bottom + 4) + 'px';
                listEl.style.left  = rect.left + 'px';
                listEl.style.width = Math.max(rect.width, 320) + 'px';
            }

            // ── Fetch suggestions from server ──
            function fetchSuggestions(q) {
                if (!q || q.length < 2) { closeList(); return; }
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetch('search_customer.php?by=' + searchBy + '&q=' + encodeURIComponent(q))
                        .then(r => r.json())
                        .then(data => {
                            lastResults  = Array.isArray(data) ? data : [];
                            currentIndex = -1;
                            renderList(lastResults);
                        })
                        .catch(() => closeList());
                }, 120);
            }

            // ── Render dropdown (plate search shows the plate first, name search the name first) ──
            function renderList(data) {
                listEl.innerHTML = '';
                if (!data || data.length === 0) { closeList(); return; }

                data.forEach((item) => {
                    const plate = '<span class="autocomplete-item-plate">' + escHtml(item.car_regis_id) + ' · ' + escHtml(item.PROVINCE_NAME) + '</span>';
                    const div = document.createElement('div');
                    div.className = 'autocomplete-item';
                    div.innerHTML = searchBy === 'plate'
                        ? '<div class="autocomplete-item-name">' + plate + escHtml(item.cus_name) + '</div>' +
                          '<div class="autocomplete-item-detail">' + escHtml(item.car_type_name) + ' &nbsp;|&nbsp; ' + escHtml(item.cus_phone) + '</div>'
                        : '<div class="autocomplete-item-name">' + escHtml(item.cus_name) + '</div>' +
                          '<div class="autocomplete-item-detail">' + plate + escHtml(item.car_type_name) + ' &nbsp;|&nbsp; ' + escHtml(item.cus_phone) + '</div>';
                    div.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        fillCustomer(item);
                    });
                    listEl.appendChild(div);
                });

                positionList();
                listEl.classList.add('open');
            }

            // ── Fill all customer fields ──
            function fillCustomer(item) {
                document.getElementById('name_cus').value     = item.cus_name;
                document.getElementById('addr_cus').value     = item.cus_addr;
                document.getElementById('phone_cus').value    = item.cus_phone;
                document.getElementById('car_regis_id').value = item.car_regis_id;

                // A value not offered in the dropdown leaves the select empty, so the clerk must pick one
                document.getElementById('car_type').value = item.id_car_type;
                document.getElementById('prov').value     = item.province_id;

                closeList();
                document.getElementById(nextFieldId).focus();
            }

            // ── Keyboard navigation ──
            input.addEventListener('keydown', (e) => {
                const items = listEl.querySelectorAll('.autocomplete-item');
                if (!listEl.classList.contains('open') || items.length === 0) return;

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    currentIndex = Math.min(currentIndex + 1, items.length - 1);
                    highlightItem(items);
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    currentIndex = Math.max(currentIndex - 1, 0);
                    highlightItem(items);
                } else if (e.key === 'Enter') {
                    if (currentIndex >= 0 && lastResults[currentIndex]) {
                        e.preventDefault();
                        fillCustomer(lastResults[currentIndex]);
                    }
                } else if (e.key === 'Escape') {
                    closeList();
                }
            });

            function highlightItem(items) {
                items.forEach((el, i) => el.classList.toggle('active', i === currentIndex));
                if (currentIndex >= 0) items[currentIndex].scrollIntoView({ block: 'nearest' });
            }

            input.addEventListener('input', () => fetchSuggestions(input.value.trim()));
            input.addEventListener('blur',  () => setTimeout(closeList, 150));
            input.addEventListener('focus', () => {
                if (input.value.trim().length >= 2) fetchSuggestions(input.value.trim());
            });

            // Re-position when modal is scrolled
            document.getElementById('billingModal').addEventListener('scroll', () => {
                if (listEl.classList.contains('open')) positionList();
            }, true);

            // Close if clicked outside
            document.addEventListener('mousedown', (e) => {
                if (e.target !== input && !listEl.contains(e.target)) closeList();
            });

            function closeList() {
                listEl.classList.remove('open');
                listEl.innerHTML = '';
                currentIndex = -1;
            }
            autocompleteClosers.push(closeList);
        }

        function escHtml(str) {
            if (!str) return '';
            return String(str)
                .replace(/&/g,'&amp;')
                .replace(/</g,'&lt;')
                .replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;');
        }

        setupCustomerAutocomplete('car_regis_id', 'plate', 'serv_1');
        setupCustomerAutocomplete('name_cus', 'name', 'addr_cus');

        // ── Auto-refresh when returning from receipt tab ──────────────────
        // receipt.php sets 'bovorn_receipt_printed' in localStorage when a
        // receipt is opened (pay_status is updated to 2 server-side at that point).
        // When this tab regains visibility, we reload so the edit button is locked.
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                try {
                    if (localStorage.getItem('bovorn_receipt_printed') === '1') {
                        localStorage.removeItem('bovorn_receipt_printed');
                        window.location.reload();
                    }
                } catch(e) {}
            }
        });
    </script>
</body>
</html>
