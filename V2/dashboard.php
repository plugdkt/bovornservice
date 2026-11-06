<?php
// dashboard.php
// Modern Interactive Sales Dashboard for Bovorn Service V2

require("db_connect.php");

// Enforce login for all authenticated roles (1 = Manager, 2 = Clerk)
check_login([]);

$id_emp = $_SESSION['id_emp'];
$id_emp_type = $_SESSION['id_emp_type'];
$id_branch = $_SESSION['id_branch'];
$user_name = $_SESSION['name'];

// Branch Filter for Managers
$branch_filter = 0;
if ($id_emp_type == 1 && isset($_GET['branch_filter'])) {
    $branch_filter = intval($_GET['branch_filter']);
}

// Month & Year Filters (Default to current month and year)
$month_filter = isset($_GET['month_filter']) ? intval($_GET['month_filter']) : intval(date('m'));
$year_filter = isset($_GET['year_filter']) ? intval($_GET['year_filter']) : (intval(date('Y')) + 543);
$year_ad = $year_filter - 543;

// Fetch branch options dynamically
$branches = [];
if ($id_emp_type == 1) {
    try {
        $stmt_b = $pdo->query("SELECT id_branch, branch_name FROM `branch` ORDER BY id_branch ASC");
        $branches = $stmt_b->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $db_error = "เกิดข้อผิดพลาดในการเชื่อมต่อสาขา: " . $e->getMessage();
    }
}

date_default_timezone_set('Asia/Bangkok');
$today_day = date('d');
$today_month = date('m');
$today_year = date('Y');

// Metrics variables
$today_revenue = 0;
$period_cash = 0;
$period_transfer = 0;
$today_inspections = 0;
$monthly_revenue = 0;
$total_transactions = 0;
$recent_activities = [];

// Charts data variables
$chart_vehicle_labels = [];
$chart_vehicle_counts = [];
$chart_branch_labels = ['สาขาดอกคำใต้', 'สาขาท่าวังทอง'];
$chart_branch_revenues = [0, 0];

try {
    if ($id_emp_type == 1) {
        // --- MANAGER METRICS (Combine both branches or filter by branch) ---
        
        // 1. Revenue in selected period (Month & Year or Year only)
        if ($month_filter > 0) {
            $sql = "SELECT SUM(pay_total) as total, 
                           SUM(CASE WHEN pay_type = 2 THEN pay_total ELSE 0 END) as transfer,
                           SUM(CASE WHEN pay_type != 2 OR p.pay_type IS NULL THEN pay_total ELSE 0 END) as cash
                    FROM `payment` p
                    WHERE pay_month = :month AND pay_year = :year AND pay_status = 2";
            $params = [':month' => $month_filter, ':year' => $year_ad];
        } else {
            $sql = "SELECT SUM(pay_total) as total, 
                           SUM(CASE WHEN pay_type = 2 THEN pay_total ELSE 0 END) as transfer,
                           SUM(CASE WHEN pay_type != 2 OR p.pay_type IS NULL THEN pay_total ELSE 0 END) as cash
                    FROM `payment` p
                    WHERE pay_year = :year AND pay_status = 2";
            $params = [':year' => $year_ad];
        }
        if ($branch_filter > 0) {
            $sql .= " AND id_branch = :branch";
            $params[':branch'] = $branch_filter;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rev_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $today_revenue = floatval(isset($rev_row['total']) ? $rev_row['total'] : 0);
        $period_cash = floatval(isset($rev_row['cash']) ? $rev_row['cash'] : 0);
        $period_transfer = floatval(isset($rev_row['transfer']) ? $rev_row['transfer'] : 0);
        
        // 2. Inspections (ตรอ.) in selected period
        if ($month_filter > 0) {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_month = :month AND pay_year = :year AND pay_status = 2 AND serv_2 > 0";
            $params = [':month' => $month_filter, ':year' => $year_ad];
        } else {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_year = :year AND pay_status = 2 AND serv_2 > 0";
            $params = [':year' => $year_ad];
        }
        if ($branch_filter > 0) {
            $sql .= " AND id_branch = :branch";
            $params[':branch'] = $branch_filter;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $today_inspections = intval($stmt->fetchColumn());
        
        // 3. Accumulated Revenue (Selected Year or All Time)
        if ($month_filter > 0) {
            $sql = "SELECT SUM(pay_total) FROM `payment` WHERE pay_year = :year AND pay_status = 2";
            $params = [':year' => $year_ad];
        } else {
            $sql = "SELECT SUM(pay_total) FROM `payment` WHERE pay_status = 2";
            $params = [];
        }
        if ($branch_filter > 0) {
            $sql .= " AND id_branch = :branch";
            $params[':branch'] = $branch_filter;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $monthly_revenue = floatval($stmt->fetchColumn());
        
        // 4. Total transactions count in selected period
        if ($month_filter > 0) {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_month = :month AND pay_year = :year AND pay_status = 2";
            $params = [':month' => $month_filter, ':year' => $year_ad];
        } else {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_year = :year AND pay_status = 2";
            $params = [':year' => $year_ad];
        }
        if ($branch_filter > 0) {
            $sql .= " AND id_branch = :branch";
            $params[':branch'] = $branch_filter;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $total_transactions = intval($stmt->fetchColumn());
        
        // 5. Recent transactions across branches within selected period (last 5)
        $sql = "SELECT p.*, b.branch_name, c.car_type_name, pr.PROVINCE_NAME as province_name 
                FROM `payment` p
                JOIN `branch` b ON p.id_branch = b.id_branch
                JOIN `car_type` c ON p.id_car_type = c.id_car_type
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE p.pay_status = 2";
        $params = [];
        if ($month_filter > 0) {
            $sql .= " AND p.pay_month = :month AND p.pay_year = :year";
            $params[':month'] = $month_filter;
            $params[':year'] = $year_ad;
        } else {
            $sql .= " AND p.pay_year = :year";
            $params[':year'] = $year_ad;
        }
        if ($branch_filter > 0) {
            $sql .= " AND p.id_branch = :branch";
            $params[':branch'] = $branch_filter;
        }
        $sql .= " ORDER BY p.id_pay DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recent_activities = $stmt->fetchAll();
        
        // 6. Chart: Branch Revenue Distribution (Only if no branch filter is active)
        if ($branch_filter == 0) {
            if ($month_filter > 0) {
                $sql = "SELECT id_branch, SUM(pay_total) as total FROM `payment` WHERE pay_status = 2 AND pay_month = :month AND pay_year = :year GROUP BY id_branch";
                $params = [':month' => $month_filter, ':year' => $year_ad];
            } else {
                $sql = "SELECT id_branch, SUM(pay_total) as total FROM `payment` WHERE pay_status = 2 AND pay_year = :year GROUP BY id_branch";
                $params = [':year' => $year_ad];
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            while ($row = $stmt->fetch()) {
                if ($row['id_branch'] == 1) $chart_branch_revenues[0] = floatval($row['total']);
                if ($row['id_branch'] == 2) $chart_branch_revenues[1] = floatval($row['total']);
            }
        }
        
    } else {
        // --- CLERK METRICS (Specific branch) ---
        
        // 1. Revenue in selected period
        if ($month_filter > 0) {
            $sql = "SELECT SUM(pay_total) as total, 
                           SUM(CASE WHEN pay_type = 2 THEN pay_total ELSE 0 END) as transfer,
                           SUM(CASE WHEN pay_type != 2 OR p.pay_type IS NULL THEN pay_total ELSE 0 END) as cash
                    FROM `payment` p
                    WHERE pay_month = :month AND pay_year = :year AND id_branch = :branch AND pay_status = 2";
            $params = [':month' => $month_filter, ':year' => $year_ad, ':branch' => $id_branch];
        } else {
            $sql = "SELECT SUM(pay_total) as total, 
                           SUM(CASE WHEN pay_type = 2 THEN pay_total ELSE 0 END) as transfer,
                           SUM(CASE WHEN pay_type != 2 OR p.pay_type IS NULL THEN pay_total ELSE 0 END) as cash
                    FROM `payment` p
                    WHERE pay_year = :year AND id_branch = :branch AND pay_status = 2";
            $params = [':year' => $year_ad, ':branch' => $id_branch];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rev_row = $stmt->fetch(PDO::FETCH_ASSOC);
        $today_revenue = floatval(isset($rev_row['total']) ? $rev_row['total'] : 0);
        $period_cash = floatval(isset($rev_row['cash']) ? $rev_row['cash'] : 0);
        $period_transfer = floatval(isset($rev_row['transfer']) ? $rev_row['transfer'] : 0);
        
        // 2. Inspections (ตรอ.) in selected period
        if ($month_filter > 0) {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_month = :month AND pay_year = :year AND id_branch = :branch AND pay_status = 2 AND serv_2 > 0";
            $params = [':month' => $month_filter, ':year' => $year_ad, ':branch' => $id_branch];
        } else {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_year = :year AND id_branch = :branch AND pay_status = 2 AND serv_2 > 0";
            $params = [':year' => $year_ad, ':branch' => $id_branch];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $today_inspections = intval($stmt->fetchColumn());
        
        // 3. Accumulated Revenue (Selected Year or All Time)
        if ($month_filter > 0) {
            $sql = "SELECT SUM(pay_total) FROM `payment` WHERE pay_year = :year AND id_branch = :branch AND pay_status = 2";
            $params = [':year' => $year_ad, ':branch' => $id_branch];
        } else {
            $sql = "SELECT SUM(pay_total) FROM `payment` WHERE id_branch = :branch AND pay_status = 2";
            $params = [':branch' => $id_branch];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $monthly_revenue = floatval($stmt->fetchColumn());
        
        // 4. Total transactions count in selected period
        if ($month_filter > 0) {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_month = :month AND pay_year = :year AND id_branch = :branch AND pay_status = 2";
            $params = [':month' => $month_filter, ':year' => $year_ad, ':branch' => $id_branch];
        } else {
            $sql = "SELECT COUNT(*) FROM `payment` WHERE pay_year = :year AND id_branch = :branch AND pay_status = 2";
            $params = [':year' => $year_ad, ':branch' => $id_branch];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $total_transactions = intval($stmt->fetchColumn());
        
        // 5. Recent transactions in their branch within selected period (last 5)
        $sql = "SELECT p.*, c.car_type_name, pr.PROVINCE_NAME as province_name 
                FROM `payment` p
                JOIN `car_type` c ON p.id_car_type = c.id_car_type
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE p.pay_status = 2 AND p.id_branch = :branch";
        $params = [':branch' => $id_branch];
        if ($month_filter > 0) {
            $sql .= " AND p.pay_month = :month AND p.pay_year = :year";
            $params[':month'] = $month_filter;
            $params[':year'] = $year_ad;
        } else {
            $sql .= " AND p.pay_year = :year";
            $params[':year'] = $year_ad;
        }
        $sql .= " ORDER BY p.id_pay DESC LIMIT 5";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $recent_activities = $stmt->fetchAll();
    }
    
    // 7. Chart: Vehicle Types breakdown (shared for both dashboard scopes)
    $sql_v = "SELECT c.car_type_name, COUNT(*) as cnt 
              FROM `payment` p 
              JOIN `car_type` c ON p.id_car_type = c.id_car_type 
              WHERE p.pay_status = 2";
    $params_v = [];
    if ($id_emp_type == 2) {
        $sql_v .= " AND p.id_branch = :branch";
        $params_v[':branch'] = $id_branch;
    } elseif ($branch_filter > 0) {
        $sql_v .= " AND p.id_branch = :branch";
        $params_v[':branch'] = $branch_filter;
    }
    if ($month_filter > 0) {
        $sql_v .= " AND p.pay_month = :month AND p.pay_year = :year";
        $params_v[':month'] = $month_filter;
        $params_v[':year'] = $year_ad;
    } else {
        $sql_v .= " AND p.pay_year = :year";
        $params_v[':year'] = $year_ad;
    }
    $sql_v .= " GROUP BY c.id_car_type, c.car_type_name ORDER BY cnt DESC LIMIT 5";
    
    $stmt_v = $pdo->prepare($sql_v);
    $stmt_v->execute($params_v);
    
    while ($row = $stmt_v->fetch()) {
        $chart_vehicle_labels[] = $row['car_type_name'];
        $chart_vehicle_counts[] = intval($row['cnt']);
    }
    
} catch (\PDOException $e) {
    $db_error = "เกิดข้อผิดพลาดในการโหลดข้อมูลหน้าแดชบอร์ด: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
    <!-- Chart.js CDN for beautiful interactive charts -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .grid-charts {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .chart-box {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-premium);
            height: 350px;
            display: flex;
            flex-direction: column;
        }
        .chart-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .chart-container {
            position: relative;
            flex-grow: 1;
            width: 100%;
            height: 100%;
        }
        .flex-row-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .quick-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }
        .filters-container {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        .filter-item {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            background-color: var(--primary-light);
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-md);
            border: 1px solid rgba(37, 99, 235, 0.15);
        }
        .filter-label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--primary);
            white-space: nowrap;
        }
        .filter-select {
            background-color: transparent;
            border: none;
            color: var(--text-main);
            font-family: inherit;
            font-weight: 600;
            font-size: 0.875rem;
            outline: none;
            cursor: pointer;
            padding-right: 1.25rem;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%232563eb' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right center;
            background-repeat: no-repeat;
            background-size: 1.1rem;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }
        .filter-select:focus {
            outline: none;
        }
        .filter-badge {
            background-color: var(--primary);
            color: var(--text-inverse);
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 9999px;
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

        /* Custom Premium Select Dropdowns */
        .custom-select-wrapper {
            position: relative;
            user-select: none;
            cursor: pointer;
        }
        .custom-select-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.35rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-main);
            padding: 0.15rem 0;
            min-width: 60px;
        }
        .custom-select-trigger svg.chevron {
            color: var(--primary);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .custom-select-wrapper.open .custom-select-trigger svg.chevron {
            transform: rotate(180deg);
        }
        .custom-select-options {
            position: absolute;
            top: calc(100% + 8px);
            right: 0;
            min-width: 150px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-md);
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.12), 0 8px 10px -6px rgba(15, 23, 42, 0.12);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(8px) scale(0.96);
            transition: opacity 0.18s cubic-bezier(0.4, 0, 0.2, 1), transform 0.18s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.18s;
            max-height: 250px;
            overflow-y: auto;
            padding: 0.35rem 0;
            border-top: 3px solid var(--primary);
        }
        .custom-select-options::-webkit-scrollbar {
            width: 5px;
        }
        .custom-select-options::-webkit-scrollbar-track {
            background: transparent;
        }
        .custom-select-options::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 99px;
        }
        .custom-select-wrapper.open .custom-select-options {
            opacity: 1;
            visibility: visible;
            transform: translateY(0) scale(1);
        }
        .custom-option {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            color: var(--text-main);
            transition: var(--transition);
            font-weight: 500;
            text-align: left;
        }
        .custom-option:hover {
            background-color: var(--primary-light);
            color: var(--primary);
        }
        .custom-option.selected {
            background-color: var(--primary);
            color: #fff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header" style="height: auto; min-height: 64px; padding: 0.75rem 2rem; flex-wrap: wrap; gap: 1rem;">
                <div class="header-title">📊 แดชบอร์ดสรุปวิเคราะห์ข้อมูล</div>
                <div class="filters-container">
                    <!-- Branch Filter -->
                    <div class="filter-item">
                        <?php if ($id_emp_type == 1): ?>
                            <span class="filter-label">สาขา:</span>
                            <select class="filter-select" id="branchFilterSelect" onchange="applyFilters()">
                                <option value="0" <?php echo $branch_filter == 0 ? 'selected' : ''; ?>>ทั้งหมด (ทุกสาขา)</option>
                                <?php foreach ($branches as $b): ?>
                                    <option value="<?php echo $b['id_branch']; ?>" <?php echo $branch_filter == $b['id_branch'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($b['branch_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <span class="filter-label">สาขา:</span>
                            <span class="filter-badge"><?php echo $id_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'; ?></span>
                        <?php endif; ?>
                    </div>

                    <!-- Month Filter -->
                    <div class="filter-item">
                        <span class="filter-label">เดือน:</span>
                        <select class="filter-select" id="monthFilterSelect" onchange="applyFilters()">
                            <option value="0" <?php echo $month_filter == 0 ? 'selected' : ''; ?>>ทั้งปี</option>
                            <?php
                            $months_th = [
                                1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 
                                5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม", 
                                9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
                            ];
                            foreach ($months_th as $m_num => $m_name):
                            ?>
                                <option value="<?php echo $m_num; ?>" <?php echo $month_filter == $m_num ? 'selected' : ''; ?>><?php echo $m_name; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Year Filter -->
                    <div class="filter-item">
                        <span class="filter-label">ปี:</span>
                        <select class="filter-select" id="yearFilterSelect" onchange="applyFilters()">
                            <?php
                            $current_year_th = date('Y') + 543;
                            for ($y = $current_year_th; $y >= 2557; $y--):
                            ?>
                                <option value="<?php echo $y; ?>" <?php echo $year_filter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="content-body">
                <?php if (isset($db_error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card" style="border-left: 4px solid var(--primary); background: linear-gradient(to right, #ffffff, #eff6ff);">
                    <h3 style="font-size: 1.25rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">ยินดีต้อนรับกลับมา,คุณ <?php echo htmlspecialchars($user_name); ?>! 👋</h3>
                    <p style="font-size: 0.875rem; color: var(--text-muted);">
                        นี่คือรายงานสรุปความเคลื่อนไหวธุรกรรมการตรวจสภาพรถ และยอดขายของสถานตรวจสภาพรถเอกชน ประจำช่วงเวลาที่คุณเลือก
                    </p>
                    
                    <div class="quick-actions">
                        <?php if ($id_emp_type == 2): ?>
                            <a href="billing.php" class="btn btn-primary btn-sm">🛒 ทำรายการบริการใหม่</a>
                            <a href="notification.php" class="btn btn-secondary btn-sm">🔔 ตรวจสอบคิวลูกค้าค้างแจ้งเตือน</a>
                        <?php else: ?>
                            <a href="manager/pay_list.php" class="btn btn-primary btn-sm">📊 ตรวจสอบยอดขายรวม</a>
                            <a href="manager/user_manager.php" class="btn btn-secondary btn-sm">👥 จัดการบัญชีพนักงาน</a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- KEY METRIC CARDS -->
                <div class="grid-widgets">
                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #ecfdf5; color: #10b981;">💰</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($today_revenue); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'ยอดขายประจำเดือนนี้ (บาท)' : 'ยอดขายประจำปีนี้ (บาท)'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #fff7ed; color: #ea580c;">💵</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($period_cash); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'ยอดเงินสดประจำเดือนนี้ (บาท)' : 'ยอดเงินสดประจำปีนี้ (บาท)'; ?>
                            </span>
                        </div>
                    </div>

                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #eff6ff; color: #2563eb;">📱</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($period_transfer); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'ยอดเงินโอนประจำเดือนนี้ (บาท)' : 'ยอดเงินโอนประจำปีนี้ (บาท)'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #fdf4ff; color: #9333ea;">🚘</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($today_inspections); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'จำนวนรถตรวจสภาพในเดือนนี้' : 'จำนวนรถตรวจสภาพในปีนี้'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <?php if ($id_emp_type != 1): ?>
                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #fdf2f8; color: #ec4899;">🧾</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($total_transactions); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'ธุรกรรมทั้งหมดในเดือนนี้' : 'ธุรกรรมทั้งหมดในปีนี้'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card widget-card">
                        <div class="widget-icon" style="background-color: #fefce8; color: #ca8a04;">📈</div>
                        <div class="widget-data">
                            <span class="widget-val"><?php echo number_format($monthly_revenue); ?></span>
                            <span class="widget-label">
                                <?php echo $month_filter > 0 ? 'ยอดขายสะสมทั้งปีนี้ (บาท)' : 'ยอดขายสะสมทั้งหมด (บาท)'; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- DYNAMIC CHARTS (CHART.JS) -->
                <div class="grid-charts">
                    <!-- Donut Chart: Vehicle Types -->
                    <div class="chart-box">
                        <div class="chart-title">🚗 สรุปประเภทรถยอดนิยม (คัน)</div>
                        <div class="chart-container">
                            <canvas id="vehicleChart"></canvas>
                        </div>
                    </div>
                    <!-- Bar Chart: Branch comparison for managers, or Monthly summary for clerks / single branch view -->
                    <?php if ($id_emp_type == 1 && $branch_filter == 0): ?>
                        <div class="chart-box">
                            <div class="chart-title">🏢 เปรียบเทียบสัดส่วนยอดขายระหว่างสาขา (บาท)</div>
                            <div class="chart-container">
                                <canvas id="branchChart"></canvas>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="chart-box">
                            <div class="chart-title">💡 ยอดสะสมวันนี้เทียบทั้งเดือน (บาท)</div>
                            <div class="chart-container">
                                <canvas id="monthCompChart"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- RECENT TRANSACTIONS TABLE -->
                <div class="card">
                    <div class="card-header flex-row-title">
                        <h3 class="card-title">⏰ รายการธุรกรรมชำระเงินล่าสุด</h3>
                        <?php if ($id_emp_type == 2): ?>
                            <a href="pay_list.php" class="btn btn-secondary btn-sm">ดูรายงานทั้งหมด</a>
                        <?php else: ?>
                            <a href="manager/pay_list.php" class="btn btn-secondary btn-sm">ดูรายงานทั้งหมด</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="table-container">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th style="width: 80px;" class="text-center">เลขบิล</th>
                                    <th>ชื่อลูกค้า</th>
                                    <th>ทะเบียน</th>
                                    <th>ประเภทรถยนต์</th>
                                    <th class="text-right">ยอดชำระสุทธิ</th>
                                    <th class="text-center">สาขา</th>
                                    <th class="text-center">เวลาธุรกรรม</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_activities)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center" style="padding: 2.5rem; color: var(--text-muted);">
                                            ไม่มีความเคลื่อนไหวธุรกรรมการเงินในวันนี้
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $act): ?>
                                        <tr>
                                            <td class="text-center" style="font-weight: 600;">#<?php echo $act['id_pay']; ?></td>
                                            <td><strong><?php echo htmlspecialchars($act['cus_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($act['car_regis_id']); ?></td>
                                            <td><?php echo htmlspecialchars($act['car_type_name']); ?></td>
                                            <td class="text-right" style="font-weight: 600; color: var(--primary);"><?php echo number_format($act['pay_total']); ?> บาท</td>
                                            <td class="text-center">
                                                <span class="badge" style="background-color: var(--primary-light); color: var(--primary);">
                                                    <?php echo $act['id_branch'] == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?php echo htmlspecialchars($act['pay_time']); ?> น.</td>
                                            <td class="text-center">
                                                <?php if ($id_emp_type == 2): ?>
                                                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px;" onclick="showDetails(<?php echo $act['id_pay']; ?>)">เปิดบิล</button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-secondary btn-sm" style="padding: 2px 8px;" onclick="showDetails(<?php echo $act['id_pay']; ?>)">ตรวจสอบ</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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
            </div>
            
            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">ปิดหน้าต่าง</button>
                <a href="#" id="modalPrintLink" class="btn btn-primary" target="_blank">🖨️ พิมพ์ใบเสร็จ</a>
            </div>
        </div>
    </div>

    <!-- Chart rendering logic -->
    <script>
        // Data payload of recent activities for the modal popup
        const recentActivities = <?php echo json_encode($recent_activities); ?>;

        // Common Chart: Vehicle Type Breakdown (Horizontal Bar Chart)
        const vehicleCtx = document.getElementById('vehicleChart').getContext('2d');
        const vehicleLabels = <?php echo json_encode($chart_vehicle_labels); ?>;
        const vehicleCounts = <?php echo json_encode($chart_vehicle_counts); ?>;
        
        new Chart(vehicleCtx, {
            type: 'bar',
            data: {
                labels: vehicleLabels.length > 0 ? vehicleLabels : ['ไม่มีข้อมูลในช่วงเวลานี้'],
                datasets: [{
                    label: 'จำนวนคัน',
                    data: vehicleCounts.length > 0 ? vehicleCounts : [0],
                    backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6'],
                    borderColor: ['#2563eb', '#059669', '#d97706', '#db2777', '#7c3aed'],
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                            font: { family: 'Sarabun, Outfit' }
                        }
                    },
                    y: {
                        ticks: {
                            font: { family: 'Sarabun, Outfit', weight: 600 }
                        }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });

        // Unified JS Redirection function for Dashboard Filters
        function applyFilters() {
            const branchEl = document.getElementById('branchFilterSelect');
            const branchId = branchEl ? branchEl.value : '<?php echo $id_branch; ?>';
            const monthId = document.getElementById('monthFilterSelect').value;
            const yearId = document.getElementById('yearFilterSelect').value;
            window.location.href = `dashboard.php?branch_filter=${branchId}&month_filter=${monthId}&year_filter=${yearId}`;
        }

        // Dashboard Branch Revenue chart (Manager only when All branches selected) or Period/Year Comparison (Clerk or Manager with branch filter)
        <?php if ($id_emp_type == 1 && $branch_filter == 0): ?>
            const branchCtx = document.getElementById('branchChart').getContext('2d');
            const branchLabels = <?php echo json_encode($chart_branch_labels); ?>;
            const branchRevenues = <?php echo json_encode($chart_branch_revenues); ?>;
            
            new Chart(branchCtx, {
                type: 'bar',
                data: {
                    labels: branchLabels,
                    datasets: [{
                        label: 'รายได้สะสมของสาขา (บาท)',
                        data: branchRevenues,
                        backgroundColor: ['#3b82f6', '#8b5cf6'],
                        borderColor: ['#2563eb', '#7c3aed'],
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                font: { family: 'Sarabun, Outfit' }
                            }
                        },
                        x: {
                            ticks: {
                                font: { family: 'Sarabun, Outfit', weight: 600 }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        <?php else: ?>
            const monthCompCtx = document.getElementById('monthCompChart').getContext('2d');
            const todayRevenue = <?php echo $today_revenue; ?>;
            const monthRevenue = <?php echo $monthly_revenue; ?>;
            
            new Chart(monthCompCtx, {
                type: 'bar',
                data: {
                    labels: [
                        '<?php echo $month_filter > 0 ? "ยอดขายประจำเดือนนี้" : "ยอดขายประจำปีนี้"; ?>', 
                        '<?php echo $month_filter > 0 ? "ยอดขายสะสมทั้งปีนี้" : "ยอดขายสะสมทั้งหมด"; ?>'
                    ],
                    datasets: [{
                        data: [todayRevenue, monthRevenue],
                        backgroundColor: ['#10b981', '#3b82f6'],
                        borderColor: ['#059669', '#2563eb'],
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                font: { family: 'Sarabun, Outfit' }
                            }
                        },
                        y: {
                            ticks: {
                                font: { family: 'Sarabun, Outfit', weight: 600 }
                            }
                        }
                    },
                    plugins: {
                        legend: { display: false }
                    }
                }
            });
        <?php endif; ?>

        // JS function to show transaction details in modal
        function showDetails(idPay) {
            const data = recentActivities.find(item => parseInt(item.id_pay) === parseInt(idPay));
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
            
            // Set print link (only allow clerks to print if pay_status == 1, managers can reprint)
            const printBtn = document.getElementById('modalPrintLink');
            if (parseInt(data.pay_status) === 1 || <?php echo $_SESSION['id_emp_type'] == 1 ? 'true' : 'false'; ?>) {
                printBtn.style.display = 'inline-flex';
                printBtn.href = 'receipt.php?id_pay=' + data.id_pay;
            } else {
                printBtn.style.display = 'none';
            }
            
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

        // Initialize Custom Select Dropdowns
        document.addEventListener('DOMContentLoaded', function() {
            const originalSelects = document.querySelectorAll('.filter-select');
            
            originalSelects.forEach(select => {
                const wrapper = document.createElement('div');
                wrapper.className = 'custom-select-wrapper';
                
                // Trigger
                const trigger = document.createElement('div');
                trigger.className = 'custom-select-trigger';
                
                const triggerText = document.createElement('span');
                const selectedOpt = select.options[select.selectedIndex];
                triggerText.textContent = selectedOpt ? selectedOpt.textContent : '';
                
                const chevron = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                chevron.setAttribute('class', 'chevron');
                chevron.setAttribute('width', '12');
                chevron.setAttribute('height', '12');
                chevron.setAttribute('viewBox', '0 0 24 24');
                chevron.setAttribute('fill', 'none');
                chevron.setAttribute('stroke', 'currentColor');
                chevron.setAttribute('stroke-width', '2.5');
                chevron.innerHTML = '<path d="M6 9l6 6 6-6"/>';
                
                trigger.appendChild(triggerText);
                trigger.appendChild(chevron);
                wrapper.appendChild(trigger);
                
                // Options list
                const optionsContainer = document.createElement('div');
                optionsContainer.className = 'custom-select-options';
                
                Array.from(select.options).forEach(opt => {
                    const optDiv = document.createElement('div');
                    optDiv.className = 'custom-option';
                    if (opt.selected) optDiv.classList.add('selected');
                    optDiv.textContent = opt.textContent;
                    optDiv.setAttribute('data-value', opt.value);
                    
                    optDiv.addEventListener('click', function(e) {
                        e.stopPropagation();
                        optionsContainer.querySelectorAll('.custom-option').forEach(el => el.classList.remove('selected'));
                        this.classList.add('selected');
                        triggerText.textContent = this.textContent;
                        
                        select.value = this.getAttribute('data-value');
                        select.dispatchEvent(new Event('change'));
                        
                        wrapper.classList.remove('open');
                    });
                    
                    optionsContainer.appendChild(optDiv);
                });
                
                wrapper.appendChild(optionsContainer);
                
                // Toggle open state on trigger click
                trigger.addEventListener('click', function(e) {
                    e.stopPropagation();
                    document.querySelectorAll('.custom-select-wrapper').forEach(w => {
                        if (w !== wrapper) w.classList.remove('open');
                    });
                    wrapper.classList.toggle('open');
                });
                
                // Insert next to original select & hide original select
                select.parentNode.insertBefore(wrapper, select);
                select.style.display = 'none';
            });
            
            // Close all custom selects on click outside
            document.addEventListener('click', function() {
                document.querySelectorAll('.custom-select-wrapper').forEach(w => w.classList.remove('open'));
            });
        });
    </script>
</body>
</html>
