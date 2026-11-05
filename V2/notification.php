<?php
// notification.php
// Customer follow-up reminder list for Bovorn Service V2
date_default_timezone_set('Asia/Bangkok');
require("db_connect.php");
check_login([2]); // Only clerk (role = 2) is allowed

$id_branch = $_SESSION['id_branch'];

// Search Filters - Supports GET request to preserve pagination query parameters
$search_active = isset($_GET['search_active']) ? intval($_GET['search_active']) : 0;
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'next_month';

if ($filter_type === 'next_month') {
    $day = 0;
    $m = intval(date('m'));
    $y = intval(date('Y'));
    $next_m = $m + 1;
    $next_y = $y;
    if ($next_m > 12) {
        $next_m = 1;
        $next_y++;
    }
    $month = $next_m;
    $year = $next_y + 543 - 1; // 1 year ago to find renewals
} elseif ($filter_type === 'today') {
    $day = intval(date('d'));
    $month = intval(date('m'));
    $year = intval(date('Y')) + 543 - 1; // 1 year ago to find renewals
} elseif ($filter_type === 'month') {
    $day = 0;
    $month = intval(date('m'));
    $year = intval(date('Y')) + 543 - 1; // 1 year ago to find renewals
} elseif ($filter_type === 'year') {
    $day = 0;
    $month = 0;
    $year = intval(date('Y')) + 543 - 1; // 1 year ago to find renewals
} else { // custom
    $day = isset($_GET['day']) ? intval($_GET['day']) : 0;
    $month = isset($_GET['month']) ? intval($_GET['month']) : 0;
    $year = isset($_GET['year']) ? intval($_GET['year']) : (date('Y') + 543);
}

$notifications = [];
$total_records = 0;
$total_pages = 0;
$page = 1;
$limit = 20;

if ($day > 0 || $month > 0 || $year > 0) {
    try {
        $conditions = ["p.id_branch = :id_branch", "p.serv_2 != 0", "p.pay_status = 2"];
        $params = [':id_branch' => $id_branch];
        
        if ($day > 0) {
            $conditions[] = "p.pay_day = :day";
            $params[':day'] = $day;
        }
        if ($month > 0) {
            $conditions[] = "p.pay_month = :month";
            $params[':month'] = $month;
        }
        if ($year > 0) {
            $conditions[] = "p.pay_year = :year";
            $params[':year'] = $year - 543;
        }
        
        $where_sql = implode(" AND ", $conditions);
        
        // 1. Get total count for pagination
        $sql_count = "SELECT COUNT(*) FROM `payment` p WHERE $where_sql";
        $stmt_count = $pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_records = intval($stmt_count->fetchColumn());
        
        // 2. Pagination calculation
        $total_pages = ceil($total_records / $limit);
        $page = isset($_GET['page']) ? intval($_GET['page']) : 1;
        if ($page < 1) $page = 1;
        if ($page > $total_pages && $total_pages > 0) $page = $total_pages;
        $offset = ($page - 1) * $limit;
        
        // 3. Fetch notifications (Paginated)
        $sql = "SELECT p.*, c.car_type_name, pr.PROVINCE_NAME 
                FROM `payment` p
                JOIN `car_type` c ON p.id_car_type = c.id_car_type
                JOIN `province` pr ON p.province_id = pr.PROVINCE_ID
                WHERE $where_sql
                ORDER BY p.id_pay DESC
                LIMIT :limit OFFSET :offset";
                
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (\PDOException $e) {
        $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบแจ้งเตือนลูกค้า - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .text-center { text-align: center; }
        .text-bold { font-weight: 600; }
        .call-link {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background-color: var(--success-light);
            color: var(--success);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
        }
        .call-link:hover {
            background-color: var(--success);
            color: #fff;
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
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">ระบบติดตามและแจ้งเตือนต่ออายุลูกค้า ตรอ.</div>
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
                
                <div class="card" style="border-left: 4px solid var(--warning);">
                    <div class="card-header">
                        <h3 class="card-title">📞 ค้นหาลูกค้าครบกำหนดรอบตรวจสภาพ (เป้าหมายแจ้งเตือน)</h3>
                    </div>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; margin-top: -0.5rem;">
                        💡 <strong>คำแนะนำ:</strong> ระบบจะแสดงรายชื่อลูกค้าที่จะครบกำหนดครบรอบปีชำระภาษีใน **เดือนถัดไป (เดือนหน้า)** เป็นค่าเริ่มต้น เพื่อให้พนักงานสามารถติดต่อแจ้งเตือนลูกค้าล่วงหน้าได้ทันเวลา
                    </p>
                    
                    <form action="notification.php" method="GET">
                        <input type="hidden" name="search_active" value="1">
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; align-items: flex-end;">
                            
                            <!-- Quick Filter Dropdown -->
                            <div class="form-group" style="margin-bottom: 0; min-width: 220px;">
                                <label for="filter_type" class="form-label">เลือกช่วงเวลาครบรอบปี</label>
                                <select name="filter_type" id="filter_type" class="form-control" onchange="handleFilterTypeChange()">
                                    <option value="next_month" <?php echo $filter_type === 'next_month' ? 'selected' : ''; ?>>เดือนถัดไป (ครบรอบในเดือนหน้า)</option>
                                    <option value="today" <?php echo $filter_type === 'today' ? 'selected' : ''; ?>>วันนี้ (ครบรอบในวันนี้)</option>
                                    <option value="month" <?php echo $filter_type === 'month' ? 'selected' : ''; ?>>เดือนนี้ (ครบรอบในเดือนนี้)</option>
                                    <option value="year" <?php echo $filter_type === 'year' ? 'selected' : ''; ?>>ปีนี้ (ครบรอบในปีนี้)</option>
                                    <option value="custom" <?php echo $filter_type === 'custom' ? 'selected' : ''; ?>>ระบุวันที่ปีที่ผ่านมาเอง (Custom)</option>
                                </select>
                            </div>
                            
                            <!-- Custom Date Selectors -->
                            <div id="customDateFields" style="display: <?php echo $filter_type === 'custom' ? 'flex' : 'none'; ?>; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
                                <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
                                    <label for="day" class="form-label">วันที่</label>
                                    <select name="day" id="day" class="form-control">
                                        <option value="0">- วัน -</option>
                                        <?php for ($i = 1; $i <= 31; $i++): ?>
                                            <option value="<?php echo $i; ?>" <?php echo $day == $i ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                
                                <div class="form-group" style="margin-bottom: 0; min-width: 150px;">
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
                                
                                <div class="form-group" style="margin-bottom: 0; min-width: 120px;">
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
                            
                            <button type="submit" class="btn btn-primary" id="searchBtn" style="height: 38px; display: <?php echo $filter_type === 'custom' ? 'inline-flex' : 'none'; ?>;">🔍 ดึงรายชื่อ</button>
                        </div>
                    </form>
                </div>
                
                <?php if (!empty($notifications)): ?>
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem;">
                            <h3 class="card-title">📞 รายชื่อลูกค้าครบกำหนดบริการ ตรอ. (ทั้งหมด <?php echo number_format($total_records); ?> รายการ)</h3>
                            <?php if ($total_pages > 1): ?>
                                <span style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500;">
                                    หน้า <?php echo $page; ?> จาก <?php echo $total_pages; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="table-container">
                            <table class="table-modern">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>ชื่อ-สกุล ลูกค้า</th>
                                        <th>ประเภทรถ</th>
                                        <th>ทะเบียนรถ</th>
                                        <th>จังหวัด</th>
                                        <th>ที่อยู่ลูกค้า</th>
                                        <th style="width: 160px;" class="text-center">เบอร์โทรศัพท์</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $idx = $offset + 1; foreach ($notifications as $not): ?>
                                        <tr>
                                            <td class="text-center"><?php echo $idx++; ?></td>
                                            <td><strong><?php echo htmlspecialchars($not['cus_name']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($not['car_type_name']); ?></td>
                                            <td class="text-bold"><?php echo htmlspecialchars($not['car_regis_id']); ?></td>
                                            <td><?php echo htmlspecialchars($not['PROVINCE_NAME']); ?></td>
                                            <td><span style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($not['cus_addr']); ?></span></td>
                                            <td class="text-center">
                                                <a href="tel:<?php echo htmlspecialchars($not['cus_phone']); ?>" class="call-link">
                                                    📞 <?php echo htmlspecialchars($not['cus_phone']); ?>
                                                </a>
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
                                $query_params = $_GET;
                                unset($query_params['page']);
                                
                                $build_page_link = function($p_num) use ($query_params) {
                                    $params = array_merge($query_params, ['page' => $p_num]);
                                    return 'notification.php?' . http_build_query($params);
                                };
                                ?>
                                <?php if ($page > 1): ?>
                                    <a href="<?php echo $build_page_link($page - 1); ?>" class="btn-page">« ก่อนหน้า</a>
                                <?php else: ?>
                                    <span class="btn-page disabled">« ก่อนหน้า</span>
                                <?php endif; ?>
                                
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
                                <?php if ($page < $total_pages): ?>
                                    <a href="<?php echo $build_page_link($page + 1); ?>" class="btn-page">ถัดไป »</a>
                                <?php else: ?>
                                    <span class="btn-page disabled">ถัดไป »</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="card text-center" style="padding: 3rem;">
                        <span style="font-size: 3rem;">📞</span>
                        <p style="margin-top: 1rem; color: var(--text-muted); font-weight: 500;">ไม่พบลูกค้ารอบบริการ ตรอ. ในช่วงเวลาดังกล่าว</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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
                document.getElementById('filter_type').form.submit();
            }
        }
    </script>
</body>
</html>
