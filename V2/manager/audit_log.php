<?php
// manager/audit_log.php
// Sales Audit Trail & Anti-Fraud Log (Manager View) for Bovorn Service V2
// Fully backwards-compatible with PHP 5.4+ through PHP 8.x
date_default_timezone_set('Asia/Bangkok');
require("con_db.php");

// ── AJAX Endpoint 1: Fetch Full Timeline of a Specific Bill ────────────────
if (isset($_GET['ajax_timeline'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id_pay = isset($_GET['id_pay']) ? intval($_GET['id_pay']) : 0;
    if ($id_pay <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Invalid ID'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT l.*, b.branch_name 
            FROM `payment_log` l
            LEFT JOIN `branch` b ON l.id_branch = b.id_branch
            WHERE l.id_pay = :id_pay 
            ORDER BY l.id_log ASC");
        $stmt->execute(array(':id_pay' => $id_pay));
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch current status of payment if available
        $stmt_pay = $pdo->prepare("SELECT p.*, c.car_type_name, pr.PROVINCE_NAME, b.branch_name 
            FROM `payment` p
            LEFT JOIN `car_type` c ON p.id_car_type = c.id_car_type
            LEFT JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
            LEFT JOIN `branch` b ON p.id_branch = b.id_branch
            WHERE p.id_pay = :id_pay");
        $stmt_pay->execute(array(':id_pay' => $id_pay));
        $pay_info = $stmt_pay->fetch(PDO::FETCH_ASSOC);

        echo json_encode(array(
            'success' => true,
            'id_pay' => $id_pay,
            'payment' => $pay_info,
            'logs' => $logs
        ));
        exit;
    } catch (\PDOException $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        exit;
    }
}

// ── AJAX Endpoint 2: Fetch Diff Data of a Specific Log ─────────────────────
if (isset($_GET['ajax_diff'])) {
    header('Content-Type: application/json; charset=utf-8');
    $id_log = isset($_GET['id_log']) ? intval($_GET['id_log']) : 0;
    if ($id_log <= 0) {
        echo json_encode(array('success' => false, 'message' => 'Invalid ID'));
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT id_log, id_pay, action, action_label, reason, changes_summary, old_data, new_data, created_at, emp_name 
            FROM `payment_log` WHERE id_log = :id_log");
        $stmt->execute(array(':id_log' => $id_log));
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) {
            echo json_encode(array('success' => false, 'message' => 'Log not found'));
            exit;
        }
        $old_decoded = !empty($log['old_data']) ? json_decode($log['old_data'], true) : null;
        $new_decoded = !empty($log['new_data']) ? json_decode($log['new_data'], true) : null;
        echo json_encode(array(
            'success' => true,
            'log' => $log,
            'old' => $old_decoded,
            'new' => $new_decoded
        ));
        exit;
    } catch (\PDOException $e) {
        echo json_encode(array('success' => false, 'message' => $e->getMessage()));
        exit;
    }
}

// ── Search Filters ─────────────────────────────────────────────────────────
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
if (empty($filter_type)) {
    try {
        $today_cnt = (int)$pdo->query("SELECT COUNT(*) FROM `payment_log` WHERE DATE(`created_at`) = CURDATE()")->fetchColumn();
        if ($today_cnt > 0) {
            $filter_type = 'today';
        } else {
            $month_cnt = (int)$pdo->query("SELECT COUNT(*) FROM `payment_log` WHERE DATE_FORMAT(`created_at`, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')")->fetchColumn();
            $filter_type = ($month_cnt > 0) ? 'month' : 'all';
        }
    } catch (\PDOException $e) {
        $filter_type = 'month';
    }
}
$branch_id   = isset($_GET['branch_id']) ? intval($_GET['branch_id']) : 0;
$action_sel  = isset($_GET['action_sel']) ? trim($_GET['action_sel']) : 'ALL';
$q           = isset($_GET['q']) ? trim($_GET['q']) : '';

$months_th = array(
    1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 
    5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม", 
    9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
);

if ($filter_type === 'today') {
    $day = intval(date('d')); $day2 = 0; $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'month') {
    $day = 0; $day2 = 0; $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'year') {
    $day = 0; $day2 = 0; $month = 0; $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'all') {
    $day = 0; $day2 = 0; $month = 0; $year = 0;
} else { // custom
    $day   = isset($_GET['day']) ? intval($_GET['day']) : 0;
    $day2  = isset($_GET['day2']) ? intval($_GET['day2']) : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $year  = isset($_GET['year']) ? intval($_GET['year']) : (date('Y') + 543);
}

// Query construction
$conditions = array("1=1");
$params = array();

// Date filtering on created_at (DATETIME)
if ($year > 0) {
    $cal_year = $year - 543;
    if ($month > 0) {
        if ($day > 0 && $day2 == 0) {
            $date_str = sprintf('%04d-%02d-%02d', $cal_year, $month, $day);
            $conditions[] = "DATE(l.created_at) = :d1";
            $params[':d1'] = $date_str;
        } elseif ($day > 0 && $day2 > 0) {
            $date_str1 = sprintf('%04d-%02d-%02d 00:00:00', $cal_year, $month, $day);
            $date_str2 = sprintf('%04d-%02d-%02d 23:59:59', $cal_year, $month, $day2);
            $conditions[] = "l.created_at >= :d1 AND l.created_at <= :d2";
            $params[':d1'] = $date_str1;
            $params[':d2'] = $date_str2;
        } else {
            $month_str = sprintf('%04d-%02d', $cal_year, $month);
            $conditions[] = "DATE_FORMAT(l.created_at, '%Y-%m') = :m1";
            $params[':m1'] = $month_str;
        }
    } else {
        $conditions[] = "YEAR(l.created_at) = :y1";
        $params[':y1'] = $cal_year;
    }
}

if ($branch_id > 0) {
    $conditions[] = "l.id_branch = :branch_id";
    $params[':branch_id'] = $branch_id;
}

if (!empty($action_sel) && $action_sel !== 'ALL') {
    $conditions[] = "l.action = :action";
    $params[':action'] = $action_sel;
}

if (!empty($q)) {
    $conditions[] = "(l.id_pay = :q_num OR l.emp_name LIKE :q_like OR l.reason LIKE :q_like OR l.changes_summary LIKE :q_like)";
    $params[':q_num']  = intval($q);
    $params[':q_like'] = "%" . $q . "%";
}

$where_sql = implode(" AND ", $conditions);

// ── Summary Statistics ─────────────────────────────────────────────────────
$total_logs = 0;
$count_edit = 0;
$count_cancel = 0;
$count_reprint = 0;

try {
    $stat_sql = "SELECT 
        COUNT(*) as total_logs,
        SUM(CASE WHEN l.action = 'EDIT' THEN 1 ELSE 0 END) as count_edit,
        SUM(CASE WHEN l.action = 'CANCEL' THEN 1 ELSE 0 END) as count_cancel,
        SUM(CASE WHEN l.action = 'REPRINT' THEN 1 ELSE 0 END) as count_reprint
        FROM `payment_log` l
        WHERE $where_sql";
    $stmt_stat = $pdo->prepare($stat_sql);
    $stmt_stat->execute($params);
    $stats = $stmt_stat->fetch(PDO::FETCH_ASSOC);
    if ($stats) {
        $total_logs    = intval($stats['total_logs']);
        $count_edit    = intval($stats['count_edit']);
        $count_cancel  = intval($stats['count_cancel']);
        $count_reprint = intval($stats['count_reprint']);
    }
} catch (\PDOException $e) {
    error_log("Audit log stat error: " . $e->getMessage());
}

// ── Pagination & Records Fetch ─────────────────────────────────────────────
$limit = 25;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;
$total_pages = ceil($total_logs / $limit);
if ($total_pages > 0 && $page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $limit;

$logs = array();
try {
    $query = "SELECT l.*, b.branch_name, p.pay_status as current_pay_status, p.cus_name, p.car_regis_id, p.pay_total as current_total
        FROM `payment_log` l
        LEFT JOIN `branch` b ON l.id_branch = b.id_branch
        LEFT JOIN `payment` p ON l.id_pay = p.id_pay
        WHERE $where_sql
        ORDER BY l.id_log DESC
        LIMIT :limit OFFSET :offset";
    $stmt_list = $pdo->prepare($query);
    foreach ($params as $k => $v) {
        $stmt_list->bindValue($k, $v);
    }
    $stmt_list->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_list->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_list->execute();
    $logs = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Audit log fetch error: " . $e->getMessage());
}

function thai_datetime($dt_str) {
    if (empty($dt_str)) return '-';
    $time = strtotime($dt_str);
    if (!$time) return $dt_str;
    $d = date('d', $time);
    $m = date('m', $time);
    $y = date('Y', $time) + 543;
    $t = date('H:i', $time);
    return sprintf('%s/%s/%d %s น.', $d, $m, $y, $t);
}

$build_query = function($overrides = array()) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    return 'audit_log.php?' . http_build_query($params);
};
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🛡️ ตรวจสอบประวัติการขายและป้องกันการทุจริต - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .stat-box {
            background: var(--bg-card);
            border-radius: var(--radius-lg);
            padding: 1.15rem 1.25rem;
            border: 1px solid var(--border);
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-box.stat-edit { border-left-color: #f59e0b; }
        .stat-box.stat-cancel { border-left-color: #ef4444; }
        .stat-box.stat-reprint { border-left-color: #8b5cf6; }
        
        .stat-label { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; }
        .stat-value { font-size: 1.65rem; font-weight: 700; color: var(--text-main); margin-top: 0.25rem; }
        
        .badge-act {
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.78rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            white-space: nowrap;
        }
        .act-CREATE  { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .act-EDIT    { background: #fffbeb; color: #b45309; border: 1px solid #fde68a; font-weight: 700; }
        .act-CANCEL  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; font-weight: 700; }
        .act-REPRINT { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; font-weight: 700; }
        .act-PRINT   { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }

        .timeline-container {
            position: relative;
            padding-left: 1.5rem;
            margin: 1.25rem 0;
        }
        .timeline-container::before {
            content: '';
            position: absolute;
            left: 7px;
            top: 5px;
            bottom: 5px;
            width: 2px;
            background: #e2e8f0;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 1.25rem;
        }
        .timeline-dot {
            position: absolute;
            left: -1.5rem;
            top: 3px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #fff;
            border: 3px solid var(--primary);
        }
        .timeline-item.act-EDIT .timeline-dot { border-color: #f59e0b; }
        .timeline-item.act-CANCEL .timeline-dot { border-color: #ef4444; }
        .timeline-item.act-REPRINT .timeline-dot { border-color: #8b5cf6; }
        .timeline-item.act-CREATE .timeline-dot { border-color: #10b981; }

        .diff-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-top: 0.75rem;
        }
        .diff-table th, .diff-table td {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
        }
        .diff-table th { background: #f8fafc; font-weight: 600; text-align: left; }
        .diff-changed { background: #fef3c7; color: #92400e; font-weight: 600; }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <!-- Header -->
            <div class="header">
                <div class="header-title">🛡️ ตรวจสอบประวัติการขายและป้องกันการทุจริต</div>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <div style="font-size: 0.875rem; color: var(--text-muted);">
                        บทบาท: <strong style="color: var(--primary);">ผู้จัดการระบบ</strong>
                    </div>
                    <a href="pay_list.php" class="btn btn-secondary btn-sm" style="font-size: 0.82rem; padding: 4px 10px;">📊 หน้ารายงานการขาย</a>
                </div>
            </div>

            <div class="content-body">
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: -0.5rem; margin-bottom: 1.25rem;">
                    บันทึกประวัติการสร้างบิล การแก้ไขข้อมูล การพิมพ์ซ้ำ และการยกเลิกบิลทุกขั้นตอน เพื่อความโปร่งใสและตรวจสอบย้อนหลังได้ 100%
                </p>

                <!-- Stat Summary Cards -->
                <div class="stat-grid">
                    <div class="stat-box">
                        <div>
                            <div class="stat-label">ประวัติการทำรายการทั้งหมด</div>
                            <div class="stat-value"><?php echo number_format($total_logs); ?></div>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.6;">📋</div>
                    </div>
                    <div class="stat-box stat-edit">
                        <div>
                            <div class="stat-label">รายการที่มีการแก้ไขข้อมูล</div>
                            <div class="stat-value" style="color: #d97706;"><?php echo number_format($count_edit); ?></div>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.8;">✏️</div>
                    </div>
                    <div class="stat-box stat-cancel">
                        <div>
                            <div class="stat-label">ใบเสร็จที่ถูกยกเลิก</div>
                            <div class="stat-value" style="color: #dc2626;"><?php echo number_format($count_cancel); ?></div>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.8;">🚫</div>
                    </div>
                    <div class="stat-box stat-reprint">
                        <div>
                            <div class="stat-label">ใบเสร็จที่มีการพิมพ์ซ้ำ</div>
                            <div class="stat-value" style="color: #7c3aed;"><?php echo number_format($count_reprint); ?></div>
                        </div>
                        <div style="font-size: 2rem; opacity: 0.8;">🖨️</div>
                    </div>
                </div>

                <!-- Filter Card -->
                <div class="card" style="margin-bottom: 1.5rem;">
                    <div class="card-body" style="padding: 1.25rem;">
                        <form method="GET" action="audit_log.php" id="filterForm">
                            <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                                <!-- Period filter -->
                                <div style="flex: 1; min-width: 160px;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">ช่วงเวลา</label>
                                    <select name="filter_type" id="filter_type" class="form-control" onchange="handlePeriodChange()" style="width:100%; padding:0.5rem 0.75rem; border-radius:6px; border:1px solid var(--border);">
                                        <option value="today" <?php echo $filter_type === 'today' ? 'selected' : ''; ?>>วันนี้ (Today)</option>
                                        <option value="month" <?php echo $filter_type === 'month' ? 'selected' : ''; ?>>เดือนนี้ (This Month)</option>
                                        <option value="year" <?php echo $filter_type === 'year' ? 'selected' : ''; ?>>ปีนี้ (This Year)</option>
                                        <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>ทั้งหมด (All Time)</option>
                                        <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>กำหนดเอง (Custom)</option>
                                    </select>
                                </div>

                                <!-- Branch filter -->
                                <div style="flex: 1; min-width: 150px;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">สาขา</label>
                                    <select name="branch_id" class="form-control" style="width:100%; padding:0.5rem 0.75rem; border-radius:6px; border:1px solid var(--border);">
                                        <option value="0" <?php echo $branch_id == 0 ? 'selected' : ''; ?>>ทุกสาขา</option>
                                        <option value="1" <?php echo $branch_id == 1 ? 'selected' : ''; ?>>สาขาดอกคำใต้</option>
                                        <option value="2" <?php echo $branch_id == 2 ? 'selected' : ''; ?>>สาขาท่าวังทอง</option>
                                    </select>
                                </div>

                                <!-- Action filter -->
                                <div style="flex: 1.2; min-width: 180px;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">ประเภทกิจกรรม</label>
                                    <select name="action_sel" class="form-control" style="width:100%; padding:0.5rem 0.75rem; border-radius:6px; border:1px solid var(--border);">
                                        <option value="ALL" <?php echo $action_sel === 'ALL' ? 'selected' : ''; ?>>ทั้งหมดทุกกิจกรรม</option>
                                        <option value="EDIT" <?php echo $action_sel === 'EDIT' ? 'selected' : ''; ?>>✏️ มีการแก้ไขข้อมูล (EDIT)</option>
                                        <option value="CANCEL" <?php echo $action_sel === 'CANCEL' ? 'selected' : ''; ?>>🚫 มีการยกเลิกบิล (CANCEL)</option>
                                        <option value="REPRINT" <?php echo $action_sel === 'REPRINT' ? 'selected' : ''; ?>>🖨️ มีการพิมพ์ซ้ำ (REPRINT)</option>
                                        <option value="CREATE" <?php echo $action_sel === 'CREATE' ? 'selected' : ''; ?>>🟢 สร้างบิลใหม่ (CREATE)</option>
                                        <option value="PRINT" <?php echo $action_sel === 'PRINT' ? 'selected' : ''; ?>>🔵 พิมพ์บิลครั้งแรก (PRINT)</option>
                                    </select>
                                </div>

                                <!-- Keyword search -->
                                <div style="flex: 1.5; min-width: 200px;">
                                    <label style="display:block; font-size:0.8rem; font-weight:600; color:var(--text-muted); margin-bottom:0.35rem;">ค้นหา (เลขที่บิล, ชื่อพนักงาน, เหตุผล)</label>
                                    <input type="text" name="q" class="form-control" placeholder="เช่น 171697, นัยนา, แก้ไข..." value="<?php echo htmlspecialchars($q); ?>" style="width:100%; padding:0.5rem 0.75rem; border-radius:6px; border:1px solid var(--border);">
                                </div>

                                <!-- Submit Buttons -->
                                <div style="display:flex; gap:0.5rem;">
                                    <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1.25rem;">🔍 ค้นหา</button>
                                    <a href="audit_log.php" class="btn btn-secondary" style="padding: 0.5rem 1rem;">ล้างค่า</a>
                                </div>
                            </div>

                            <!-- Custom Date Fields (shown when filter_type === 'custom') -->
                            <div id="customDateFields" style="display: <?php echo $filter_type === 'custom' ? 'flex' : 'none'; ?>; flex-wrap: wrap; gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px dashed var(--border);">
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-muted); display:block;">จากวันที่</label>
                                    <input type="number" min="1" max="31" name="day" value="<?php echo $day > 0 ? $day : ''; ?>" placeholder="เช่น 1" style="width:80px; padding:0.4rem; border:1px solid var(--border); border-radius:4px;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-muted); display:block;">ถึงวันที่</label>
                                    <input type="number" min="1" max="31" name="day2" value="<?php echo $day2 > 0 ? $day2 : ''; ?>" placeholder="เช่น 31" style="width:80px; padding:0.4rem; border:1px solid var(--border); border-radius:4px;">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-muted); display:block;">เดือน</label>
                                    <select name="month" style="padding:0.4rem; border:1px solid var(--border); border-radius:4px;">
                                        <option value="0">ทุกเดือน</option>
                                        <?php for ($m = 1; $m <= 12; $m++): ?>
                                            <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>><?php echo $months_th[$m]; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-muted); display:block;">ปี พ.ศ.</label>
                                    <input type="number" name="year" value="<?php echo $year; ?>" style="width:90px; padding:0.4rem; border:1px solid var(--border); border-radius:4px;">
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="card" style="overflow: hidden;">
                    <div class="card-header" style="padding: 1rem 1.25rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #fafafa;">
                        <h3 class="card-title" style="font-size: 1rem; font-weight: 700; color: var(--text-main); margin: 0;">
                            📋 บันทึกกิจกรรมการขาย (พบ <?php echo number_format($total_logs); ?> รายการ)
                        </h3>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            หน้า <?php echo $page; ?> จาก <?php echo max(1, $total_pages); ?>
                        </div>
                    </div>

                    <div class="table-responsive" style="overflow-x: auto;">
                        <table class="table-modern" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1.5px solid var(--border); text-align: left; font-size: 0.82rem; color: var(--text-muted);">
                                    <th style="padding: 10px 14px; width: 140px;">วัน-เวลา</th>
                                    <th style="padding: 10px 10px; width: 100px; text-align: center;">เลขที่บิล</th>
                                    <th style="padding: 10px 10px; width: 110px;">สาขา</th>
                                    <th style="padding: 10px 12px; width: 140px;">ผู้ทำรายการ</th>
                                    <th style="padding: 10px 12px; width: 130px; text-align: center;">กิจกรรม</th>
                                    <th style="padding: 10px 14px;">รายละเอียดการเปลี่ยนแปลง / หมายเหตุ</th>
                                    <th style="padding: 10px 12px; width: 140px;">เหตุผล</th>
                                    <th style="padding: 10px 12px; width: 130px; text-align: center;">เครื่องมือ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 3rem 1rem; color: var(--text-muted);">
                                            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🔍</div>
                                            <div style="font-weight: 600; font-size: 0.95rem; color: var(--text-main); margin-bottom: 0.35rem;">ไม่พบประวัติการทำรายการตามเงื่อนไขที่เลือก</div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted); max-width: 500px; margin: 0 auto; line-height: 1.5;">
                                                <?php if ($filter_type === 'today'): ?>
                                                    ยังไม่มีการสร้างบิล แก้ไข หรือยกเลิกบิลในวันนี้ ท่านสามารถเปลี่ยนตัวกรองเป็น <strong>"เดือนนี้"</strong> หรือ <strong>"ทั้งหมด"</strong> เพื่อดูประวัติย้อนหลังได้ครับ
                                                <?php else: ?>
                                                    ระบบบันทึกประวัติ (Audit Log) จะบันทึกข้อมูลแบบเรียลไทม์อัตโนมัติเมื่อพนักงานสร้างบิล, แก้ไขบิล, พิมพ์ใบเสร็จ หรือยกเลิกบิล
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($logs as $l): ?>
                                        <?php 
                                            $act = $l['action'];
                                            $act_class = 'act-' . $act;
                                            $is_suspicious = ($act === 'EDIT' || $act === 'CANCEL');
                                        ?>
                                        <tr style="border-bottom: 1px solid var(--border); font-size: 0.85rem; <?php echo $is_suspicious ? 'background-color: #fffdfa;' : ''; ?>">
                                            <td style="padding: 10px 14px; color: var(--text-muted); font-size: 0.82rem; white-space: nowrap;">
                                                <?php echo thai_datetime($l['created_at']); ?>
                                            </td>
                                            <td style="padding: 10px 10px; text-align: center; font-weight: 700;">
                                                <a href="javascript:void(0)" onclick="viewTimeline(<?php echo $l['id_pay']; ?>)" style="color: var(--primary); text-decoration: underline;" title="คลิกเพื่อดูประวัติบิลนี้ทั้งหมด">
                                                    #<?php echo htmlspecialchars($l['id_pay']); ?>
                                                </a>
                                            </td>
                                            <td style="padding: 10px 10px;">
                                                <?php if ($l['id_branch'] == 1): ?>
                                                    <span class="badge" style="background:#e0f2fe; color:#0369a1; font-size:0.75rem;">ดอกคำใต้</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background:#dcfce7; color:#15803d; font-size:0.75rem;">ท่าวังทอง</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px 12px; font-weight: 500;">
                                                <?php echo htmlspecialchars($l['emp_name']); ?>
                                            </td>
                                            <td style="padding: 10px 12px; text-align: center;">
                                                <span class="badge-act <?php echo $act_class; ?>">
                                                    <?php 
                                                        if ($act === 'CREATE')  echo '🟢 สร้างบิล';
                                                        elseif ($act === 'EDIT')    echo '✏️ แก้ไขข้อมูล';
                                                        elseif ($act === 'CANCEL')  echo '🚫 ยกเลิกบิล';
                                                        elseif ($act === 'REPRINT') echo '🖨️ พิมพ์ซ้ำ';
                                                        elseif ($act === 'PRINT')   echo '🔵 พิมพ์บิล';
                                                        else echo htmlspecialchars($l['action_label']);
                                                    ?>
                                                </span>
                                            </td>
                                            <td style="padding: 10px 14px; line-height: 1.45;">
                                                <div style="color: var(--text-main);">
                                                    <?php echo htmlspecialchars($l['changes_summary']); ?>
                                                </div>
                                                <?php if (!empty($l['ip_address'])): ?>
                                                    <small style="color: #94a3b8; font-size: 0.73rem;">IP: <?php echo htmlspecialchars($l['ip_address']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px 12px; color: #b45309; font-weight: 500;">
                                                <?php echo !empty($l['reason']) ? htmlspecialchars($l['reason']) : '<span style="color:#cbd5e1;">-</span>'; ?>
                                            </td>
                                            <td style="padding: 10px 12px; text-align: center; white-space: nowrap;">
                                                <button type="button" class="btn btn-secondary btn-sm" style="padding: 3px 8px; font-size: 0.78rem;" onclick="viewTimeline(<?php echo $l['id_pay']; ?>)">
                                                    📜 ไทม์ไลน์
                                                </button>
                                                <?php if ($act === 'EDIT' && !empty($l['old_data'])): ?>
                                                    <button type="button" class="btn btn-primary btn-sm" style="padding: 3px 8px; font-size: 0.78rem; background: #f59e0b; border-color: #f59e0b;" onclick="viewDiff(<?php echo $l['id_log']; ?>)">
                                                        🔍 ตรวจค่า
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div style="padding: 1rem 1.25rem; border-top: 1px solid var(--border); display: flex; justify-content: center; gap: 0.35rem; flex-wrap: wrap;">
                            <?php if ($page > 1): ?>
                                <a href="<?php echo $build_query(array('page' => $page - 1)); ?>" class="btn btn-secondary btn-sm">« ก่อนหน้า</a>
                            <?php endif; ?>
                            
                            <?php 
                            $p_start = max(1, $page - 2);
                            $p_end   = min($total_pages, $page + 2);
                            for ($pi = $p_start; $pi <= $p_end; $pi++): 
                            ?>
                                <a href="<?php echo $build_query(array('page' => $pi)); ?>" class="btn btn-sm <?php echo $pi == $page ? 'btn-primary' : 'btn-secondary'; ?>">
                                    <?php echo $pi; ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="<?php echo $build_query(array('page' => $page + 1)); ?>" class="btn btn-secondary btn-sm">ถัดไป »</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div> <!-- .content-body -->
        </div> <!-- .main-wrapper -->
    </div> <!-- .app-container -->

    <!-- Modal 1: Bill Timeline Modal -->
    <div class="modal-overlay" id="timelineModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 680px; max-height: 90vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0;">
                    📜 ลำดับเหตุการณ์ของบิล <span id="timelineBillNo" style="color: var(--primary);"></span>
                </h3>
                <button type="button" onclick="closeTimeline()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>
            
            <!-- Bill Overview Card -->
            <div id="timelineBillOverview" style="background: #f8fafc; border: 1px solid var(--border); border-radius: 8px; padding: 0.85rem 1rem; margin-bottom: 1.25rem; font-size: 0.88rem;">
                กำลังโหลดข้อมูล...
            </div>

            <!-- Timeline -->
            <div id="timelineItemsContainer" class="timeline-container">
                กำลังโหลดประวัติ...
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.5rem; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeTimeline()">ปิดหน้าต่าง</button>
                <a href="#" id="timelineReceiptLink" target="_blank" class="btn btn-primary" style="display: none;">🖨️ เปิดดูใบเสร็จ</a>
            </div>
        </div>
    </div>

    <!-- Modal 2: Diff Inspection Modal -->
    <div class="modal-overlay" id="diffModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; padding: 1rem;">
        <div style="background: #ffffff; border-radius: 12px; width: 100%; max-width: 750px; max-height: 90vh; overflow-y: auto; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; margin-bottom: 1rem;">
                <h3 style="font-size: 1.15rem; font-weight: 700; color: #b45309; margin: 0;">
                    🔍 เปรียบเทียบข้อมูลก่อน - หลังแก้ไข (Diff Viewer)
                </h3>
                <button type="button" onclick="closeDiff()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8;">&times;</button>
            </div>
            
            <div id="diffContent" style="font-size: 0.88rem;">
                กำลังโหลดการเปรียบเทียบ...
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <button type="button" class="btn btn-secondary" onclick="closeDiff()">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        function handlePeriodChange() {
            var val = document.getElementById('filter_type').value;
            var customFields = document.getElementById('customDateFields');
            if (val === 'custom') {
                customFields.style.display = 'flex';
            } else {
                customFields.style.display = 'none';
                document.getElementById('filterForm').submit();
            }
        }

        function viewTimeline(idPay) {
            document.getElementById('timelineBillNo').innerText = '#' + idPay;
            document.getElementById('timelineModal').style.display = 'flex';
            document.getElementById('timelineBillOverview').innerHTML = 'กำลังโหลดข้อมูลบิล...';
            document.getElementById('timelineItemsContainer').innerHTML = 'กำลังโหลดประวัติ...';
            document.getElementById('timelineReceiptLink').style.display = 'none';

            fetch('audit_log.php?ajax_timeline=1&id_pay=' + idPay)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.success) {
                        document.getElementById('timelineItemsContainer').innerHTML = '<div style="color:red;">' + (data.message || 'เกิดข้อผิดพลาดในการโหลด') + '</div>';
                        return;
                    }
                    
                    // Render overview
                    var p = data.payment;
                    if (p) {
                        var statusStr = '<span class="badge" style="background:#e0f2fe; color:#0369a1;">ร่างบิล (Draft)</span>';
                        if (p.pay_status == 2) statusStr = '<span class="badge badge-success">พิมพ์แล้ว (Completed)</span>';
                        if (p.pay_status == 3) statusStr = '<span class="badge badge-danger">ยกเลิกแล้ว (Cancelled)</span>';

                        var overviewHtml = '<div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">' +
                            '<div><strong>ลูกค้า:</strong> ' + (p.cus_name || '-') + ' (' + (p.cus_phone || '-') + ')</div>' +
                            '<div><strong>ทะเบียนรถ:</strong> ' + (p.car_regis_id || '-') + ' ' + (p.PROVINCE_NAME || '') + '</div>' +
                            '<div><strong>สาขา:</strong> ' + (p.branch_name || '-') + '</div>' +
                            '<div><strong>ยอดเงินสุทธิ:</strong> <strong style="color:var(--primary);">' + Number(p.pay_total).toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บาท</strong></div>' +
                            '<div><strong>สถานะปัจจุบัน:</strong> ' + statusStr + '</div>' +
                            '</div>';
                        document.getElementById('timelineBillOverview').innerHTML = overviewHtml;
                        
                        var receiptBtn = document.getElementById('timelineReceiptLink');
                        receiptBtn.href = '../receipt.php?id_pay=' + p.id_pay;
                        receiptBtn.style.display = 'inline-flex';
                    } else {
                        document.getElementById('timelineBillOverview').innerHTML = '<div style="color:#64748b;">(ไม่พบข้อมูลบิลในตารางปัจจุบัน อาจถูกลบออกจากระบบ)</div>';
                    }

                    // Render timeline items
                    if (!data.logs || data.logs.length === 0) {
                        document.getElementById('timelineItemsContainer').innerHTML = '<div style="color:#64748b;">ยังไม่มีประวัติการบันทึก</div>';
                        return;
                    }

                    var itemsHtml = '';
                    data.logs.forEach(function(item) {
                        var actClass = 'act-' + item.action;
                        itemsHtml += '<div class="timeline-item ' + actClass + '">' +
                            '<div class="timeline-dot"></div>' +
                            '<div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:0.75rem 1rem; box-shadow:0 1px 2px rgba(0,0,0,0.05);">' +
                                '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.35rem;">' +
                                    '<span class="badge-act ' + actClass + '">' + item.action_label + '</span>' +
                                    '<span style="font-size:0.78rem; color:#94a3b8;">' + item.created_at + '</span>' +
                                '</div>' +
                                '<div style="font-weight:600; font-size:0.88rem; color:#1e293b; margin-bottom:0.25rem;">' +
                                    'ผู้ทำรายการ: ' + item.emp_name + (item.branch_name ? ' (' + item.branch_name + ')' : '') +
                                '</div>' +
                                '<div style="font-size:0.85rem; color:#334155; line-height:1.45;">' +
                                    item.changes_summary +
                                '</div>' +
                                (item.reason ? '<div style="margin-top:0.35rem; font-size:0.82rem; color:#b45309; background:#fffbeb; padding:4px 8px; border-radius:4px;">เหตุผล: ' + item.reason + '</div>' : '') +
                                (item.ip_address ? '<div style="font-size:0.73rem; color:#94a3b8; margin-top:0.25rem;">IP: ' + item.ip_address + '</div>' : '') +
                            '</div>' +
                        '</div>';
                    });
                    document.getElementById('timelineItemsContainer').innerHTML = itemsHtml;
                })
                .catch(function(err) {
                    document.getElementById('timelineItemsContainer').innerHTML = '<div style="color:red;">โหลดข้อมูลไม่สำเร็จ: ' + err + '</div>';
                });
        }

        function closeTimeline() {
            document.getElementById('timelineModal').style.display = 'none';
        }

        function viewDiff(idLog) {
            document.getElementById('diffModal').style.display = 'flex';
            document.getElementById('diffContent').innerHTML = 'กำลังโหลดการเปรียบเทียบข้อมูล...';

            fetch('audit_log.php?ajax_diff=1&id_log=' + idLog)
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data.success) {
                        document.getElementById('diffContent').innerHTML = '<div style="color:red;">' + (data.message || 'เกิดข้อผิดพลาด') + '</div>';
                        return;
                    }
                    var log = data.log;
                    var oldD = data.old || {};
                    var newD = data.new || {};

                    var fields = [
                        { key: 'cus_name', label: 'ชื่อลูกค้า' },
                        { key: 'cus_phone', label: 'เบอร์โทร' },
                        { key: 'cus_addr', label: 'ที่อยู่' },
                        { key: 'car_regis_id', label: 'ทะเบียนรถ' },
                        { key: 'serv_1', label: 'พ.ร.บ. (serv_1)', isNum: true },
                        { key: 'serv_2', label: 'ตรวจสภาพ (ตรอ.)', isNum: true },
                        { key: 'serv_2_1', label: 'เลขที่ ตรอ.' },
                        { key: 'serv_3', label: 'ฝากต่อภาษี', isNum: true },
                        { key: 'serv_4', label: 'บริการอื่นๆ (4)', isNum: true },
                        { key: 'serv_5', label: 'บริการอื่นๆ (5)', isNum: true },
                        { key: 'serv_6', label: 'ประกันภัย', isNum: true },
                        { key: 'serv_7', label: 'บริการอื่นๆ (7)', isNum: true },
                        { key: 'discount', label: 'ส่วนลด', isNum: true },
                        { key: 'pay_sum', label: 'ยอดรวมก่อนลด', isNum: true },
                        { key: 'pay_total', label: 'ยอดชำระสุทธิ', isNum: true },
                        { key: 'pay_type', label: 'วิธีชำระเงิน' },
                        { key: 'pay_cash', label: 'เงินสด', isNum: true },
                        { key: 'pay_transfer', label: 'เงินโอน', isNum: true }
                    ];

                    var html = '<div style="margin-bottom:1rem; background:#f8fafc; padding:0.75rem 1rem; border-radius:6px; border:1px solid #e2e8f0;">' +
                        '<div><strong>บิลเลขที่:</strong> #' + log.id_pay + ' | <strong>ผู้แก้ไข:</strong> ' + log.emp_name + ' | <strong>วันเวลา:</strong> ' + log.created_at + '</div>' +
                        '<div style="color:#b45309; margin-top:0.25rem;"><strong>เหตุผลในการแก้ไข:</strong> ' + (log.reason || '-') + '</div>' +
                        '</div>';

                    html += '<table class="diff-table">' +
                        '<thead><tr><th style="width:25%;">ฟิลด์ข้อมูล</th><th style="width:37%;">ค่าเดิม (ก่อนแก้)</th><th style="width:38%;">ค่าใหม่ (หลังแก้)</th></tr></thead>' +
                        '<tbody>';

                    var diffCount = 0;
                    fields.forEach(function(f) {
                        var ov = oldD[f.key] !== undefined ? oldD[f.key] : '';
                        var nv = newD[f.key] !== undefined ? newD[f.key] : '';
                        var isDiff = false;

                        if (f.isNum) {
                            var of = parseFloat(ov) || 0;
                            var nf = parseFloat(nv) || 0;
                            isDiff = Math.abs(of - nf) > 0.001;
                            ov = of.toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บ.';
                            nv = nf.toLocaleString('th-TH', {minimumFractionDigits: 2}) + ' บ.';
                        } else if (f.key === 'pay_type') {
                            var ptMap = {1:'เงินสด', 2:'เงินโอน', 3:'ผสม'};
                            isDiff = (ov != nv);
                            ov = ptMap[ov] || ov;
                            nv = ptMap[nv] || nv;
                        } else {
                            isDiff = (String(ov).trim() !== String(nv).trim());
                        }

                        if (isDiff) {
                            diffCount++;
                            html += '<tr class="diff-changed">' +
                                '<td>' + f.label + '</td>' +
                                '<td style="text-decoration:line-through; color:#991b1b;">' + (ov || '-') + '</td>' +
                                '<td style="color:#166534; font-weight:700;">' + (nv || '-') + ' ⬅ ใหม่</td>' +
                                '</tr>';
                        }
                    });

                    if (diffCount === 0) {
                        html += '<tr><td colspan="3" style="text-align:center; padding:1.5rem; color:#64748b;">ไม่มีข้อมูลตัวเลขหลักที่เปลี่ยนแปลง หรือไม่มี Snapshot เดิม</td></tr>';
                    }

                    html += '</tbody></table>';
                    document.getElementById('diffContent').innerHTML = html;
                })
                .catch(function(err) {
                    document.getElementById('diffContent').innerHTML = '<div style="color:red;">โหลดข้อมูลไม่สำเร็จ: ' + err + '</div>';
                });
        }

        function closeDiff() {
            document.getElementById('diffModal').style.display = 'none';
        }

        // Close on clicking overlay outside container
        window.onclick = function(event) {
            var tModal = document.getElementById('timelineModal');
            var dModal = document.getElementById('diffModal');
            if (event.target === tModal) closeTimeline();
            if (event.target === dModal) closeDiff();
        };
    </script>
</body>
</html>
