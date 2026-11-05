<?php
// car_list.php — Vehicle Inspection (ตรอ.) Report for Bovorn Service V2
date_default_timezone_set('Asia/Bangkok');
require("db_connect.php");
check_login([2]);

$id_branch    = $_SESSION['id_branch'];
$branch_label = $id_branch == 1 ? 'ดอกคำใต้' : 'ท่าวังทอง';

$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'today';

if ($filter_type === 'today') {
    $day = intval(date('d')); $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'month') {
    $day = 0; $month = intval(date('m')); $year = intval(date('Y')) + 543;
} elseif ($filter_type === 'year') {
    $day = 0; $month = 0; $year = intval(date('Y')) + 543;
} else {
    $day   = isset($_GET['day'])   ? intval($_GET['day'])   : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $year  = isset($_GET['year'])  ? intval($_GET['year'])  : (date('Y') + 543);
}

$inspections      = [];
$car_type_summary = [];
$province_summary = [];
$total_records    = 0;
$total_pages      = 0;
$page  = 1;
$limit = 20;

$months_th = [
    1=>'มกราคม',2=>'กุมภาพันธ์',3=>'มีนาคม',4=>'เมษายน',
    5=>'พฤษภาคม',6=>'มิถุนายน',7=>'กรกฎาคม',8=>'สิงหาคม',
    9=>'กันยายน',10=>'ตุลาคม',11=>'พฤศจิกายน',12=>'ธันวาคม'
];

if ($day > 0 || $month > 0 || $year > 0) {
    try {
        $conditions = ["p.id_branch = :id_branch", "p.serv_2 != 0", "p.pay_status = 2"];
        $params = [':id_branch' => $id_branch];

        if ($day   > 0) { $conditions[] = "p.pay_day = :day";     $params[':day']   = $day; }
        if ($month > 0) { $conditions[] = "p.pay_month = :month"; $params[':month'] = $month; }
        if ($year  > 0) { $conditions[] = "p.pay_year = :year";   $params[':year']  = $year - 543; }

        $where_sql = implode(" AND ", $conditions);

        // Count
        $sc = $pdo->prepare("SELECT COUNT(*) FROM `payment` p WHERE $where_sql");
        $sc->execute($params);
        $total_records = intval($sc->fetchColumn());

        // Pagination
        $total_pages = ceil($total_records / $limit);
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        if ($total_pages > 0 && $page > $total_pages) $page = $total_pages;
        $offset = ($page - 1) * $limit;

        // Detail rows
        $stmt = $pdo->prepare("SELECT p.cus_name, p.car_regis_id, p.pay_day, p.pay_month, p.pay_year,
                                      c.car_type_name, pr.PROVINCE_NAME, p.serv_2_1
                               FROM `payment` p
                               JOIN `car_type` c  ON p.id_car_type = c.id_car_type
                               JOIN `province` pr ON p.province_id  = pr.PROVINCE_ID
                               WHERE $where_sql ORDER BY p.id_pay DESC
                               LIMIT :limit OFFSET :offset");
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limit',  $limit,  PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Car type summary
        $sc2 = $pdo->prepare("SELECT c.car_type_name, COUNT(*) as cnt
                               FROM `payment` p JOIN `car_type` c ON p.id_car_type = c.id_car_type
                               WHERE $where_sql GROUP BY c.id_car_type ORDER BY cnt DESC");
        $sc2->execute($params);
        $car_type_summary = $sc2->fetchAll(PDO::FETCH_ASSOC);

        // Province summary
        $sc3 = $pdo->prepare("SELECT pr.PROVINCE_NAME, COUNT(*) as cnt
                               FROM `payment` p JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                               WHERE $where_sql GROUP BY p.province_id ORDER BY cnt DESC LIMIT 15");
        $sc3->execute($params);
        $province_summary = $sc3->fetchAll(PDO::FETCH_ASSOC);

    } catch (\PDOException $e) { $db_error = $e->getMessage(); }
}

// Period label
function period_label($ft, $day, $month, $year, $months_th) {
    if ($ft === 'today') return 'วันที่ ' . date('d') . ' ' . $months_th[intval(date('m'))] . ' ' . (date('Y') + 543);
    if ($ft === 'month') return 'เดือน ' . $months_th[intval(date('m'))] . ' ' . (date('Y') + 543);
    if ($ft === 'year')  return 'ปี ' . (date('Y') + 543);
    $p = [];
    if ($day > 0)   $p[] = 'วันที่ ' . $day;
    if ($month > 0) $p[] = $months_th[$month];
    if ($year > 0)  $p[] = $year;
    return implode(' ', $p) ?: 'ทุกช่วงเวลา';
}
$period_label = period_label($filter_type, $day, $month, $year, $months_th);

$max_car  = !empty($car_type_summary)  ? max(array_column($car_type_summary,  'cnt')) : 1;
$max_prov = !empty($province_summary)  ? max(array_column($province_summary,   'cnt')) : 1;
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>รายงาน ตรอ. - Bovorn Service V2</title>
<link rel="stylesheet" href="style.css">
<style>
/* ── Filter bar ── */
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.75rem;
    box-shadow: var(--shadow-sm);
}
.filter-bar-title {
    font-size: 0.8rem; font-weight: 700;
    color: var(--text-muted);
    text-transform: uppercase; letter-spacing: 0.8px;
    margin-bottom: 1rem;
}
.filter-tabs { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
.filter-tab {
    padding: 0.45rem 1.1rem; border-radius: 20px;
    font-size: 0.875rem; font-weight: 600;
    cursor: pointer; border: 1.5px solid var(--border);
    background: var(--bg-main); color: var(--text-muted);
    transition: var(--transition); text-decoration: none;
    display: inline-flex; align-items: center; gap: 0.35rem;
}
.filter-tab:hover  { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.filter-tab.active { background: var(--primary); border-color: var(--primary); color: var(--text-inverse); }

.custom-fields {
    display: flex; flex-wrap: wrap; gap: 0.85rem; align-items: flex-end;
    padding: 1rem; background: var(--bg-main);
    border-radius: var(--radius-md); border: 1px solid var(--border);
}
.modern-fg { display: flex; flex-direction: column; gap: 0.35rem; }
.modern-fg label { font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
.modern-fg select {
    border: 1.5px solid var(--border); border-radius: var(--radius-md);
    padding: 0.5rem 0.75rem; font-size: 0.9rem;
    background: var(--bg-card); color: var(--text-main);
    transition: var(--transition); min-width: 110px;
}
.modern-fg select:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); outline: none; }

/* ── Summary metric ── */
.metric-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 1.1rem 1.25rem;
    box-shadow: var(--shadow-sm); display: flex; align-items: center; gap: 1rem;
    transition: var(--transition);
}
.metric-card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
.metric-icon {
    width: 46px; height: 46px; border-radius: var(--radius-md);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; flex-shrink: 0;
}
.metric-label { font-size: 0.775rem; font-weight: 600; color: var(--text-muted); margin-bottom: 3px; }
.metric-value { font-size: 1.4rem; font-weight: 800; line-height: 1.2; }

/* ── Main table ── */
.report-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.report-table thead tr { background: linear-gradient(135deg, #1e3a5f, #2d5a8e); color: #fff; }
.report-table thead th {
    padding: 10px 14px; font-weight: 600; font-size: 0.8rem;
    letter-spacing: 0.3px; white-space: nowrap;
}
.report-table thead th:first-child { border-radius: 8px 0 0 0; }
.report-table thead th:last-child  { border-radius: 0 8px 0 0; }
.report-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; }
.report-table tbody tr:hover td { background: var(--primary-light); }
.report-table tbody td { padding: 9px 14px; color: var(--text-main); vertical-align: middle; }
.plate-pill {
    display: inline-block; background: #1e3a5f; color: #fff;
    border-radius: 4px; padding: 1px 8px;
    font-size: 0.8rem; font-weight: 700; letter-spacing: 0.4px; white-space: nowrap;
}
.tro-badge {
    display: inline-block; background: #d1fae5; color: #065f46;
    border: 1px solid #6ee7b7;
    border-radius: 6px; padding: 3px 10px;
    font-size: 0.82rem; font-weight: 700; letter-spacing: 0.5px;
}

/* ── Progress bar summary ── */
.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 1.25rem;
    margin-top: 1.5rem;
}
.summary-card {
    background: var(--bg-card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    box-shadow: var(--shadow-sm);
}
.summary-card-head {
    background: linear-gradient(135deg, #1e3a5f, #2d5a8e);
    color: #fff; padding: 0.85rem 1.25rem;
    font-size: 0.9rem; font-weight: 700;
    display: flex; align-items: center; gap: 0.5rem;
}
.summary-card-body { padding: 0.75rem 1.25rem; }
.summary-row {
    display: flex; align-items: center; gap: 0.75rem;
    padding: 0.6rem 0; border-bottom: 1px solid var(--border);
}
.summary-row:last-child { border-bottom: none; }
.summary-rank {
    width: 22px; height: 22px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.7rem; font-weight: 800; flex-shrink: 0;
}
.rank-1 { background: #fef08a; color: #713f12; }
.rank-2 { background: #e2e8f0; color: #334155; }
.rank-3 { background: #fed7aa; color: #7c2d12; }
.rank-n { background: var(--bg-main); color: var(--text-muted); }
.summary-name { flex: 1; font-size: 0.875rem; font-weight: 600; color: var(--text-main); min-width: 0; }
.summary-bar-wrap { flex: 2; background: var(--bg-main); border-radius: 99px; height: 8px; overflow: hidden; }
.summary-bar { height: 100%; border-radius: 99px; background: linear-gradient(90deg, #2563eb, #60a5fa); transition: width 0.6s ease; }
.summary-count {
    min-width: 50px; text-align: right;
    font-size: 0.875rem; font-weight: 700; color: var(--primary);
}

/* ── Pagination ── */
.pagination { display: flex; justify-content: center; align-items: center; gap: 0.35rem; padding: 1.25rem 0 0.5rem; }
.pg-btn {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 8px;
    font-size: 0.85rem; font-weight: 600; border-radius: var(--radius-md);
    border: 1.5px solid var(--border); background: var(--bg-card);
    color: var(--text-main); text-decoration: none; transition: var(--transition); cursor: pointer;
}
.pg-btn:hover  { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.pg-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
.pg-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.pg-dots { color: var(--text-muted); padding: 0 4px; font-weight: 600; align-self: center; }

/* ── Period banner ── */
.period-banner {
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1.25rem;
}
.period-label {
    display: inline-flex; align-items: center; gap: 0.5rem;
    background: var(--primary-light); border: 1px solid var(--primary);
    color: var(--primary); border-radius: 20px;
    padding: 4px 14px; font-size: 0.875rem; font-weight: 700;
}

/* ── Empty state ── */
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
.empty-state .icon { font-size: 3.5rem; margin-bottom: 1rem; }
.empty-state p { font-size: 1rem; font-weight: 500; }
</style>
</head>
<body>
<div class="app-container">
    <?php include("sidebar.php"); ?>
    <div class="main-wrapper">
        <div class="header">
            <div class="header-title">🔍 รายงานประเภทรถที่เข้ารับบริการ ตรอ.</div>
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
                <form action="car_list.php" method="GET" id="filterForm">
                    <input type="hidden" name="search_active" value="1">
                    <input type="hidden" name="filter_type" id="filter_type_input" value="<?php echo htmlspecialchars($filter_type); ?>">

                    <div class="filter-tabs">
                        <button type="button" class="filter-tab <?php echo $filter_type==='today'  ? 'active':''; ?>" onclick="setFilter('today')">📅 วันนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='month'  ? 'active':''; ?>" onclick="setFilter('month')">📆 เดือนนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='year'   ? 'active':''; ?>" onclick="setFilter('year')">🗓️ ปีนี้</button>
                        <button type="button" class="filter-tab <?php echo $filter_type==='custom' ? 'active':''; ?>" onclick="setFilter('custom')">✏️ กำหนดเอง</button>
                    </div>

                    <div id="customFields" style="display:<?php echo $filter_type==='custom' ? 'flex':'none'; ?>;" class="custom-fields">
                        <div class="modern-fg">
                            <label>วันที่</label>
                            <select name="day">
                                <option value="0">— วัน —</option>
                                <?php for ($i=1;$i<=31;$i++) echo '<option value="'.$i.'"'.($day==$i?' selected':'').'>'.$i.'</option>'; ?>
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

            <!-- ── Period banner + count ── -->
            <div class="period-banner">
                <div class="period-label">📅 <?php echo $period_label; ?></div>
                <span style="font-size:0.875rem; color:var(--text-muted);">
                    พบ <strong style="color:var(--text-main);"><?php echo number_format($total_records); ?></strong> รายการ
                    <?php if ($total_pages > 1): ?>&nbsp;·&nbsp; หน้า <?php echo $page; ?>/<?php echo $total_pages; ?><?php endif; ?>
                </span>
            </div>

            <!-- ── Metric cards ── -->
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1.75rem;">
                <div class="metric-card">
                    <div class="metric-icon" style="background:#eff6ff;">🚗</div>
                    <div>
                        <div class="metric-label">รายการตรวจสภาพ</div>
                        <div class="metric-value" style="color:#2563eb;"><?php echo number_format($total_records); ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">คัน</span></div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon" style="background:#f0fdf4;">🏷️</div>
                    <div>
                        <div class="metric-label">ประเภทรถที่พบ</div>
                        <div class="metric-value" style="color:#16a34a;"><?php echo count($car_type_summary); ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">ประเภท</span></div>
                    </div>
                </div>
                <div class="metric-card">
                    <div class="metric-icon" style="background:#fff7ed;">📍</div>
                    <div>
                        <div class="metric-label">จังหวัดที่พบ</div>
                        <div class="metric-value" style="color:#ea580c;"><?php echo count($province_summary); ?> <span style="font-size:0.85rem; font-weight:500; color:var(--text-muted);">จังหวัด</span></div>
                    </div>
                </div>
                <?php if (!empty($car_type_summary)): ?>
                <div class="metric-card" style="background:linear-gradient(135deg,#1e3a5f,#2d5a8e); border-color:#1e3a5f;">
                    <div class="metric-icon" style="background:rgba(255,255,255,0.15);">🥇</div>
                    <div>
                        <div class="metric-label" style="color:rgba(255,255,255,0.75);">ประเภทรถยอดนิยม</div>
                        <div class="metric-value" style="color:#fff; font-size:1.1rem;"><?php echo htmlspecialchars($car_type_summary[0]['car_type_name']); ?></div>
                        <div style="font-size:0.8rem; color:rgba(255,255,255,0.65);"><?php echo $car_type_summary[0]['cnt']; ?> คัน</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Inspection Records Table ── -->
            <div class="card" style="padding:0; overflow:hidden; margin-bottom:0;">
                <div style="background:linear-gradient(135deg,#1e3a5f,#2d5a8e); padding:0.9rem 1.25rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
                    <div style="color:#fff; font-weight:700; font-size:0.95rem;">🚗 รายการรถที่เข้าตรวจสภาพ ตรอ.</div>
                    <div style="color:rgba(255,255,255,0.75); font-size:0.82rem;">ทั้งหมด <?php echo number_format($total_records); ?> รายการ</div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th class="center" style="width:46px;">#</th>
                                <th>ชื่อ - นามสกุล</th>
                                <th class="center">ทะเบียนรถ</th>
                                <th>จังหวัด</th>
                                <th>ประเภทรถ</th>
                                <th class="center" style="width:80px;">วันที่</th>
                                <th class="center">เลขที่ ตรอ.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $idx = $offset + 1; foreach ($inspections as $ins): ?>
                            <tr>
                                <td class="center" style="color:var(--text-muted); font-size:0.8rem;"><?php echo $idx++; ?></td>
                                <td style="font-weight:700;"><?php echo htmlspecialchars($ins['cus_name']); ?></td>
                                <td class="center"><span class="plate-pill"><?php echo htmlspecialchars($ins['car_regis_id']); ?></span></td>
                                <td style="font-size:0.85rem; color:var(--text-muted);"><?php echo htmlspecialchars($ins['PROVINCE_NAME']); ?></td>
                                <td style="font-size:0.875rem;"><?php echo htmlspecialchars($ins['car_type_name']); ?></td>
                                <td class="center" style="font-size:0.8rem; color:var(--text-muted); white-space:nowrap;">
                                    <?php echo sprintf('%02d/%02d/%d', $ins['pay_day'], $ins['pay_month'], $ins['pay_year']+543); ?>
                                </td>
                                <td class="center">
                                    <?php if (!empty($ins['serv_2_1'])): ?>
                                        <span class="tro-badge"><?php echo htmlspecialchars($ins['serv_2_1']); ?></span>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1):
                    $qp = $_GET; unset($qp['page']);
                    $pl = function($n) use ($qp) {
                        return 'car_list.php?' . http_build_query(array_merge($qp, ['page' => $n]));
                    };
                ?>
                <div class="pagination">
                    <?php if ($page>1): ?><a href="<?php echo $pl($page-1); ?>" class="pg-btn">‹ ก่อนหน้า</a><?php else: ?><span class="pg-btn disabled">‹ ก่อนหน้า</span><?php endif; ?>
                    <?php
                    $sp=max(1,$page-2); $ep=min($total_pages,$page+2);
                    if ($sp>1) { echo '<a href="'.$pl(1).'" class="pg-btn">1</a>'; if ($sp>2) echo '<span class="pg-dots">…</span>'; }
                    for ($i=$sp;$i<=$ep;$i++) echo '<a href="'.$pl($i).'" class="pg-btn '.($i==$page?'active':'').'">'.$i.'</a>';
                    if ($ep<$total_pages) { if ($ep<$total_pages-1) echo '<span class="pg-dots">…</span>'; echo '<a href="'.$pl($total_pages).'" class="pg-btn">'.$total_pages.'</a>'; }
                    ?>
                    <?php if ($page<$total_pages): ?><a href="<?php echo $pl($page+1); ?>" class="pg-btn">ถัดไป ›</a><?php else: ?><span class="pg-btn disabled">ถัดไป ›</span><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Summary charts ── -->
            <div class="summary-grid">

                <!-- Car type breakdown -->
                <div class="summary-card">
                    <div class="summary-card-head">🏷️ สรุปตามประเภทรถ</div>
                    <div class="summary-card-body">
                        <?php foreach ($car_type_summary as $i => $s): 
                            $pct = $max_car > 0 ? round($s['cnt'] / $max_car * 100) : 0;
                            $rankClass = $i===0 ? 'rank-1' : ($i===1 ? 'rank-2' : ($i===2 ? 'rank-3' : 'rank-n'));
                        ?>
                        <div class="summary-row">
                            <div class="summary-rank <?php echo $rankClass; ?>"><?php echo $i+1; ?></div>
                            <div class="summary-name"><?php echo htmlspecialchars($s['car_type_name']); ?></div>
                            <div class="summary-bar-wrap">
                                <div class="summary-bar" style="width:<?php echo $pct; ?>%;"></div>
                            </div>
                            <div class="summary-count"><?php echo number_format($s['cnt']); ?> คัน</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Province breakdown -->
                <div class="summary-card">
                    <div class="summary-card-head">📍 สรุปตามจังหวัด (Top 15)</div>
                    <div class="summary-card-body">
                        <?php foreach ($province_summary as $i => $s): 
                            $pct = $max_prov > 0 ? round($s['cnt'] / $max_prov * 100) : 0;
                            $rankClass = $i===0 ? 'rank-1' : ($i===1 ? 'rank-2' : ($i===2 ? 'rank-3' : 'rank-n'));
                        ?>
                        <div class="summary-row">
                            <div class="summary-rank <?php echo $rankClass; ?>"><?php echo $i+1; ?></div>
                            <div class="summary-name"><?php echo htmlspecialchars($s['PROVINCE_NAME']); ?></div>
                            <div class="summary-bar-wrap">
                                <div class="summary-bar" style="width:<?php echo $pct; ?>%; background:linear-gradient(90deg,#ea580c,#fb923c);"></div>
                            </div>
                            <div class="summary-count"><?php echo number_format($s['cnt']); ?> คัน</div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /.summary-grid -->

            <?php else: ?>
            <div class="empty-state card">
                <div class="icon">🚘</div>
                <p>ไม่พบข้อมูลการตรวจสภาพรถ ตรอ. ในช่วงเวลาที่เลือก</p>
                <p style="font-size:0.875rem; margin-top:0.5rem; color:var(--text-muted);">ลองเปลี่ยนช่วงเวลาการค้นหา</p>
            </div>
            <?php endif; ?>

        </div><!-- /.content-body -->
    </div><!-- /.main-wrapper -->
</div><!-- /.app-container -->

<script>
function setFilter(type) {
    document.getElementById('filter_type_input').value = type;
    document.getElementById('customFields').style.display = type === 'custom' ? 'flex' : 'none';
    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
    event.currentTarget.classList.add('active');
    if (type !== 'custom') document.getElementById('filterForm').submit();
}
</script>
</body>
</html>
