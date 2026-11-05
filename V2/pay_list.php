<?php
// pay_list.php — Sales Report (Clerk view) for Bovorn Service V2
date_default_timezone_set('Asia/Bangkok');
require("db_connect.php");
check_login([2]);

$id_branch = $_SESSION['id_branch'];
$branch_label = $id_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง';

$search_active = isset($_GET['search_active']) ? intval($_GET['search_active']) : 0;
$filter_type   = isset($_GET['filter_type'])   ? $_GET['filter_type']           : 'today';

if ($filter_type === 'today') {
    $day = intval(date('d')); $day2 = 0;
    $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'month') {
    $day = 0; $day2 = 0;
    $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'year') {
    $day = 0; $day2 = 0; $month = 0; $year = intval(date('Y')) + 543;
} else {
    $day   = isset($_GET['day'])   ? intval($_GET['day'])   : 0;
    $day2  = isset($_GET['day2'])  ? intval($_GET['day2'])  : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $year  = isset($_GET['year'])  ? intval($_GET['year'])  : (date('Y') + 543);
}

$payments = []; $total_records = 0; $total_pages = 0; $page = 1; $limit = 20;
$total_sum = $sum_1 = $sum_2 = $sum_3 = $sum_4 = $sum_5 = $sum_6 = $sum_7 = $sum_disc = 0;
$cash_sum = $transfer_sum = 0;

$months_th = [
    1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
    5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
    9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
];

if ($day > 0 || $month > 0 || $year > 0) {
    try {
        $conditions = ["p.id_branch = :id_branch", "p.pay_status != 3"];
        $params = [':id_branch' => $id_branch];

        if ($day > 0 && $day2 == 0)       { $conditions[] = "p.pay_day = :day";  $params[':day'] = $day; }
        elseif ($day > 0 && $day2 > 0)    { $conditions[] = "p.pay_day >= :day AND p.pay_day <= :day2"; $params[':day'] = $day; $params[':day2'] = $day2; }
        if ($month > 0)                    { $conditions[] = "p.pay_month = :month"; $params[':month'] = $month; }
        if ($year > 0)                     { $conditions[] = "p.pay_year = :year";  $params[':year'] = $year - 543; }

        $where_sql = implode(" AND ", $conditions);

        $sums_res = $pdo->prepare("SELECT COUNT(*) as total_records,
            SUM(p.serv_1) as sum_1, SUM(p.serv_2) as sum_2, SUM(p.serv_3) as sum_3,
            SUM(p.serv_4) as sum_4, SUM(p.serv_5) as sum_5, SUM(p.serv_6) as sum_6,
            SUM(p.serv_7) as sum_7, SUM(p.discount) as sum_disc, SUM(p.pay_total) as total_sum,
            SUM(COALESCE(p.pay_cash, 0)) as cash_sum,
            SUM(COALESCE(p.pay_transfer, 0)) as transfer_sum
            FROM `payment` p WHERE $where_sql");
        $sums_res->execute($params);
        $sr = $sums_res->fetch(PDO::FETCH_ASSOC);

        if ($sr) {
            $total_records = intval($sr['total_records']);
            $sum_1 = floatval($sr['sum_1']); $sum_2 = floatval($sr['sum_2']);
            $sum_3 = floatval($sr['sum_3']); $sum_4 = floatval($sr['sum_4']);
            $sum_5 = floatval($sr['sum_5']); $sum_6 = floatval($sr['sum_6']);
            $sum_7 = floatval($sr['sum_7']); $sum_disc = floatval($sr['sum_disc']);
            $total_sum = floatval($sr['total_sum']);
            $cash_sum = floatval($sr['cash_sum']);
            $transfer_sum = floatval($sr['transfer_sum']);
        }

        $total_pages = ceil($total_records / $limit);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("SELECT p.*, b.branch_name, c.car_type_name, pr.PROVINCE_NAME as province_name
            FROM `payment` p
            JOIN `branch` b  ON p.id_branch   = b.id_branch
            JOIN `car_type` c ON p.id_car_type = c.id_car_type
            JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
            WHERE $where_sql ORDER BY p.id_pay DESC LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (\PDOException $e) { $db_error = $e->getMessage(); }
}

// Build a human-readable period label
function period_label($filter_type, $day, $day2, $month, $year, $months_th) {
    if ($filter_type === 'today')  return 'วันที่ ' . date('d') . ' ' . $months_th[intval(date('m'))] . ' ' . (date('Y') + 543);
    if ($filter_type === 'month')  return 'เดือน ' . $months_th[intval(date('m'))] . ' ' . (date('Y') + 543);
    if ($filter_type === 'year')   return 'ปี ' . (date('Y') + 543);
    $parts = [];
    if ($day > 0) $parts[] = 'วันที่ ' . $day . ($day2 > 0 ? ' – ' . $day2 : '');
    if ($month > 0) $parts[] = $months_th[$month];
    if ($year > 0) $parts[] = $year;
    return implode(' ', $parts) ?: 'ทุกช่วงเวลา';
}
$period_label = period_label($filter_type, $day, $day2, $month, $year, $months_th);
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงานการขาย - Bovorn Service V2</title>
<link rel="stylesheet" href="style.css">
<style>
/* ── Filter Bar ── */
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.75rem;
    box-shadow: var(--shadow-sm);
}
.filter-bar-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 1rem;
}
.filter-tabs {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}
.filter-tab {
    padding: 0.45rem 1.1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid var(--border);
    background: var(--bg-main);
    color: var(--text-muted);
    transition: var(--transition);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.filter-tab:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.filter-tab.active { background: var(--primary); border-color: var(--primary); color: var(--text-inverse); }

.custom-fields {
    display: flex;
    flex-wrap: wrap;
    gap: 0.85rem;
    align-items: flex-end;
    padding: 1rem;
    background: var(--bg-main);
    border-radius: var(--radius-md);
    border: 1px solid var(--border);
}
.modern-fg { display: flex; flex-direction: column; gap: 0.35rem; }
.modern-fg label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.modern-fg select, .modern-fg input {
    border: 1.5px solid var(--border);
    border-radius: var(--radius-md);
    padding: 0.5rem 0.75rem;
    font-size: 0.9rem;
    background: var(--bg-card);
    color: var(--text-main);
    transition: var(--transition);
    min-width: 110px;
}
.modern-fg select:focus, .modern-fg input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px var(--primary-light);
    outline: none;
}

/* ── Summary Metric Cards ── */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.75rem;
}
.metric-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.1rem 1.25rem;
    box-shadow: var(--shadow-sm);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: var(--transition);
}
.metric-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.metric-icon {
    width: 44px; height: 44px;
    border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.metric-body { flex: 1; min-width: 0; }
.metric-label { font-size: 0.775rem; font-weight: 600; color: var(--text-muted); margin-bottom: 3px; white-space: nowrap; }
.metric-value { font-size: 1.35rem; font-weight: 800; color: var(--text-main); line-height: 1.2; }
.metric-unit  { font-size: 0.8rem; font-weight: 500; color: var(--text-muted); margin-left: 2px; }

/* ── Table ── */
.report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.report-table thead tr { background: var(--primary); color: #fff; }
.report-table thead th {
    padding: 10px 12px;
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: 0.3px;
    white-space: nowrap;
}
.report-table thead th:first-child { border-radius: 8px 0 0 0; }
.report-table thead th:last-child  { border-radius: 0 8px 0 0; }
.report-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; }
.report-table tbody tr:hover td { background: var(--primary-light); }
.report-table tbody td { padding: 9px 12px; color: var(--text-main); vertical-align: middle; }
.report-table .num { text-align: right; font-variant-numeric: tabular-nums; }
.report-table .center { text-align: center; }
.report-table tfoot tr { background: linear-gradient(135deg, #1e3a5f, #2d5a8e); color: #fff; }
.report-table tfoot td {
    padding: 10px 12px;
    font-weight: 700;
    font-size: 0.85rem;
    font-variant-numeric: tabular-nums;
}
.report-table tfoot td:first-child { border-radius: 0 0 0 8px; }
.report-table tfoot td:last-child  { border-radius: 0 0 8px 0; }

/* Plate badge */
.plate-pill {
    display: inline-block;
    background: #1e3a5f;
    color: #fff;
    border-radius: 4px;
    padding: 1px 7px;
    font-size: 0.78rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    white-space: nowrap;
}

/* Status badge */
.status-pill {
    display: inline-block;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 0.72rem;
    font-weight: 700;
    white-space: nowrap;
}
.status-draft     { background: #fef3c7; color: #92400e; }
.status-completed { background: #d1fae5; color: #065f46; }

/* ── Inspect button ── */
.btn-inspect {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 12px;
    font-size: 0.78rem;
    font-weight: 600;
    border-radius: 20px;
    cursor: pointer;
    border: 1.5px solid var(--primary);
    background: var(--primary-light);
    color: var(--primary);
    transition: var(--transition);
    white-space: nowrap;
}
.btn-inspect:hover { background: var(--primary); color: #fff; }

/* ── Pagination ── */
.pagination { display: flex; justify-content: center; align-items: center; gap: 0.35rem; padding: 1.25rem 0 0.5rem; }
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 8px;
    font-size: 0.85rem; font-weight: 600;
    border-radius: var(--radius-md);
    border: 1.5px solid var(--border);
    background: var(--bg-card);
    color: var(--text-main);
    text-decoration: none;
    transition: var(--transition);
    cursor: pointer;
}
.pg-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.pg-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.pg-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.pg-dots { color: var(--text-muted); padding: 0 4px; font-weight: 600; align-self: center; }

/* ── Period header ── */
.period-banner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-bottom: 1.25rem;
}
.period-label {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--primary-light);
    border: 1px solid var(--primary);
    color: var(--primary);
    border-radius: 20px;
    padding: 4px 14px;
    font-size: 0.875rem;
    font-weight: 700;
}

/* ── Modal ── */
.modal-overlay {
    position: fixed; inset: 0;
    background: rgba(15,23,42,0.55);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: none;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.25s ease;
}
.modal-overlay.active { display: flex; opacity: 1; }
.modal-box {
    background: var(--bg-card);
    border-radius: var(--radius-xl);
    border: 1px solid var(--border);
    width: 95%; max-width: 680px;
    max-height: 90vh; overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,0.2);
    animation: popIn 0.28s cubic-bezier(0.34,1.56,0.64,1);
}
@keyframes popIn {
    from { transform: scale(0.92) translateY(16px); opacity: 0; }
    to   { transform: scale(1) translateY(0); opacity: 1; }
}
.modal-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    background: var(--bg-card);
    z-index: 1;
}
.modal-head h3 { font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin: 0; }
.modal-close {
    background: var(--bg-main); border: 1px solid var(--border);
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; cursor: pointer; color: var(--text-muted);
    transition: var(--transition);
}
.modal-close:hover { background: var(--danger); color: #fff; border-color: var(--danger); }
.modal-body { padding: 1.5rem; }

/* Modal sections */
.modal-section { margin-bottom: 1.25rem; }
.modal-section-title {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    margin-bottom: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.modal-section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }
.modal-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
.modal-field { display: flex; flex-direction: column; gap: 0.2rem; }
.modal-field.full { grid-column: span 2; }
.field-label { font-size: 0.72rem; font-weight: 600; color: var(--text-muted); }
.field-value { font-size: 0.9rem; font-weight: 600; color: var(--text-main); }

/* Modal service table */
.modal-srv-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; border-radius: var(--radius-md); overflow: hidden; }
.modal-srv-table th { background: var(--bg-main); padding: 7px 10px; font-size: 0.78rem; text-align: left; color: var(--text-muted); font-weight: 700; border-bottom: 2px solid var(--border); }
.modal-srv-table td { padding: 7px 10px; border-bottom: 1px solid var(--border); vertical-align: middle; }
.modal-srv-table tr:last-child td { border-bottom: none; }
.modal-srv-table .srv-amt { text-align: right; font-weight: 600; font-variant-numeric: tabular-nums; }

/* Total summary bar */
.modal-total-bar {
    display: flex; justify-content: space-between; align-items: center;
    background: linear-gradient(135deg, #1e3a5f, #2d5a8e);
    color: #fff;
    border-radius: var(--radius-md);
    padding: 1rem 1.25rem;
    margin-top: 1rem;
}
.modal-total-label { font-size: 0.9rem; font-weight: 600; opacity: 0.9; }
.modal-total-amount { font-size: 1.6rem; font-weight: 800; letter-spacing: 0.5px; }

.modal-foot {
    display: flex; gap: 0.75rem; justify-content: flex-end;
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--text-muted);
}
.empty-state .icon { font-size: 3.5rem; margin-bottom: 1rem; }
.empty-state p { font-size: 1rem; font-weight: 500; }
</style>
</head>
<body>
<div class="app-container">
    <?php include("sidebar.php"); ?>
    <div class="main-wrapper">
        <div class="header">
            <div class="header-title">📊 รายงานการเข้าใช้บริการและยอดขาย</div>
            <div style="font-size:0.875rem; color:var(--text-muted);">
                สาขา: <strong style="color:var(--primary);"><?php echo $branch_label; ?></strong>
            </div>
        </div>

        <div class="content-body">

            <?php if (isset($db_error)): ?>
            <div style="background:#fef2f2; border:1px solid #fca5a5; color:#dc2626; padding:0.75rem 1rem; border-radius:var(--radius-md); margin-bottom:1.5rem; font-size:0.875rem;">
                ⚠️ <?php echo htmlspecialchars($db_error); ?>
            </div>
            <?php endif; ?>

            <!-- ── Filter Bar ── -->
            <div class="filter-bar">
                <div class="filter-bar-title">🔍 เลือกช่วงเวลา</div>
                <form action="pay_list.php" method="GET" id="filterForm">
                    <input type="hidden" name="search_active" value="1">
                    <input type="hidden" name="filter_type" id="filter_type_input" value="<?php echo htmlspecialchars($filter_type); ?>">

                    <!-- Quick tabs -->
                    <div class="filter-tabs">
                        <button type="button" class="filter-tab <?php echo $filter_type==='today'  ? 'active' : ''; ?>" onclick="setFilter('today')">📅 วันนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='month'  ? 'active' : ''; ?>" onclick="setFilter('month')">📆 เดือนนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='year'   ? 'active' : ''; ?>" onclick="setFilter('year')">🗓️ ปีนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='custom' ? 'active' : ''; ?>" onclick="setFilter('custom')">✏️ กำหนดเอง</button>
                    </div>

                    <!-- Custom date fields -->
                    <div id="customFields" style="display:<?php echo $filter_type==='custom' ? 'flex' : 'none'; ?>;" class="custom-fields">
                        <div class="modern-fg">
                            <label>เริ่มวันที่</label>
                            <select name="day">
                                <option value="0">— วัน —</option>
                                <?php for ($i=1;$i<=31;$i++) echo '<option value="'.$i.'"'.($day==$i?' selected':'').'>'.$i.'</option>'; ?>
                            </select>
                        </div>
                        <div class="modern-fg">
                            <label>ถึงวันที่</label>
                            <select name="day2">
                                <option value="0">— วัน —</option>
                                <?php for ($i=1;$i<=31;$i++) echo '<option value="'.$i.'"'.($day2==$i?' selected':'').'>'.$i.'</option>'; ?>
                            </select>
                        </div>
                        <div class="modern-fg">
                            <label>เดือน</label>
                            <select name="month">
                                <option value="0">— ทั้งหมด —</option>
                                <?php foreach ($months_th as $mn=>$mname) echo '<option value="'.$mn.'"'.($month==$mn?' selected':'').'>'.$mname.'</option>'; ?>
                            </select>
                        </div>
                        <div class="modern-fg">
                            <label>ปี พ.ศ.</label>
                            <select name="year">
                                <option value="0">— ปี —</option>
                                <?php for ($y=date('Y')+543;$y>=2557;$y--) echo '<option value="'.$y.'"'.($year==$y?' selected':'').'>'.$y.'</option>'; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="height:40px; padding:0 1.5rem; align-self:flex-end;">🔍 ค้นหา</button>
                    </div>
                </form>
            </div>

            <?php if ($total_records > 0): ?>

            <!-- ── Period Banner + Record count ── -->
            <div class="period-banner">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <div class="period-label">📅 <?php echo $period_label; ?></div>
                    <?php
                    $is_daily = false;
                    $p_day = 0; $p_month = 0; $p_year = 0;
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
                    if ($is_daily):
                    ?>
                        <button onclick="openTaxReferralPopup('print_tax_referral.php?day=<?php echo $p_day; ?>&month=<?php echo $p_month; ?>&year=<?php echo $p_year; ?>&id_branch=<?php echo $id_branch; ?>')" 
                           class="btn-print-tax">
                            🖨️ พิมพ์ใบส่งต่อภาษีรายวัน
                        </button>
                        <button onclick="openAccountReportPopup('daily_closing.php?day=<?php echo $p_day; ?>&month=<?php echo $p_month; ?>&year=<?php echo $p_year; ?>')"
                           class="btn-print-tax" style="background: linear-gradient(135deg, #2563eb, #1d4ed8); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);">
                            📒 สรุปส่งยอดรายวัน
                        </button>
                    <?php endif; ?>
                </div>
                <span style="font-size:0.875rem; color:var(--text-muted);">
                    พบ <strong style="color:var(--text-main);"><?php echo number_format($total_records); ?></strong> รายการ
                    <?php if ($total_pages > 1): ?> &nbsp;·&nbsp; หน้า <?php echo $page; ?>/<?php echo $total_pages; ?><?php endif; ?>
                </span>
            </div>

            <!-- ── Summary Metrics ── -->
            <div class="metrics-grid">
                <?php
                $metrics = [
                    ['💼', 'จำนวนรายการ',        number_format($total_records), 'รายการ', '#eff6ff', '#2563eb'],
                    ['📋', 'ค่า พ.ร.บ.',          number_format($sum_1),         'บาท',    '#f0fdf4', '#16a34a'],
                    ['🔍', 'ค่าตรวจสภาพ (ตรอ.)', number_format($sum_2),         'บาท',    '#fff7ed', '#ea580c'],
                    ['🏛️', 'ค่าภาษี (รวม)',       number_format($sum_3+$sum_4),  'บาท',    '#fdf4ff', '#9333ea'],
                    ['⚠️', 'ค่าปรับ+ค่าเล่ม',    number_format($sum_5+$sum_6),  'บาท',    '#fefce8', '#ca8a04'],
                    ['🏷️', 'ส่วนลด',              number_format($sum_disc),       'บาท',    '#fff1f2', '#e11d48'],
                    ['💵', 'ยอดเงินสด',          number_format($cash_sum),      'บาท',    '#fef3c7', '#d97706'],
                    ['📱', 'ยอดเงินโอน',          number_format($transfer_sum),  'บาท',    '#e0e7ff', '#4338ca'],
                    ['💰', 'ยอดรวมสุทธิ',         number_format($total_sum),     'บาท',    '#1e3a5f', '#fff'],
                ];
                foreach ($metrics as $m): ?>
                <div class="metric-card" style="<?php echo $m[3]==='บาท' && $m[0]==='💰' ? 'background:linear-gradient(135deg,#1e3a5f,#2d5a8e); border-color:#1e3a5f;' : ''; ?>">
                    <div class="metric-icon" style="background:<?php echo $m[4]; ?>;">
                        <?php echo $m[0]; ?>
                    </div>
                    <div class="metric-body">
                        <div class="metric-label" style="<?php echo $m[0]==='💰' ? 'color:rgba(255,255,255,0.75);' : ''; ?>"><?php echo $m[1]; ?></div>
                        <div class="metric-value" style="<?php echo $m[0]==='💰' ? 'color:#fff;' : 'color:'.$m[5].';'; ?>">
                            <?php echo $m[2]; ?><span class="metric-unit" style="<?php echo $m[0]==='💰' ? 'color:rgba(255,255,255,0.7);' : ''; ?>"><?php echo $m[3]; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- ── Transactions Table ── -->
            <div class="card" style="padding:0; overflow:hidden;">
                <div style="overflow-x:auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th class="center" style="width:46px;">#</th>
                                <th>ชื่อลูกค้า</th>
                                <th class="center">ทะเบียน</th>
                                <th>จังหวัด</th>
                                <th class="center" style="width:80px;">วันที่</th>
                                <th class="num" style="color:#bfdbfe;">สุทธิ (บาท)</th>
                                <th class="center">การชำระเงิน</th>
                                <th class="center">สถานะ</th>
                                <th class="center">รายละเอียด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = $offset + 1; foreach ($payments as $p): ?>
                            <tr>
                                <td class="center" style="color:var(--text-muted); font-size:0.8rem;"><?php echo $idx++; ?></td>
                                <td>
                                    <div style="font-weight:700; font-size:0.875rem;"><?php echo htmlspecialchars($p['cus_name']); ?></div>
                                    <div style="font-size:0.75rem; color:var(--text-muted);"><?php echo htmlspecialchars($p['cus_phone']); ?></div>
                                </td>
                                <td class="center"><span class="plate-pill"><?php echo htmlspecialchars($p['car_regis_id']); ?></span></td>
                                <td style="font-size:0.85rem; color:var(--text-muted);"><?php echo htmlspecialchars($p['province_name']); ?></td>
                                <td class="center" style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                                    <?php echo sprintf('%02d/%02d', $p['pay_day'], $p['pay_month']); ?><br>
                                    <span style="font-size:0.72rem;"><?php echo substr($p['pay_time'],0,5); ?> น.</span>
                                </td>
                                <td class="num" style="color:var(--primary); font-weight:700; font-size:0.95rem;"><?php echo number_format($p['pay_total']); ?></td>
                                <td class="center">
                                    <?php
                                    $pt = isset($p['pay_type']) ? intval($p['pay_type']) : 1;
                                    if ($pt == 2): ?>
                                        <span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">📱 เงินโอน</span>
                                    <?php elseif ($pt == 3): ?>
                                        <span class="badge" style="background-color: #d1fae5; color: #065f46; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">💵📱 สดและโอน</span>
                                    <?php else: ?>
                                        <span class="badge" style="background-color: #fef3c7; color: #d97706; font-size: 0.75rem; padding: 3px 8px; font-weight: 600;">💵 เงินสด</span>
                                    <?php endif; ?>
                                </td>
                                <td class="center">
                                    <?php if ($p['pay_status']==1): ?>
                                        <span class="status-pill status-draft">⏳ รอพิมพ์</span>
                                    <?php elseif ($p['pay_status']==2): ?>
                                        <span class="status-pill status-completed">✅ สำเร็จ</span>
                                    <?php endif; ?>
                                </td>
                                <td class="center">
                                    <button class="btn-inspect" onclick="showDetails(<?php echo $p['id_pay']; ?>)">🔎 ดูรายละเอียด</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" style="text-align:right; font-size:0.85rem; opacity:0.85;">รวมทั้งหมด (<?php echo number_format($total_records); ?> รายการ) | เงินสด: <?php echo number_format($cash_sum); ?> บาท | เงินโอน: <?php echo number_format($transfer_sum); ?> บาท</td>
                                <td class="num" style="color:#bfdbfe; font-size:1rem;"><?php echo number_format($total_sum); ?></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if ($total_pages > 1):
                    $qp = $_GET; unset($qp['page']);
                    $plink = function($n) use ($qp) {
                        return 'pay_list.php?' . http_build_query(array_merge($qp, ['page' => $n]));
                    };
                ?>
                <div class="pagination">
                    <?php if ($page>1): ?><a href="<?php echo $plink($page-1); ?>" class="pg-btn">‹ ก่อนหน้า</a><?php else: ?><span class="pg-btn disabled">‹ ก่อนหน้า</span><?php endif; ?>
                    <?php
                    $sp = max(1,$page-2); $ep = min($total_pages,$page+2);
                    if ($sp>1) { echo '<a href="'.$plink(1).'" class="pg-btn">1</a>'; if ($sp>2) echo '<span class="pg-dots">…</span>'; }
                    for ($i=$sp;$i<=$ep;$i++) echo '<a href="'.$plink($i).'" class="pg-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                    if ($ep<$total_pages) { if ($ep<$total_pages-1) echo '<span class="pg-dots">…</span>'; echo '<a href="'.$plink($total_pages).'" class="pg-btn">'.$total_pages.'</a>'; }
                    ?>
                    <?php if ($page<$total_pages): ?><a href="<?php echo $plink($page+1); ?>" class="pg-btn">ถัดไป ›</a><?php else: ?><span class="pg-btn disabled">ถัดไป ›</span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <div class="empty-state card">
                <div class="icon">📂</div>
                <p>ไม่พบรายการธุรกรรมในช่วงเวลาที่เลือก</p>
                <p style="font-size:0.875rem; margin-top:0.5rem; color:var(--text-muted);">ลองเปลี่ยนช่วงเวลาการค้นหา</p>
            </div>
            <?php endif; ?>

        </div><!-- /.content-body -->
    </div><!-- /.main-wrapper -->
</div><!-- /.app-container -->

<!-- ── Transaction Detail Modal ── -->
<div class="modal-overlay" id="txModal">
    <div class="modal-box">
        <div class="modal-head">
            <h3>🧾 รายละเอียดใบเสร็จ <span id="mBillNo" style="color:var(--primary);"></span></h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <div class="modal-body">
            <!-- Customer -->
            <div class="modal-section">
                <div class="modal-section-title">👤 ข้อมูลลูกค้า</div>
                <div class="modal-info-grid">
                    <div class="modal-field">
                        <span class="field-label">ชื่อ - นามสกุล</span>
                        <span class="field-value" id="mCusName">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">เบอร์โทรศัพท์</span>
                        <span class="field-value" id="mCusPhone">—</span>
                    </div>
                    <div class="modal-field full">
                        <span class="field-label">ที่อยู่</span>
                        <span class="field-value" id="mCusAddr">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">ทะเบียนรถ / จังหวัด</span>
                        <span class="field-value" id="mCarRegis">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">ประเภทรถ</span>
                        <span class="field-value" id="mCarType">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">วันที่ / เวลาทำรายการ</span>
                        <span class="field-value" id="mDateTime">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">สาขา</span>
                        <span class="field-value" id="mBranch">—</span>
                    </div>
                    <div class="modal-field">
                        <span class="field-label">ช่องทางชำระเงิน</span>
                        <span class="field-value" id="mPayType">—</span>
                    </div>
                </div>
            </div>
            <!-- Services -->
            <div class="modal-section">
                <div class="modal-section-title">⚙️ รายการค่าบริการ</div>
                <table class="modal-srv-table">
                    <thead>
                        <tr>
                            <th>รายการ</th>
                            <th style="text-align:right; width:110px;">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody id="mServRows"></tbody>
                </table>
                <div class="modal-total-bar">
                    <div>
                        <div class="modal-total-label">ยอดชำระสุทธิ</div>
                        <div style="font-size:0.8rem; opacity:0.75;" id="mDiscNote"></div>
                    </div>
                    <div class="modal-total-amount" id="mTotal">0 บาท</div>
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn btn-secondary" onclick="closeModal()">ปิด</button>
            <a href="#" id="mPrintLink" class="btn btn-primary" target="_blank">🖨️ พิมพ์ใบเสร็จ</a>
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

const payData = <?php echo json_encode($payments ?: [], JSON_UNESCAPED_UNICODE); ?>;

function setFilter(type) {
    document.getElementById('filter_type_input').value = type;
    document.getElementById('customFields').style.display = type === 'custom' ? 'flex' : 'none';
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');
    if (type !== 'custom') document.getElementById('filterForm').submit();
}

function fmt(n) { return parseFloat(n || 0).toLocaleString('th-TH', {minimumFractionDigits:2, maximumFractionDigits:2}); }

function showDetails(id) {
    const d = payData.find(r => parseInt(r.id_pay) === id);
    if (!d) return;

    document.getElementById('mBillNo').textContent   = '#' + d.id_pay;
    document.getElementById('mCusName').textContent  = d.cus_name;
    document.getElementById('mCusPhone').textContent = d.cus_phone;
    document.getElementById('mCusAddr').textContent  = d.cus_addr;
    document.getElementById('mCarRegis').innerHTML   = `<span style="background:#1e3a5f;color:#fff;border-radius:4px;padding:1px 7px;font-size:0.8rem;font-weight:700;">${d.car_regis_id}</span> &nbsp;${d.province_name}`;
    document.getElementById('mCarType').textContent  = d.car_type_name;
    document.getElementById('mDateTime').textContent = `${d.pay_day}/${d.pay_month}/${parseInt(d.pay_year)+543} เวลา ${d.pay_time.substring(0,5)} น.`;
    document.getElementById('mBranch').textContent   = d.branch_name || (parseInt(d.id_branch)===1 ? 'ดอกคำใต้' : 'ท่าวังทอง');
    const payTypeVal = parseInt(d.pay_type || 1);
    let payTypeBadge;
    if (payTypeVal === 2) {
        payTypeBadge = '<span class="badge" style="background-color: #e0e7ff; color: #4338ca; font-weight: 600; font-size: 0.8rem; padding: 2px 8px;">📱 เงินโอน</span>';
    } else if (payTypeVal === 3) {
        const pc = parseFloat(d.pay_cash || 0).toLocaleString('th-TH', {minimumFractionDigits:2});
        const pt = parseFloat(d.pay_transfer || 0).toLocaleString('th-TH', {minimumFractionDigits:2});
        payTypeBadge = '<span class="badge" style="background-color: #d1fae5; color: #065f46; font-weight: 600; font-size: 0.8rem; padding: 2px 8px;">💵📱 สดและโอน</span>'
                     + '<div style="font-size:0.75rem; color:var(--text-muted); margin-top:3px;">💵 ' + pc + ' | 📱 ' + pt + ' บาท</div>';
    } else {
        payTypeBadge = '<span class="badge" style="background-color: #fef3c7; color: #d97706; font-weight: 600; font-size: 0.8rem; padding: 2px 8px;">💵 เงินสด</span>';
    }
    document.getElementById('mPayType').innerHTML = payTypeBadge;

    // Build service rows (non-zero only)
    const servDefs = [
        { label:'ต่อ พ.ร.บ. คุ้มครองผู้ประสบภัยจากรถ', key:'serv_1', note:'' },
        { label:'ตรวจสภาพรถ (ตรอ.)', key:'serv_2', note: d.serv_2_1 ? ` — เลขที่ ${d.serv_2_1}` : '' },
        { label:'ต่อภาษีประจำปี (กรณีปกติ)', key:'serv_3', note:'' },
        { label:'ต่อภาษีย้อนหลัง', key:'serv_4', note: parseInt(d.serv_4_1)>0 ? ` — ${d.serv_4_1} ปี (${d.serv_4_2})` : '' },
        { label:'ค่าปรับล่าช้าภาษี', key:'serv_5', note:'' },
        { label:'ค่าสมุดเล่มทะเบียน', key:'serv_6', note:'' },
        { label:'ค่าบริการอื่นๆ', key:'serv_7', note: d.serv_7_1 ? `: ${d.serv_7_1}` : '' },
    ];

    let rows = '';
    servDefs.forEach((s, i) => {
        const amt = parseFloat(d[s.key] || 0);
        if (amt > 0) {
            rows += `<tr>
                <td>${i+1}. ${s.label}<span style="color:var(--text-muted); font-size:0.8rem;">${s.note}</span></td>
                <td class="srv-amt">${fmt(amt)} บาท</td>
            </tr>`;
        }
    });
    if (!rows) rows = '<tr><td colspan="2" style="text-align:center; color:var(--text-muted); padding:1rem;">— ไม่มีรายการ —</td></tr>';
    document.getElementById('mServRows').innerHTML = rows;

    // Total
    document.getElementById('mTotal').textContent = fmt(d.pay_total) + ' บาท';
    const disc = parseFloat(d.discount || 0);
    document.getElementById('mDiscNote').textContent = disc > 0 ? `หักส่วนลด ${fmt(disc)} บาท` : '';

    document.getElementById('mPrintLink').href = 'receipt.php?id_pay=' + d.id_pay;

    const modal = document.getElementById('txModal');
    modal.style.display = 'flex';
    setTimeout(() => modal.classList.add('active'), 10);
}

function closeModal() {
    const modal = document.getElementById('txModal');
    modal.classList.remove('active');
    setTimeout(() => modal.style.display = 'none', 280);
}

document.getElementById('txModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});
</script>
</body>
</html>
