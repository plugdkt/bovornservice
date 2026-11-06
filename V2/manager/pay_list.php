<?php
// manager/pay_list.php
// Optimized Sales and Revenue Report Dashboard (Manager View) with Pagination, Popups & Quick Filters
date_default_timezone_set('Asia/Bangkok');
require("con_db.php");
require("../daily_closing_lib.php");

// Search Filters - Supports GET request to preserve pagination query parameters
$search_active = isset($_GET['search_active']) ? intval($_GET['search_active']) : 0;
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'today';

if ($filter_type === 'today') {
    $day = intval(date('d'));
    $day2 = 0;
    $month = intval(date('m'));
    $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'month') {
    $day = 0;
    $day2 = 0;
    $month = intval(date('m'));
    $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'year') {
    $day = 0;
    $day2 = 0;
    $month = 0;
    $year = intval(date('Y')) + 543;
} else { // custom
    $day = isset($_GET['day']) ? intval($_GET['day']) : 0;
    $day2 = isset($_GET['day2']) ? intval($_GET['day2']) : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $year = isset($_GET['year']) ? intval($_GET['year']) : (date('Y') + 543);
}

$branch_id = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;

$payments = [];
$total_records = 0;
$total_pages = 0;
$page = 1;
$limit = 20;

$total_records_dkt = 0; $sum_1_dkt = 0; $sum_2_dkt = 0; $sum_3_dkt = 0; $sum_4_dkt = 0; $sum_5_dkt = 0; $sum_6_dkt = 0; $sum_7_dkt = 0; $sum_disc_dkt = 0; $total_dkt = 0; $cash_dkt = 0; $transfer_dkt = 0;
$total_records_twt = 0; $sum_1_twt = 0; $sum_2_twt = 0; $sum_3_twt = 0; $sum_4_twt = 0; $sum_5_twt = 0; $sum_6_twt = 0; $sum_7_twt = 0; $sum_disc_twt = 0; $total_twt = 0; $cash_twt = 0; $transfer_twt = 0;

$sum_1 = $sum_2 = $sum_3 = $sum_4 = $sum_5 = $sum_6 = $sum_7 = $sum_disc = 0;
$total_sum = $total_cash = $total_transfer = 0;

$is_daily = false;
$p_day = 0; $p_month = 0; $p_year = 0;

$tax_car_count_dkt = 0; $tax_motorcycle_count_dkt = 0; $tax_total_sum_dkt = 0; $tax_total_fees_dkt = 0; $tax_grand_total_dkt = 0;
$tax_car_count_twt = 0; $tax_motorcycle_count_twt = 0; $tax_total_sum_twt = 0; $tax_total_fees_twt = 0; $tax_grand_total_twt = 0;

$tax_car_count = 0;
$tax_motorcycle_count = 0;
$tax_total_sum = 0;
$tax_total_fees = 0;
$tax_grand_total = 0;
$tax_car_fees_dkt = 0;
$tax_motorcycle_fees_dkt = 0;
$tax_car_fees_twt = 0;
$tax_motorcycle_fees_twt = 0;
$tax_car_fees = 0;
$tax_motorcycle_fees = 0;
$net_total = 0;
$net_total_dkt = 0;
$net_total_twt = 0;
$car_rate = 10;
$motorcycle_rate = 5;

if ($day > 0 || $month > 0 || $year > 0) {
    try {
        $conditions_base = [];
        $params = [];
        
        if ($day > 0 && $day2 == 0) {
            $conditions_base[] = "p.pay_day = :day";
            $params[':day'] = $day;
        } elseif ($day > 0 && $day2 > 0) {
            $conditions_base[] = "p.pay_day >= :day AND p.pay_day <= :day2";
            $params[':day'] = $day;
            $params[':day2'] = $day2;
        }
        
        if ($month > 0) {
            $conditions_base[] = "p.pay_month = :month";
            $params[':month'] = $month;
        }
        
        if ($year > 0) {
            $conditions_base[] = "p.pay_year = :year";
            $params[':year'] = $year - 543;
        }
        
        if ($branch_id > 0) {
            $conditions_base[] = "p.id_branch = :branch_id";
            $params[':branch_id'] = $branch_id;
        }
        
        $conditions_sums = array_merge(["p.pay_status != 3"], $conditions_base);
        $where_sql = implode(" AND ", $conditions_sums);
        $where_list_sql = !empty($conditions_base) ? implode(" AND ", $conditions_base) : "1=1";
        
        // 1. Calculate overall sums and branch-specific sums directly via database aggregation (Highly optimized)
        $sql_sums = "SELECT p.id_branch, 
                            COUNT(*) as branch_records,
                            SUM(p.serv_1) as branch_serv_1, 
                            SUM(p.serv_2) as branch_serv_2, 
                            SUM(p.serv_3) as branch_serv_3, 
                            SUM(p.serv_4) as branch_serv_4, 
                            SUM(p.serv_5) as branch_serv_5, 
                            SUM(p.serv_6) as branch_serv_6, 
                            SUM(p.serv_7) as branch_serv_7, 
                            SUM(p.discount) as branch_discount, 
                            SUM(p.pay_total) as branch_total,
                            SUM(COALESCE(p.pay_cash, 0)) as branch_cash,
                            SUM(COALESCE(p.pay_transfer, 0)) as branch_transfer
                     FROM `payment` p 
                     WHERE $where_sql 
                     GROUP BY p.id_branch";
        $stmt_sums = $pdo->prepare($sql_sums);
        $stmt_sums->execute($params);
        $branch_sums = $stmt_sums->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($branch_sums as $bs) {
            if ($bs['id_branch'] == 1) {
                $total_records_dkt = intval($bs['branch_records']);
                $sum_1_dkt = floatval($bs['branch_serv_1']);
                $sum_2_dkt = floatval($bs['branch_serv_2']);
                $sum_3_dkt = floatval($bs['branch_serv_3']);
                $sum_4_dkt = floatval($bs['branch_serv_4']);
                $sum_5_dkt = floatval($bs['branch_serv_5']);
                $sum_6_dkt = floatval($bs['branch_serv_6']);
                $sum_7_dkt = floatval($bs['branch_serv_7']);
                $sum_disc_dkt = floatval($bs['branch_discount']);
                $total_dkt = floatval($bs['branch_total']);
                $cash_dkt = floatval($bs['branch_cash']);
                $transfer_dkt = floatval($bs['branch_transfer']);
            } elseif ($bs['id_branch'] == 2) {
                $total_records_twt = intval($bs['branch_records']);
                $sum_1_twt = floatval($bs['branch_serv_1']);
                $sum_2_twt = floatval($bs['branch_serv_2']);
                $sum_3_twt = floatval($bs['branch_serv_3']);
                $sum_4_twt = floatval($bs['branch_serv_4']);
                $sum_5_twt = floatval($bs['branch_serv_5']);
                $sum_6_twt = floatval($bs['branch_serv_6']);
                $sum_7_twt = floatval($bs['branch_serv_7']);
                $sum_disc_twt = floatval($bs['branch_discount']);
                $total_twt = floatval($bs['branch_total']);
                $cash_twt = floatval($bs['branch_cash']);
                $transfer_twt = floatval($bs['branch_transfer']);
            }
        }
        $sum_1 = $sum_1_dkt + $sum_1_twt;
        $sum_2 = $sum_2_dkt + $sum_2_twt;
        $sum_3 = $sum_3_dkt + $sum_3_twt;
        $sum_4 = $sum_4_dkt + $sum_4_twt;
        $sum_5 = $sum_5_dkt + $sum_5_twt;
        $sum_6 = $sum_6_dkt + $sum_6_twt;
        $sum_7 = $sum_7_dkt + $sum_7_twt;
        $sum_disc = $sum_disc_dkt + $sum_disc_twt;
        $total_sum = $total_dkt + $total_twt;
        $total_cash = $cash_dkt + $cash_twt;
        $total_transfer = $transfer_dkt + $transfer_twt;
        
        // 2. Count total records for pagination
        $sql_count = "SELECT COUNT(*) FROM `payment` p WHERE $where_list_sql";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_records = intval($stmt_count->fetchColumn());
        
        // 3. Pagination calculation
        $total_pages = ceil($total_records / $limit);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        $offset = ($page - 1) * $limit;
        
        // 4. Fetch the paginated dataset (Latest transactions first)
        $sql = "SELECT p.*, b.branch_name, c.car_type_name, pr.PROVINCE_NAME as province_name 
                FROM `payment` p
                JOIN `branch` b ON p.id_branch = b.id_branch
                JOIN `car_type` c ON p.id_car_type = c.id_car_type
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE $where_list_sql
                ORDER BY p.id_pay DESC 
                LIMIT :limit OFFSET :offset";
                
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate tax referral summary for the selected period (overall & branch-specific)
        $tax_params = $params;
        $tax_conditions = $conditions_sums;
        $tax_conditions[] = "(p.serv_3 > 0 OR p.serv_4 > 0 OR p.serv_5 > 0 OR p.serv_6 > 0)";
        $tax_where = implode(" AND ", $tax_conditions);
        
        $sql_tax_summary = "SELECT p.id_car_type, p.id_branch, COUNT(*) as count_rows, 
                                   SUM(p.serv_3) as sum_serv3,
                                   SUM(p.serv_4) as sum_serv4,
                                   SUM(p.serv_5) as sum_serv5,
                                   SUM(p.serv_6) as sum_serv6
                            FROM `payment` p
                            WHERE $tax_where
                            GROUP BY p.id_car_type, p.id_branch";
                            
        $stmt_tax = $pdo->prepare($sql_tax_summary);
        $stmt_tax->execute($tax_params);
        $tax_rows = $stmt_tax->fetchAll(PDO::FETCH_ASSOC);
        
        $car_rate = floatval(get_system_setting('tax_car_fee', 10));
        $motorcycle_rate = floatval(get_system_setting('tax_motorcycle_fee', 5));
        
        foreach ($tax_rows as $tr) {
            $row_sum = floatval($tr['sum_serv3']) + floatval($tr['sum_serv4']) + floatval($tr['sum_serv5']) + floatval($tr['sum_serv6']);
            
            if ($tr['id_branch'] == 1) {
                $tax_total_sum_dkt += $row_sum;
                if (intval($tr['id_car_type']) == 4) {
                    $tax_motorcycle_count_dkt += intval($tr['count_rows']);
                } else {
                    $tax_car_count_dkt += intval($tr['count_rows']);
                }
            } elseif ($tr['id_branch'] == 2) {
                $tax_total_sum_twt += $row_sum;
                if (intval($tr['id_car_type']) == 4) {
                    $tax_motorcycle_count_twt += intval($tr['count_rows']);
                } else {
                    $tax_car_count_twt += intval($tr['count_rows']);
                }
            }
        }
        
        $tax_car_fees_dkt = $tax_car_count_dkt * $car_rate;
        $tax_motorcycle_fees_dkt = $tax_motorcycle_count_dkt * $motorcycle_rate;
        $tax_total_fees_dkt = $tax_car_fees_dkt + $tax_motorcycle_fees_dkt;
        $tax_grand_total_dkt = $tax_total_sum_dkt + $tax_total_fees_dkt;
        
        $tax_car_fees_twt = $tax_car_count_twt * $car_rate;
        $tax_motorcycle_fees_twt = $tax_motorcycle_count_twt * $motorcycle_rate;
        $tax_total_fees_twt = $tax_car_fees_twt + $tax_motorcycle_fees_twt;
        $tax_grand_total_twt = $tax_total_sum_twt + $tax_total_fees_twt;
        
        $tax_car_count = $tax_car_count_dkt + $tax_car_count_twt;
        $tax_motorcycle_count = $tax_motorcycle_count_dkt + $tax_motorcycle_count_twt;
        $tax_total_sum = $tax_total_sum_dkt + $tax_total_sum_twt;
        $tax_total_fees = $tax_total_fees_dkt + $tax_total_fees_twt;
        $tax_grand_total = $tax_grand_total_dkt + $tax_grand_total_twt;
        
        $tax_car_fees = $tax_car_fees_dkt + $tax_car_fees_twt;
        $tax_motorcycle_fees = $tax_motorcycle_fees_dkt + $tax_motorcycle_fees_twt;
        $net_total = $total_sum - $tax_grand_total;
        $net_total_dkt = $total_dkt - $tax_grand_total_dkt;
        $net_total_twt = $total_twt - $tax_grand_total_twt;
        
        // Calculate if query is daily (for the PDF printing option / details box)
        if ($filter_type === 'today') {
            $is_daily = true;
            $p_day = intval(date('d'));
            $p_month = intval(date('m'));
            $p_year = intval(date('Y')) + 543;
        } elseif ($filter_type === 'custom' && $day > 0 && $day2 == 0) {
            $is_daily = true;
            $p_day = $day;
            $p_month = $month > 0 ? $month : intval(date('m'));
            $p_year = $year > 0 ? $year : (intval(date('Y')) + 543);
        }
        
    } catch (\PDOException $e) {
        $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    }
}

// ── Daily closing summary per branch (สรุปส่งยอดรายวัน) — same figures as daily_closing.php ──
$closing_boxes = [];
if ($is_daily) {
    $closing_year_ad = $p_year > 2500 ? $p_year - 543 : $p_year;
    foreach ($branch_id > 0 ? [$branch_id] : [1, 2] as $cb_branch) {
        try {
            $closing_boxes[$cb_branch] = daily_closing_totals($pdo, $p_day, $p_month, $closing_year_ad, $cb_branch);
        } catch (\PDOException $e) {
            error_log("Daily closing summary error: " . $e->getMessage());
            $closing_boxes[$cb_branch] = null;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานยอดขายรวม - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: var(--danger); font-weight: 600; }
        .text-primary { color: var(--primary); font-weight: 600; }

        /* Print Tax Referral Button */
        .btn-print-tax {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.45rem 1.1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            transition: var(--transition);
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
            font-family: inherit;
        }
        .btn-print-tax:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.3);
            background: linear-gradient(135deg, #059669, #047857);
            color: #fff !important;
        }

        /* Pagination Styles */
        .btn-page {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.375rem 0.75rem;
            min-width: 32px;
            height: 32px;
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-main);
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
        }
        .btn-page:hover {
            border-color: var(--primary);
            color: var(--primary);
            background-color: var(--primary-light);
        }
        .btn-page.active {
            background-color: var(--primary);
            border-color: var(--primary);
            color: var(--text-inverse);
        }
        .btn-page.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .pagination-dots {
            color: var(--text-muted);
            font-weight: 600;
            padding: 0 0.25rem;
            align-self: center;
        }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        /* Modal Container */
        .modal-container {
            background-color: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border);
            width: 95%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            padding: 1.75rem;
            text-align: left;
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
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }
        .modal-close-btn:hover {
            color: var(--text-main);
        }
        /* Details Grid */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .details-section-title {
            grid-column: span 2;
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--primary);
            border-bottom: 1px dashed var(--border);
            padding-bottom: 0.25rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }
        .details-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .details-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            font-weight: 500;
        }
        .details-value {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-main);
        }
        .details-value.total {
            font-size: 1.25rem;
            color: var(--primary);
            font-weight: 800;
        }

        /* Modal Responsiveness */
        @media (max-width: 576px) {
            .details-grid {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }
            .details-section-title {
                grid-column: span 1;
            }
            .details-item[style*="grid-column: span 2"] {
                grid-column: span 1;
            }
            .modal-container {
                padding: 1.25rem;
                width: 95%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">รายงานการเข้าใช้บริการ (ผู้จัดการ)</div>
                <div style="font-size: 0.875rem; color: var(--text-muted);">
                    บทบาท: <strong style="color: var(--primary);">ผู้จัดการระบบ</strong>
                </div>
            </div>
            
            <div class="content-body">
                <?php if (isset($_GET['cancel']) && $_GET['cancel'] === 'success'): ?>
                    <div class="badge badge-success" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        ยกเลิกใบเสร็จรับเงินเรียบร้อยแล้ว
                    </div>
                <?php endif; ?>
                
                <?php if (isset($db_error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔍 ค้นหายอดขายสาขา</h3>
                    </div>
                    
                    <form action="pay_list.php" method="GET">
                        <input type="hidden" name="search_active" value="1">
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            
                            <!-- Quick Filter Dropdown -->
                            <div class="form-group" style="margin-bottom: 0; min-width: 160px;">
                                <label for="filter_type" class="form-label">เลือกช่วงเวลา</label>
                                <select name="filter_type" id="filter_type" class="form-control" onchange="handleFilterTypeChange()">
                                    <option value="today" <?php echo $filter_type === 'today' ? 'selected' : ''; ?>>วันนี้ (Today)</option>
                                    <option value="month" <?php echo $filter_type === 'month' ? 'selected' : ''; ?>>เดือนนี้ (Current Month)</option>
                                    <option value="year" <?php echo $filter_type === 'year' ? 'selected' : ''; ?>>ปีนี้ (Current Year)</option>
                                    <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>ระบุวันที่เอง (Custom)</option>
                                </select>
                            </div>

                            <!-- Branch Filter -->
                            <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
                                <label for="branch_id" class="form-label">เลือกสาขา</label>
                                <select name="branch_id" id="branch_id" class="form-control" onchange="this.form.submit()">
                                    <option value="0" <?php echo $branch_id == 0 ? 'selected' : ''; ?>>ทั้งหมด (ทุกสาขา)</option>
                                    <option value="1" <?php echo $branch_id == 1 ? 'selected' : ''; ?>>สาขาดอกคำใต้</option>
                                    <option value="2" <?php echo $branch_id == 2 ? 'selected' : ''; ?>>สาขาท่าวังทอง</option>
                                </select>
                            </div>

                            <!-- Custom Date Selectors (Conditionally visible via JavaScript) -->
                            <div id="customDateFields" style="display: <?php echo $filter_type === 'custom' ? 'flex' : 'none'; ?>; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0; min-width: 110px;">
                                    <label for="day" class="form-label">เริ่มวันที่</label>
                                    <select name="day" id="day" class="form-control">
                                        <option value="0">- วัน -</option>
                                        <?php for ($i = 1; $i <= 31; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $day == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0; min-width: 110px;">
                                    <label for="day2" class="form-label">ถึงวันที่</label>
                                    <select name="day2" id="day2" class="form-control">
                                        <option value="0">- วัน -</option>
                                        <?php for ($i = 1; $i <= 31; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $day2 == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0; min-width: 130px;">
                                    <label for="month" class="form-label">เดือน</label>
                                    <select name="month" id="month" class="form-control">
                                        <option value="0" <?php echo $month == 0 ? 'selected' : ''; ?>>- ทั้งหมด -</option>
                                        <?php
                                        $months_th = [
                                            1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 
                                            5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม", 
                                            9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
                                        ];
                                        foreach ($months_th as $num => $name):
                                        ?>
                                            <option value="<?php echo $num; ?>" <?php echo $month == $num ? 'selected' : ''; ?>><?php echo $name; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0; min-width: 110px;">
                                    <label for="year" class="form-label">ปี พ.ศ.</label>
                                    <select name="year" id="year" class="form-control">
                                        <option value="0">- ปี -</option>
                                        <?php
                                        $current_year_th = date('Y') + 543;
                                        for ($y = $current_year_th; $y >= 2557; $y--):
                                        ?>
                                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary" id="searchBtn" style="height: 38px; display: <?php echo $filter_type === 'custom' ? 'inline-flex' : 'none'; ?>;">🔍 ค้นหา</button>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($payments)): ?>
                    <div class="card text-center" style="padding: 3rem;">
                        <span style="font-size: 3rem;">📂</span>
                        <p style="margin-top: 1rem; color: var(--text-muted); font-weight: 500;">ไม่พบธุรกรรมการขายในช่วงเวลาที่ค้นหา</p>
                    </div>
                <?php else: ?>
                    
                    <!-- 1. KEY FINANCIAL SUMMARY (TOP 4 KPI CARDS) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                        <?php
                        $kpis = [
                            ['💼', 'จำนวนรายการ',        number_format($total_records), 'รายการ', '#eff6ff', '#2563eb', $branch_id == 0 ? '(ดก: ' . number_format($total_records_dkt) . ' | ทว: ' . number_format($total_records_twt) . ')' : ''],
                            ['💰', 'ยอดรวมสุทธิ (รับ)',         number_format($total_sum),     'บาท',    'linear-gradient(135deg, #1e3a5f, #2d5a8e)', '#fff',    $branch_id == 0 ? '(ดก: ' . number_format($total_dkt) . ' | ทว: ' . number_format($total_twt) . ')' : ''],
                            ['💸', 'ยอดส่งต่อภาษี (จ่าย)', number_format($tax_grand_total), 'บาท',    '#fff5f5', '#e53e3e', $branch_id == 0 ? '(ดก: ' . number_format($tax_grand_total_dkt) . ' | ทว: ' . number_format($tax_grand_total_twt) . ')' : ''],
                            ['🟢', 'ยอดคงเหลือสุทธิ (จริง)', number_format($net_total),       'บาท',    'linear-gradient(135deg, #10b981, #059669)', '#fff',    $branch_id == 0 ? '(ดก: ' . number_format($net_total_dkt) . ' | ทว: ' . number_format($net_total_twt) . ')' : ''],
                        ];
                        foreach ($kpis as $k):
                            $is_gradient_green = ($k[0] === '🟢');
                            $is_gradient_blue = ($k[0] === '💰');
                            $is_gradient = $is_gradient_green || $is_gradient_blue;
                            
                            $card_bg = 'var(--bg-card)';
                            $card_border = 'var(--border)';
                            if ($is_gradient_green) {
                                $card_bg = 'linear-gradient(135deg, #10b981, #059669)';
                                $card_border = '#10b981';
                            } elseif ($is_gradient_blue) {
                                $card_bg = 'linear-gradient(135deg, #1e3a5f, #2d5a8e)';
                                $card_border = '#1e3a5f';
                            }
                        ?>
                        <div class="card" style="padding: 1.25rem 1.4rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 0; transition: var(--transition); background: <?php echo $card_bg; ?>; border-color: <?php echo $card_border; ?>;">
                            <div style="background: <?php echo $is_gradient ? 'rgba(255,255,255,0.15)' : $k[4]; ?>; color: <?php echo $is_gradient ? '#fff' : $k[5]; ?>; width: 46px; height: 46px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0;">
                                <?php echo $k[0] === '🟢' ? '💰' : $k[0]; ?>
                            </div>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-size: 0.775rem; font-weight: 600; color: <?php echo $is_gradient ? 'rgba(255,255,255,0.75)' : 'var(--text-muted)'; ?>; margin-bottom: 3px; white-space: nowrap;"><?php echo $k[1]; ?></div>
                                <div style="font-size: 1.4rem; font-weight: 800; color: <?php echo $is_gradient ? '#fff' : 'var(--text-main)'; ?>; line-height: 1.2;">
                                    <?php echo $k[2]; ?><span style="font-size: 0.825rem; font-weight: 500; color: <?php echo $is_gradient ? 'rgba(255,255,255,0.7)' : 'var(--text-muted)'; ?>; margin-left: 2px;"><?php echo $k[3]; ?></span>
                                </div>
                                <?php if (!empty($k[6])): ?>
                                    <div style="font-size: 0.725rem; color: <?php echo $is_gradient ? 'rgba(255,255,255,0.6)' : 'var(--text-muted)'; ?>; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo $k[6]; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 2. DETAILED SUMMARY (2 GROUPED DETAIL CARDS SIDE-BY-SIDE) -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                        
                        <!-- LEFT CARD: Payment Methods & Discounts -->
                        <div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 0;">
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">
                                <span>💳</span> ช่องทางการชำระเงิน & ส่วนลด
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                
                                <!-- Cash -->
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border); padding-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #fef3c7; color: #d97706; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">💵</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ยอดเงินสด</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($cash_dkt); ?> | ทว: <?php echo number_format($cash_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($total_cash); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                                <!-- Transfer -->
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border); padding-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #e0e7ff; color: #4338ca; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">📱</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ยอดเงินโอน</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($transfer_dkt); ?> | ทว: <?php echo number_format($transfer_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($total_transfer); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                                <!-- Discount -->
                                <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #fff1f2; color: #e11d48; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">🏷️</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ส่วนลดรวม</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($sum_disc_dkt); ?> | ทว: <?php echo number_format($sum_disc_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--danger);"><?php echo number_format($sum_disc); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                            </div>
                        </div>
                        
                        <!-- RIGHT CARD: Revenue Breakdown by Service Type -->
                        <div class="card" style="padding: 1.25rem 1.5rem; margin-bottom: 0;">
                            <h3 style="font-size: 0.95rem; font-weight: 700; color: var(--text-main); margin-top: 0; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">
                                <span>🛠️</span> แยกประเภทรายรับค่าบริการ
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                
                                <!-- PRB -->
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border); padding-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #f0fdf4; color: #16a34a; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">📋</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ค่า พ.ร.บ.</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($sum_1_dkt); ?> | ทว: <?php echo number_format($sum_1_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($sum_1); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                                <!-- Inspection (TRO) -->
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border); padding-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #fff7ed; color: #ea580c; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">🔍</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ค่าตรวจสภาพ (ตรอ.)</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($sum_2_dkt); ?> | ทว: <?php echo number_format($sum_2_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($sum_2); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                                <!-- Annual Tax (Regular + Back Tax) -->
                                <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px dashed var(--border); padding-bottom: 0.75rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #fdf4ff; color: #9333ea; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">🏛️</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ค่าภาษีประจำปี (รวม)</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($sum_3_dkt+$sum_4_dkt); ?> | ทว: <?php echo number_format($sum_3_twt+$sum_4_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($sum_3+$sum_4); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                                <!-- Fines + Book Fees -->
                                <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.25rem;">
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <div style="background: #fefce8; color: #ca8a04; width: 36px; height: 36px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">⚠️</div>
                                        <div>
                                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-main);">ค่าปรับ + ค่าเล่มทะเบียน</div>
                                            <?php if ($branch_id == 0): ?>
                                                <div style="font-size: 0.7rem; color: var(--text-muted);">ดก: <?php echo number_format($sum_5_dkt+$sum_6_dkt); ?> | ทว: <?php echo number_format($sum_5_twt+$sum_6_twt); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div style="font-size: 1.05rem; font-weight: 700; color: var(--text-main);"><?php echo number_format($sum_5+$sum_6); ?> <span style="font-size: 0.775rem; font-weight: 500; color: var(--text-muted);">บาท</span></div>
                                </div>
                                
                            </div>
                        </div>
                        
                    </div>

                    <!-- ── DAILY TAX REFERRAL SUMMARY (MANAGER VIEW) ── -->
                    <?php if ($is_daily): ?>
                        <div class="card" style="border: 1px solid #10b981; background-color: #f0fdf4; margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 700; color: #065f46; font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <span>📋</span> สรุปยอดส่งต่อภาษีรายวัน (สาขา: <?php echo $branch_id == 0 ? 'ทุกสาขา' : ($branch_id == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'); ?>)
                                </div>
                                <button onclick="openTaxReferralPopup('../print_tax_referral.php?day=<?php echo $p_day; ?>&month=<?php echo $p_month; ?>&year=<?php echo $p_year; ?>&id_branch=<?php echo $branch_id > 0 ? $branch_id : 1; ?>')" 
                                   class="btn-print-tax" style="padding: 0.35rem 0.95rem; font-size: 0.8rem;">
                                    🖨️ สั่งพิมพ์ใบส่งต่อภาษีรายวัน
                                </button>
                            </div>
                            
                            <?php if ($tax_car_count > 0 || $tax_motorcycle_count > 0): ?>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                                    <!-- Volume and Fee card -->
                                    <div style="background: #ffffff; border: 1px solid #d1fae5; padding: 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                                        <div style="font-weight: 700; font-size: 0.8rem; color: #047857; text-transform: uppercase; margin-bottom: 0.75rem; border-bottom: 1px dashed #a7f3d0; padding-bottom: 0.25rem;">
                                            🚗 จำนวนรถและค่าธรรมเนียมส่งต่อ
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-main);">
                                            <span>รถยนต์ (<?php echo $car_rate; ?> บ./คัน):</span>
                                            <strong><?php echo $tax_car_count; ?> คัน (<?php echo number_format($tax_car_fees); ?> บาท)</strong>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-main);">
                                            <span>รถจักรยานยนต์ (<?php echo $motorcycle_rate; ?> บ./คัน):</span>
                                            <strong><?php echo $tax_motorcycle_count; ?> คัน (<?php echo number_format($tax_motorcycle_fees); ?> บาท)</strong>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem; margin-top: 0.5rem; font-weight: 700; font-size: 0.9rem; color: #065f46;">
                                            <span>รวมค่าธรรมเนียมส่งต่อ:</span>
                                            <span><?php echo number_format($tax_total_fees); ?> บาท</span>
                                        </div>
                                    </div>

                                    <!-- Total costs card -->
                                    <div style="background: #ffffff; border: 1px solid #d1fae5; padding: 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                                        <div style="font-weight: 700; font-size: 0.8rem; color: #047857; text-transform: uppercase; margin-bottom: 0.75rem; border-bottom: 1px dashed #a7f3d0; padding-bottom: 0.25rem;">
                                            💰 สรุปรายจ่ายการส่งต่อภาษี
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-main);">
                                            <span>ยอดเงินภาษีรวม (ปกติ+ล่าช้า+ปรับ+เล่ม):</span>
                                            <strong><?php echo number_format($tax_total_sum); ?> บาท</strong>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.85rem; color: var(--text-main);">
                                            <span>ค่าธรรมเนียมบริการส่งต่อ:</span>
                                            <strong><?php echo number_format($tax_total_fees); ?> บาท</strong>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 0.5rem; margin-top: 0.5rem; font-weight: 800; font-size: 1.05rem; color: #047857;">
                                            <span>รวมส่งเงินทั้งสิ้น (ภาษี + ค่าธรรมเนียม):</span>
                                            <span><?php echo number_format($tax_grand_total); ?> บาท</span>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.85rem; color: #64748b; text-align: center; padding: 0.5rem 0;">
                                    ℹ️ ไม่มีรายการส่งต่อภาษี (ภาษีปกติ, ภาษีย้อนหลัง, ค่าปรับ, หรือค่าเล่มเป็น 0 ทั้งหมด) สำหรับวันที่เลือก
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- ── DAILY CLOSING SUMMARY PER BRANCH (สรุปส่งยอดรายวัน) ── -->
                    <?php if ($is_daily && !empty($closing_boxes)): ?>
                        <div class="card" style="border: 1px solid #93c5fd; background-color: #eff6ff; margin-bottom: 1.5rem; padding: 1.25rem 1.5rem;">
                            <div style="font-weight: 700; color: #1e3a8a; font-size: 1rem; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span>📒</span> สรุปส่งยอดรายวัน
                                <span style="font-weight: 500; font-size: 0.8rem; color: #64748b;">(ยังไม่หักรายจ่ายอื่นๆ — กรอกได้ในรายงานเต็ม)</span>
                            </div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                                <?php foreach ($closing_boxes as $cb_branch => $cb): ?>
                                    <div style="background: #ffffff; border: 1px solid #bfdbfe; padding: 1rem; border-radius: var(--radius-md); box-shadow: var(--shadow-sm);">
                                        <div style="font-weight: 700; font-size: 0.85rem; color: #1d4ed8; margin-bottom: 0.75rem; border-bottom: 1px dashed #bfdbfe; padding-bottom: 0.25rem;">
                                            🏢 สาขา<?php echo $cb_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'; ?> <?php echo $cb ? '(' . number_format($cb['bills']) . ' บิล)' : ''; ?>
                                        </div>
                                        <?php if ($cb === null): ?>
                                            <div style="font-size: 0.85rem; color: #b91c1c;">ดึงข้อมูลไม่สำเร็จ</div>
                                        <?php else: ?>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem;">
                                                <span>รายรับรวม</span><strong><?php echo number_format($cb['total']); ?> บาท</strong>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem; color: var(--text-muted);">
                                                <span>&nbsp;&nbsp;• ลูกค้าโอนเข้าบัญชี</span><span><?php echo number_format($cb['transfer']); ?> บาท</span>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem;">
                                                <span>รับเงินสด</span><strong><?php echo number_format($cb['cash']); ?> บาท</strong>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem; font-size: 0.85rem; color: #b91c1c;">
                                                <span>หัก รายจ่ายต่อภาษี</span><span>−<?php echo number_format($cb['tax_expense']); ?> บาท</span>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; align-items: baseline; border-top: 1px solid #e2e8f0; padding-top: 0.5rem; margin-top: 0.5rem; font-weight: 800; font-size: 1.05rem; color: <?php echo $cb['cash_to_account'] < 0 ? '#b91c1c' : '#1d4ed8'; ?>;">
                                                <span><?php echo $cb['cash_to_account'] < 0 ? 'เงินสดไม่พอจ่าย' : 'เงินสดที่ต้องโอนเข้าบัญชี'; ?></span>
                                                <span><?php echo number_format(abs($cb['cash_to_account'])); ?> บาท</span>
                                            </div>
                                            <div style="text-align: right; margin-top: 0.75rem;">
                                                <button onclick="openAccountReportPopup('../daily_closing.php?day=<?php echo $p_day; ?>&month=<?php echo $p_month; ?>&year=<?php echo $p_year; ?>&id_branch=<?php echo $cb_branch; ?>')"
                                                   class="btn-print-tax" style="padding: 0.35rem 0.95rem; font-size: 0.8rem; background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">
                                                    📒 เปิดรายงานเต็ม / พิมพ์
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- SEARCH RESULTS TABLE -->
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                            <h3 class="card-title">🏢 รายการเข้าใช้บริการ (ทั้งหมด <?php echo number_format($total_records); ?> รายการ)</h3>
                            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                <?php if ($total_pages > 1): ?>
                                    <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                        หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table class="table-modern" style="font-size: 0.85rem;">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;" class="text-center">#</th>
                                        <th style="width: 80px;" class="text-center">เลขบิล</th>
                                        <th>ชื่อลูกค้า</th>
                                        <th style="width: 140px;" class="text-center">ทะเบียนรถ</th>
                                        <th style="width: 120px;" class="text-right text-primary">ยอดชำระสุทธิ</th>
                                        <th style="width: 110px;" class="text-center">การชำระเงิน</th>
                                        <th style="width: 120px;" class="text-center">สาขา</th>
                                        <th style="width: 160px;" class="text-center">เวลาธุรกรรม</th>
                                        <th class="text-center no-print" style="width: 140px;">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $idx = $offset + 1; 
                                    foreach ($payments as $p): 
                                        $is_cancelled = (isset($p['pay_status']) ? intval($p['pay_status']) : 0) === 3;
                                        $row_style = $is_cancelled ? 'style="background: #fff5f5; color: #b91c1c; opacity: 0.9;"' : '';
                                    ?>
                                        <tr <?php echo $row_style; ?>>
                                            <td class="text-center"><?php echo $idx++; ?></td>
                                            <td class="text-center" style="font-weight: 600;">#<?php echo $p['id_pay']; ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($p['cus_name']); ?></strong>
                                                <?php if ($is_cancelled): ?>
                                                    <span style="font-size: 0.725rem; font-weight: bold; color: #ef4444; background: #fee2e2; padding: 1px 6px; border-radius: 4px; margin-left: 0.35rem;">ยกเลิกแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo htmlspecialchars($p['car_regis_id'] . ' (' . $p['province_name'] . ')'); ?></td>
                                            <td class="text-right <?php echo $is_cancelled ? 'text-danger' : 'text-primary'; ?>" style="font-weight: 600;"><?php echo number_format($p['pay_total']); ?> บาท</td>
                                            <td class="text-center">
                                                <?php if ($is_cancelled): ?>
                                                    <span class="badge" style="background-color: #fca5a5; color: #b91c1c; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">🚫 ยกเลิกแล้ว</span>
                                                <?php elseif ((isset($p['pay_type']) ? $p['pay_type'] : 1) == 2): ?>
                                                    <span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">📱 เงินโอน</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #fef3c7; color: #d97706; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">💵 เงินสด</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge" style="background-color: var(--primary-light); color: var(--primary); font-size: 0.75rem; font-weight: 600;">
                                                    <?php echo htmlspecialchars($p['branch_name']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center" style="font-size: 0.8rem; color: var(--text-muted);">
                                                <?php echo $p['pay_day'] . '/' . $p['pay_month'] . '/' . ($p['pay_year'] + 543) . ' ' . substr($p['pay_time'], 0, 5) . ' น.'; ?>
                                            </td>
                                            <td class="text-center no-print" style="display: flex; gap: 0.35rem; justify-content: center; align-items: center;">
                                                <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 11px;" onclick="showDetails(<?php echo $p['id_pay']; ?>)">ตรวจสอบ</button>
                                                <?php if (!$is_cancelled): ?>
                                                    <form method="POST" action="cancel_receipt.php" style="display:inline;" onsubmit="return confirm('กรุณายืนยันการยกเลิกใบเสร็จนี้อีกครั้ง !!!');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="id_pay" value="<?php echo $p['id_pay']; ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px; font-size: 11px;">ยกเลิก</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #ef4444; color: #fff; font-size: 10px; padding: 3px 6px; font-weight: 600; opacity: 0.85; cursor: not-allowed;">ยกเลิกแล้ว</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    

                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINATION CONTROLS -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination-container" style="display: flex; justify-content: center; align-items: center; gap: 0.35rem; margin: 1.5rem 0; padding-bottom: 0.5rem;">
                                <?php
                                // Generate query string parameters to preserve search parameters
                                $query_params = $_GET;
                                unset($query_params['page']); // Remove page parameter to replace it
                                
                                $build_page_link = function($p_num) use ($query_params) {
                                    $params = array_merge($query_params, ['page' => $p_num]);
                                    return 'pay_list.php?' . http_build_query($params);
                                };
                                ?>
                                
                                <!-- Previous page link -->
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo $build_page_link($page - 1); ?>" class="btn-page">« ก่อนหน้า</a>
                                <?php else: ?>
                                    <span class="btn-page disabled">« ก่อนหน้า</span>
                                <?php endif; ?>
                                
                                <!-- Page numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<a href="' . $build_page_link(1) . '" class="btn-page">1</a>';
                                    if ($start_page > 2) {
                                        echo '<span class="pagination-dots">...</span>';
                                    }
                                }
                                
                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    $active_class = ($i == $page) ? 'active' : '';
                                    echo '<a href="' . $build_page_link($i) . '" class="btn-page ' . $active_class . '">' . $i . '</a>';
                                }
                                
                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<span class="pagination-dots">...</span>';
                                    }
                                    echo '<a href="' . $build_page_link($total_pages) . '" class="btn-page">' . $total_pages . '</a>';
                                }
                                ?>
                                
                                <!-- Next page link -->
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo $build_page_link($page + 1); ?>" class="btn-page">ถัดไป »</a>
                                <?php else: ?>
                                    <span class="btn-page disabled">ถัดไป »</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Transaction Detail Modal -->
    <div class="modal-overlay" id="transactionModal">
        <div class="modal-container">
            <div class="modal-header">
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0;">🧾 รายละเอียดใบเสร็จรับเงิน <span id="modalBillNo" style="color: var(--primary);"></span></h3>
                <button type="button" class="modal-close-btn" onclick="closeModal()">&times;</button>
            </div>
            <div class="details-grid">
                <!-- Section: Customer Info -->
                <div class="details-section-title">👤 ข้อมูลลูกค้าและพาหนะ</div>
                
                <div class="details-item">
                    <span class="details-label">ชื่อผู้รับบริการ</span>
                    <span class="details-value" id="modalCusName">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">เบอร์โทรศัพท์</span>
                    <span class="details-value" id="modalCusPhone">-</span>
                </div>
                <div class="details-item" style="grid-column: span 2;">
                    <span class="details-label">ที่อยู่</span>
                    <span class="details-value" id="modalCusAddr">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ทะเบียนรถ / จังหวัด</span>
                    <span class="details-value" id="modalCarRegis">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ประเภทรถ</span>
                    <span class="details-value" id="modalCarType">-</span>
                </div>

                <!-- Section: Service Fees -->
                <div class="details-section-title">⚙️ รายละเอียดค่าบริการ</div>
                
                <div class="details-item">
                    <span class="details-label">ค่า พ.ร.บ.</span>
                    <span class="details-value" id="modalServ1">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ค่าตรวจสภาพ (ตรอ.)</span>
                    <span class="details-value" id="modalServ2">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">เลขที่ใบตรวจสภาพ</span>
                    <span class="details-value" id="modalServ21">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ค่าภาษีประจำปี</span>
                    <span class="details-value" id="modalServ3">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ค่าภาษีย้อนหลัง</span>
                    <span class="details-value" id="modalServ4">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">จำนวนปี / ตั้งแต่ปี</span>
                    <span class="details-value" id="modalServ4Details">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ค่าปรับชำระล่าช้า</span>
                    <span class="details-value" id="modalServ5">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ค่าเล่มทะเบียน</span>
                    <span class="details-value" id="modalServ6">0 บาท</span>
                </div>
                <div class="details-item" style="grid-column: span 2;">
                    <span class="details-label">ค่าบริการอื่น ๆ</span>
                    <span class="details-value" id="modalServ7">0 บาท</span>
                </div>

                <!-- Section: Payment Summary -->
                <div class="details-section-title">💰 สรุปยอดเงิน</div>
                
                <div class="details-item">
                    <span class="details-label">ยอดรวมก่อนหักส่วนลด</span>
                    <span class="details-value" id="modalPaySum">0 บาท</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ส่วนลดพิเศษ</span>
                    <span class="details-value" style="color: var(--danger);" id="modalDiscount">0 บาท</span>
                </div>
                <div class="details-item" style="grid-column: span 2;">
                    <span class="details-label">ยอดชำระสุทธิ</span>
                    <span class="details-value total" id="modalPayTotal">0 บาท</span>
                </div>
                
                <div class="details-item">
                    <span class="details-label">เวลาบันทึกรายการ</span>
                    <span class="details-value" id="modalDateTime">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">สาขาที่ให้บริการ</span>
                    <span class="details-value" id="modalBranchName">-</span>
                </div>
                <div class="details-item">
                    <span class="details-label">ช่องทางชำระเงิน</span>
                    <span class="details-value" id="modalPayType">-</span>
                </div>
            </div>
            
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">ปิดหน้าต่าง</button>
                <a href="#" id="modalPrintLink" class="btn btn-primary" target="_blank">🖨️ พิมพ์ใบเสร็จ</a>
            </div>
        </div>
    </div>

    <script>
        function openAccountReportPopup(url) {
            window.open(url, 'account_report_popup', 'width=980,height=900,scrollbars=yes,resizable=yes');
        }

        function openTaxReferralPopup(url) {
            window.open(url, 'tax_referral_popup', 'width=980,height=850,scrollbars=yes,resizable=yes');
        }

        // Store payments displayed on the current page for details lookup
        const paymentDetails = <?php 
            $json = json_encode($payments ? $payments : [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); 
            echo $json !== false ? $json : '[]'; 
        ?>;

        function handleFilterTypeChange() {
            const filterType = document.getElementById('filter_type').value;
            const customFields = document.getElementById('customDateFields');
            const searchBtn = document.getElementById('searchBtn');
            
            if (filterType === 'custom') {
                customFields.style.display = 'flex';
                searchBtn.style.display = 'inline-flex';
            } else {
                customFields.style.display = 'none';
                searchBtn.style.display = 'none';
                // Auto submit the form on preset selection
                document.getElementById('filter_type').form.submit();
            }
        }

        function showDetails(idPay) {
            const data = paymentDetails.find(item => parseInt(item.id_pay) === parseInt(idPay));
            if (!data) return;
            
            document.getElementById('modalBillNo').innerText = '#' + data.id_pay;
            document.getElementById('modalCusName').innerText = data.cus_name;
            document.getElementById('modalCusPhone').innerText = data.cus_phone;
            document.getElementById('modalCusAddr').innerText = data.cus_addr;
            document.getElementById('modalCarRegis').innerText = `${data.car_regis_id} (${data.province_name})`;
            document.getElementById('modalCarType').innerText = data.car_type_name;
            
            document.getElementById('modalServ1').innerText = parseFloat(data.serv_1).toLocaleString() + ' บาท';
            document.getElementById('modalServ2').innerText = parseFloat(data.serv_2).toLocaleString() + ' บาท';
            document.getElementById('modalServ21').innerText = data.serv_2_1 ? data.serv_2_1 : '-';
            document.getElementById('modalServ3').innerText = parseFloat(data.serv_3).toLocaleString() + ' บาท';
            document.getElementById('modalServ4').innerText = parseFloat(data.serv_4).toLocaleString() + ' บาท';
            document.getElementById('modalServ4Details').innerText = parseInt(data.serv_4_1) > 0 ? `${data.serv_4_1} ปี (${data.serv_4_2})` : '-';
            document.getElementById('modalServ5').innerText = parseFloat(data.serv_5).toLocaleString() + ' บาท';
            document.getElementById('modalServ6').innerText = parseFloat(data.serv_6).toLocaleString() + ' บาท';
            
            let otherDetails = parseFloat(data.serv_7).toLocaleString() + ' บาท';
            if (data.serv_7_1) {
                otherDetails += ` (${data.serv_7_1})`;
            }
            document.getElementById('modalServ7').innerText = otherDetails;
            
            document.getElementById('modalPaySum').innerText = parseFloat(data.pay_sum).toLocaleString() + ' บาท';
            document.getElementById('modalDiscount').innerText = parseFloat(data.discount).toLocaleString() + ' บาท';
            document.getElementById('modalPayTotal').innerText = parseFloat(data.pay_total).toLocaleString() + ' บาท';
            
            // Format Date (Day/Month/Year BE)
            document.getElementById('modalDateTime').innerText = `${data.pay_day}/${data.pay_month}/${parseInt(data.pay_year) + 543} เวลา ${data.pay_time} น.`;
            document.getElementById('modalBranchName').innerText = data.branch_name ? data.branch_name : (parseInt(data.id_branch) === 1 ? 'ดอกคำใต้' : 'ท่าวังทอง');
            document.getElementById('modalPayType').innerHTML = parseInt(data.pay_type || 1) === 2 
                ? '<span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-weight: 600; font-size: 0.8rem; padding: 2px 8px;">📱 เงินโอน</span>' 
                : '<span class="badge" style="background-color: #fef3c7; color: #d97706; font-weight: 600; font-size: 0.8rem; padding: 2px 8px;">💵 เงินสด</span>';
            
            // Set print link (since we are inside V2/manager/, receipt.php is in parent directory)
            document.getElementById('modalPrintLink').href = '../receipt.php?id_pay=' + data.id_pay;
            
            // Show modal
            const modal = document.getElementById('transactionModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
            }, 10);
        }
        
        function closeModal() {
            const modal = document.getElementById('transactionModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Close modal when clicking outside the container
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('transactionModal');
            if (event.target === modal) {
                closeModal();
            }
        });
    </script>
</body>
</html>
