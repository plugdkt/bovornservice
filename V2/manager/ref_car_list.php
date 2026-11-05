<?php
// manager/ref_car_list.php
// Reference inspection numbers (ตรอ.) report (Manager view) for Bovorn Service V2
date_default_timezone_set('Asia/Bangkok');
require("con_db.php");

// Search Filters - Support GET request to preserve search state
$search_active = isset($_GET['search_active']) ? intval($_GET['search_active']) : 0;
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'today';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

$months_th = [
    1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 
    5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม", 
    9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
];

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

$period_label = "";
if ($filter_type === 'today') {
    $period_label = "ประจำวันนี้ (" . date('d') . " " . $months_th[intval(date('m'))] . " " . (date('Y') + 543) . ")";
} elseif ($filter_type === 'month') {
    $period_label = "ประจำเดือน " . $months_th[intval(date('m'))] . " " . (date('Y') + 543);
} elseif ($filter_type === 'year') {
    $period_label = "ประจำปี พ.ศ. " . (date('Y') + 543);
} else {
    $parts = [];
    if ($day > 0) {
        if ($day2 > 0) {
            $parts[] = "วันที่ " . $day . " - " . $day2;
        } else {
            $parts[] = "วันที่ " . $day;
        }
    }
    if ($month > 0) {
        $parts[] = "เดือน" . $months_th[$month];
    }
    if ($year > 0) {
        $parts[] = "ปี พ.ศ. " . $year;
    }
    $period_label = "กำหนดเอง: " . (empty($parts) ? "ทั้งหมด" : implode(" ", $parts));
}

if (!empty($q)) {
    $period_label .= " | ค้นหาด้วยคำค้น: \"" . htmlspecialchars($q) . "\"";
}

$payments_dkt = [];
$payments_twt = [];

$sum_serv_2_dkt = 0;
$sum_serv_2_twt = 0;

try {
    $conditions = ["p.pay_status != 3", "p.serv_2 != 0"];
    $params = [];
    
    if ($day > 0 && $day2 == 0) {
        $conditions[] = "p.pay_day = :day";
        $params[':day'] = $day;
    } elseif ($day > 0 && $day2 > 0) {
        $conditions[] = "p.pay_day >= :day AND p.pay_day <= :day2";
        $params[':day'] = $day;
        $params[':day2'] = $day2;
    }
    
    if ($month > 0) {
        $conditions[] = "p.pay_month = :month";
        $params[':month'] = $month;
    }
    
    if ($year > 0) {
        $conditions[] = "p.pay_year = :year";
        $params[':year'] = $year - 543;
    }
    
    if (!empty($q)) {
        $conditions[] = "(p.cus_name LIKE :search_q1 OR p.car_regis_id LIKE :search_q2 OR p.serv_2_1 LIKE :search_q3)";
        $params[':search_q1'] = '%' . $q . '%';
        $params[':search_q2'] = '%' . $q . '%';
        $params[':search_q3'] = '%' . $q . '%';
    }
    
    $where_sql = implode(" AND ", $conditions);
    
    // Fetch branch 1 (Dok Khamtai)
    $sql_dkt = "SELECT p.*, pr.PROVINCE_NAME 
                FROM `payment` p
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE $where_sql AND p.id_branch = 1
                ORDER BY p.id_pay ASC";
    $stmt_dkt = $pdo->prepare($sql_dkt);
    $stmt_dkt->execute($params);
    $payments_dkt = $stmt_dkt->fetchAll();
    
    // Fetch branch 2 (Tha Wang Thong)
    $sql_twt = "SELECT p.*, pr.PROVINCE_NAME 
                FROM `payment` p
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE $where_sql AND p.id_branch = 2
                ORDER BY p.id_pay ASC";
    $stmt_twt = $pdo->prepare($sql_twt);
    $stmt_twt->execute($params);
    $payments_twt = $stmt_twt->fetchAll();
    
    // Calculate Branch 1 Sums
    foreach ($payments_dkt as $p) {
        $sum_serv_2_dkt += floatval($p['serv_2']);
    }
    
    // Calculate Branch 2 Sums
    foreach ($payments_twt as $p) {
        $sum_serv_2_twt += floatval($p['serv_2']);
    }
    
} catch (\PDOException $e) {
    $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลขที่ ตรอ. รวมสาขา - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-primary { color: var(--primary); font-weight: 600; }
        .badge-info { background-color: var(--primary-light); color: var(--primary); }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">รายงานเลขที่ ตรอ. (ผู้จัดการ)</div>
            </div>
            
            <div class="content-body">
                <?php if (isset($db_error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">🔍 ค้นหารายการเลขที่ ตรอ.</h3>
                    </div>
                    
                    <form action="ref_car_list.php" method="GET">
                        <input type="hidden" name="search_active" value="1">
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            
                            <!-- Search Term -->
                            <div class="form-group" style="margin-bottom: 0; flex-grow: 1; min-width: 250px;">
                                <label for="q" class="form-label">คำค้นหา</label>
                                <input type="text" name="q" id="q" class="form-control" placeholder="ค้นหา ชื่อลูกค้า, ทะเบียนรถ, เลขที่ ตรอ." value="<?php echo htmlspecialchars($q); ?>">
                            </div>
                            
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
                                        <?php foreach ($months_th as $num => $name): ?>
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
                            
                            <button type="submit" class="btn btn-primary" id="searchBtn" style="height: 38px;">🔍 ค้นหา</button>
                        </div>
                    </form>
                </div>

                <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div style="font-size: 1.1rem; font-weight: 600; color: var(--text-main);">
                        📅 <?php echo $period_label; ?>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <!-- Overall TRO Revenue Card -->
                    <div class="card" style="background: linear-gradient(135deg, var(--primary) 0%, #1e3a8a 100%); color: #fff; padding: 1.75rem; border: none; display: flex; align-items: center; justify-content: space-between; border-radius: var(--radius-lg); margin-bottom: 0;">
                        <div>
                            <h4 style="font-size: 0.95rem; font-weight: 500; opacity: 0.9; margin: 0; color: #fff;">📊 ยอดรวมค่าตรวจสภาพ ตรอ. ทั้งหมด (2 สาขา)</h4>
                            <div style="font-size: 2.25rem; font-weight: 800; margin-top: 0.25rem;">
                                <?php echo number_format($sum_serv_2_dkt + $sum_serv_2_twt); ?> <span style="font-size: 1.15rem; font-weight: 500;">บาท</span>
                            </div>
                            <p style="font-size: 0.75rem; opacity: 0.8; margin-top: 0.25rem; line-height: 1.4; color: #fff;">
                                (สาขาดอกคำใต้: <?php echo number_format($sum_serv_2_dkt); ?> บ. | สาขาท่าวังทอง: <?php echo number_format($sum_serv_2_twt); ?> บ.)
                            </p>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($payments_dkt) && empty($payments_twt)): ?>
                    <div class="card text-center" style="padding: 3rem;">
                        <span style="font-size: 3rem;">🚘</span>
                        <p style="margin-top: 1rem; color: var(--text-muted); font-weight: 500;">ไม่พบรายการเลขที่ ตรอ. ในช่วงเวลาที่เลือกหรือคำค้นหานี้</p>
                    </div>
                <?php else: ?>
                    
                    <!-- 1. DOK KHAMTAI BRANCH -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">🏢 สาขาดอกคำใต้ (รวมค่าตรวจสภาพ: <?php echo number_format($sum_serv_2_dkt); ?> บาท)</h3>
                        </div>
                        
                        <div class="table-container">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>ชื่อลูกค้า</th>
                                        <th>ทะเบียนรถ</th>
                                        <th>จังหวัด</th>
                                        <th class="text-right" style="width: 150px;">ค่าตรวจสภาพ ตรอ.</th>
                                        <th class="text-center" style="width: 200px;">เลขที่ ตรอ.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments_dkt)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center" style="color: var(--text-muted); padding: 2rem;">ไม่มีรายการตรวจสภาพรถ</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $idx = 1; foreach ($payments_dkt as $p): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $idx++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($p['cus_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($p['car_regis_id']); ?></td>
                                                <td><?php echo htmlspecialchars($p['PROVINCE_NAME']); ?></td>
                                                <td class="text-right"><?php echo number_format($p['serv_2']); ?></td>
                                                <td class="text-center"><span class="badge badge-info"><?php echo htmlspecialchars($p['serv_2_1']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background-color: var(--bg-main); font-weight: 700;">
                                            <td colspan="4" class="text-right">รวมค่าตรวจสภาพ ตรอ.:</td>
                                            <td class="text-right text-primary"><?php echo number_format($sum_serv_2_dkt); ?></td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- 2. THA WANG THONG BRANCH -->
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">🏢 สาขาท่าวังทอง (รวมค่าตรวจสภาพ: <?php echo number_format($sum_serv_2_twt); ?> บาท)</h3>
                        </div>
                        
                        <div class="table-container">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>ชื่อลูกค้า</th>
                                        <th>ทะเบียนรถ</th>
                                        <th>จังหวัด</th>
                                        <th class="text-right" style="width: 150px;">ค่าตรวจสภาพ ตรอ.</th>
                                        <th class="text-center" style="width: 200px;">เลขที่ ตรอ.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($payments_twt)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center" style="color: var(--text-muted); padding: 2rem;">ไม่มีรายการตรวจสภาพรถ</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $idx = 1; foreach ($payments_twt as $p): ?>
                                            <tr>
                                                <td class="text-center"><?php echo $idx++; ?></td>
                                                <td><strong><?php echo htmlspecialchars($p['cus_name']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($p['car_regis_id']); ?></td>
                                                <td><?php echo htmlspecialchars($p['PROVINCE_NAME']); ?></td>
                                                <td class="text-right"><?php echo number_format($p['serv_2']); ?></td>
                                                <td class="text-center"><span class="badge badge-info"><?php echo htmlspecialchars($p['serv_2_1']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr style="background-color: var(--bg-main); font-weight: 700;">
                                            <td colspan="4" class="text-right">รวมค่าตรวจสภาพ ตรอ.:</td>
                                            <td class="text-right text-primary"><?php echo number_format($sum_serv_2_twt); ?></td>
                                            <td></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function handleFilterTypeChange() {
            const filterType = document.getElementById('filter_type').value;
            const customFields = document.getElementById('customDateFields');
            
            if (filterType === 'custom') {
                customFields.style.display = 'flex';
            } else {
                customFields.style.display = 'none';
                // Auto submit the form on preset selection
                document.getElementById('filter_type').form.submit();
            }
        }
    </script>
</body>
</html>
