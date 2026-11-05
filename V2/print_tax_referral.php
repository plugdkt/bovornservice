<?php
// print_tax_referral.php — V2
// ใบส่งต่อภาษีรายวัน สำหรับระบบ Bovorn Service V2
// ใช้ PDO + unified payment table + id_branch filter

require("db_connect.php");
check_login([1, 2]); // ทั้ง manager และ clerk เข้าได้
date_default_timezone_set('Asia/Bangkok');

// ── รับ parameter วันที่ ──────────────────────────────────────────
$day   = isset($_GET['day'])   ? intval($_GET['day'])   : intval(date('d'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year_input = isset($_GET['year']) ? intval($_GET['year']) : (intval(date('Y')) + 543);

// แปลง BE ↔ AD
if ($year_input > 2500) {
    $year_ad = $year_input - 543;
    $year_be = $year_input;
} else {
    $year_ad = $year_input;
    $year_be = $year_input + 543;
}

// ── สาขา ─────────────────────────────────────────────────────────
$id_branch = isset($_GET['id_branch'])
    ? intval($_GET['id_branch'])
    : (isset($_SESSION['id_branch']) ? intval($_SESSION['id_branch']) : 1);

if ($id_branch == 1) {
    $branch_name  = "บวรเซอร์วิสดอกคำใต้";
    $branch_phone = "โทร 054-491910";
} else {
    $branch_name  = "บวรเซอร์วิสท่าวังทอง";
    $branch_phone = "โทร 054-480445";
}

// ── ดึงข้อมูลจาก DB (V2 unified table) ──────────────────────────
try {
    $stmt = $pdo->prepare(
        "SELECT p.*, ct.car_type_name
         FROM `payment` p
         LEFT JOIN `car_type` ct ON p.id_car_type = ct.id_car_type
         WHERE p.pay_day   = :day
           AND p.pay_month = :month
           AND p.pay_year  = :year
           AND p.id_branch = :branch
           AND p.pay_status != 3
           AND (p.serv_3 > 0 OR p.serv_4 > 0 OR p.serv_5 > 0 OR p.serv_6 > 0)
         ORDER BY p.id_pay ASC"
    );
    $stmt->execute([
        ':day'    => $day,
        ':month'  => $month,
        ':year'   => $year_ad,
        ':branch' => $id_branch,
    ]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $rows = [];
}

// ── แยกรถยนต์ / จยย. (id_car_type == 4 = จยย.) ──────────────────
$rows_car        = [];
$rows_motorcycle = [];
foreach ($rows as $row) {
    if (intval($row['id_car_type']) == 4) {
        $rows_motorcycle[] = $row;
    } else {
        $rows_car[] = $row;
    }
}

// ── ฟังก์ชันแปลงตัวเลขเป็นตัวอักษรไทย ──────────────────────────
function baht_text($number) {
    $txtnum1 = array('ศูนย์','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า','สิบ');
    $txtnum2 = array('','สิบ','ร้อย','พัน','หมื่น','แสน','ล้าน');
    $number  = str_replace([',', ' '], '', $number);
    $number  = number_format(doubleval($number), 2, '.', '');
    list($num, $dec) = explode('.', $number);

    $baht_text = '';
    $len = strlen($num);
    for ($i = 0; $i < $len; $i++) {
        $n     = substr($num, $i, 1);
        $len_n = $len - $i - 1;
        if ($n != 0) {
            if ($len_n == 1 && $n == 2)                   $baht_text .= 'ยี่';
            elseif ($len_n == 1 && $n == 1)               $baht_text .= '';
            elseif ($len_n == 0 && $n == 1 && $len > 1)  $baht_text .= 'เอ็ด';
            else                                           $baht_text .= $txtnum1[$n];
            $baht_text .= $txtnum2[$len_n];
        }
    }
    $baht_text = ($baht_text == '') ? 'ศูนย์บาท' : $baht_text . 'บาท';

    $dec_text = '';
    if ($dec == '00' || $dec == '') {
        $baht_text .= 'ถ้วน';
    } else {
        $len = strlen($dec);
        for ($i = 0; $i < $len; $i++) {
            $n     = substr($dec, $i, 1);
            $len_n = $len - $i - 1;
            if ($n != 0) {
                if ($len_n == 1 && $n == 2)                   $dec_text .= 'ยี่';
                elseif ($len_n == 1 && $n == 1)               $dec_text .= '';
                elseif ($len_n == 0 && $n == 1 && $len > 1)  $dec_text .= 'เอ็ด';
                else                                           $dec_text .= $txtnum1[$n];
                $dec_text .= $txtnum2[$len_n];
            }
        }
        $baht_text .= $dec_text . 'สตางค์';
    }
    return $baht_text;
}

// ── ฟังก์ชัน render ตารางรายการ ──────────────────────────────────
function renderTaxTable($rows, $label, &$sum_tax, &$sum_fine, &$sum_back_tax, &$sum_book_fee, &$sum_total) {
    $count = count($rows);
    if ($count == 0) return;

    $sub_tax = $sub_fine = $sub_back = $sub_book = $sub_total = 0;
    $idx = 1;

    echo '<div class="section-title">' . $label . '</div>';
    echo '<table class="main-table">';
    echo '<thead><tr>
            <th width="5%">ลำดับ</th>
            <th width="13%">ทะเบียน</th>
            <th width="10%">ภาษี</th>
            <th width="7%">ปรับ</th>
            <th width="12%">ภาษีย้อนหลัง</th>
            <th width="10%">เปลี่ยนเล่ม</th>
            <th width="10%">รวม</th>
            <th width="13%">หมายเหตุ</th>
            <th width="20%">ลงชื่อรับเล่ม</th>
          </tr></thead><tbody>';

    foreach ($rows as $row) {
        $tax      = doubleval($row['serv_3']);
        $fine     = doubleval($row['serv_5']);
        $back_tax = doubleval($row['serv_4']);
        $book_fee = doubleval($row['serv_6']);
        $total    = $tax + $fine + $back_tax + $book_fee;

        $sub_tax   += $tax;  $sub_fine += $fine;
        $sub_back  += $back_tax; $sub_book += $book_fee; $sub_total += $total;
        $sum_tax   += $tax;  $sum_fine += $fine;
        $sum_back_tax += $back_tax; $sum_book_fee += $book_fee; $sum_total += $total;

        echo '<tr>';
        echo '<td class="text-center">' . $idx++ . '</td>';
        echo '<td class="text-center">' . htmlspecialchars($row['car_regis_id']) . '</td>';
        echo '<td class="text-right">'  . ($tax > 0      ? number_format($tax, 0)      : '') . '</td>';
        echo '<td class="text-right">'  . ($fine > 0     ? number_format($fine, 0)     : '') . '</td>';
        echo '<td class="text-right">'  . ($back_tax > 0 ? number_format($back_tax, 0) : '') . '</td>';
        echo '<td class="text-right">'  . ($book_fee > 0 ? number_format($book_fee, 0) : '') . '</td>';
        echo '<td class="text-right bg-yellow" style="font-weight:bold;">' . number_format($total, 0) . '</td>';
        echo '<td class="bg-blue" style="opacity:0.85;"></td>';
        echo '<td></td>';
        echo '</tr>';
    }

    // แถวผลรวมย่อย
    echo '<tr class="bg-yellow" style="font-weight:bold;">';
    echo '<td colspan="2" class="text-center">รวม</td>';
    echo '<td class="text-right">' . number_format($sub_tax, 0)   . '</td>';
    echo '<td class="text-right">' . number_format($sub_fine, 0)  . '</td>';
    echo '<td class="text-right">' . number_format($sub_back, 0)  . '</td>';
    echo '<td class="text-right">' . number_format($sub_book, 0)  . '</td>';
    echo '<td class="text-right">' . number_format($sub_total, 0) . '</td>';
    echo '<td colspan="2" class="bg-gray-cell"></td>';
    echo '</tr>';
    echo '</tbody></table>';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ใบส่งต่อภาษีรายวัน — <?php echo "$day/$month/$year_be"; ?> — <?php echo $branch_name; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&family=Prompt:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ── Base ── */
        *, *::before, *::after { box-sizing: border-box; }
        body {
            font-family: 'Sarabun', sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 30px 20px;
            background: linear-gradient(135deg, #f0f9ff 0%, #e0e7ff 100%);
            color: #1e293b;
            min-height: 100vh;
        }

        /* ── Action Bar (screen only) ── */
        .action-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 1rem;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .btn-print {
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            color: #fff;
            border: none;
            padding: 10px 28px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Prompt', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(37,99,235,0.35);
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-print:hover { background: linear-gradient(135deg,#1d4ed8,#1e40af); transform: translateY(-1px); }
        .btn-back {
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 10px 22px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            font-family: 'Prompt', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-back:hover { background: #e2e8f0; }

        /* ── Container ── */
        .container {
            width: 860px;
            margin: 0 auto;
            background: #ffffff;
            padding: 28px 30px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(15,23,42,0.10);
            border: 1px solid #e2e8f0;
        }

        /* ── Header info row ── */
        .header-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-bottom: 20px;
        }
        .header-table td {
            border: 1px solid transparent;
            padding: 12px;
            text-align: center;
            font-weight: bold;
            font-size: 15px;
            border-radius: 10px;
            font-family: 'Prompt', sans-serif;
        }
        .bg-blue   { background:#eff6ff; border-color:#bfdbfe !important; color:#1e40af; }
        .bg-yellow { background:#fef9c3; border-color:#fef08a !important; color:#854d0e; }
        .bg-pink   { background:#fce7f3; border-color:#fbcfe8 !important; color:#9d174d; }
        .bg-orange { background:#ffedd5; border-color:#fed7aa !important; color:#9a3412; }
        .bg-purple { background:#faf5ff; border-color:#e9d5ff !important; color:#581c87;
                     font-family:'Prompt',sans-serif; text-align:center; border-radius:8px; }
        .bg-orange-cell { background:#fff7ed; border-color:#ffedd5 !important; color:#c2410c; }
        .bg-gray-cell   { background:#f1f5f9; }

        /* ── Main table ── */
        .main-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .main-table th, .main-table td {
            border: 1px solid #e2e8f0;
            padding: 9px 11px;
            font-size: 13.5px;
        }
        .main-table th {
            font-weight: 600;
            text-align: center;
            background: #0f172a;
            color: #fff;
            font-family: 'Prompt', sans-serif;
            letter-spacing: 0.3px;
            border-bottom: 2px solid #1e293b;
        }
        .main-table tbody tr:nth-child(even) { background:#f8fafc; }
        .main-table tbody tr:hover           { background:#f1f5f9; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }

        /* ── Section title ── */
        .section-title {
            font-family: 'Prompt', sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 22px 0 10px;
            border-left: 4px solid #2563eb;
            padding-left: 8px;
        }

        /* ── Summary table ── */
        .summary-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px;
            margin-top: 14px;
        }
        .summary-table td {
            border: 1px solid #e2e8f0;
            padding: 10px 16px;
            font-size: 15px;
            border-radius: 8px;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            font-size: 16px;
            font-weight: 600;
            color: #64748b;
            font-family: 'Prompt', sans-serif;
        }

        /* ── Print media ── */
        @media print {
            .action-bar { display: none !important; }
            body {
                padding: 0; margin: 0;
                background: #fff;
                font-size: 13px;
            }
            .container {
                width: 100%; padding: 0;
                border: none; box-shadow: none; margin: 0;
                border-radius: 0;
            }
            .section-title {
                margin-top: 14px !important;
                border-left: 3px solid #000 !important;
                font-size: 13px !important;
                padding-left: 5px !important;
            }
            .header-table { margin-bottom: 8px !important; border-spacing: 0 !important; }
            .header-table td {
                padding: 5px !important; font-size: 13px !important;
                border-radius: 0 !important; box-shadow: none !important;
            }
            .main-table { margin-bottom: 8px !important; border-radius: 0 !important; overflow: visible !important; }
            .main-table th, .main-table td { padding: 4px 6px !important; font-size: 12px !important; }
            .main-table th { background: #f1f5f9 !important; color: #000 !important; border-bottom: 1px solid #000 !important; }
            .summary-table { margin-top: 4px !important; border-spacing: 0 !important; }
            .summary-table td { padding: 4px 10px !important; font-size: 12px !important; border-radius: 0 !important; }
            .main-table, .main-table th, .main-table td,
            .header-table td, .summary-table td { border: 1px solid #000 !important; }
            .bg-blue,.bg-yellow,.bg-pink,.bg-orange,.bg-purple,.bg-orange-cell {
                background: transparent !important; color: #000 !important;
            }
            @page { size: A4 portrait; margin: 10mm; }
        }
    </style>
</head>
<body>

<!-- ── Action Bar ─────────────────────────────────────────────── -->
<div class="action-bar">
    <button class="btn-back" onclick="if(window.opener) { window.close(); } else { window.location.href='billing.php'; }">← ย้อนกลับ</button>
    <button class="btn-print" onclick="window.print()">🖨️ สั่งพิมพ์เอกสาร</button>

    <!-- เลือกวันที่ -->
    <form method="GET" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <input type="hidden" name="id_branch" value="<?php echo $id_branch; ?>">
        <label style="font-weight:600; font-size:13px; color:#334155;">วันที่:</label>
        <input type="number" name="day"   value="<?php echo $day; ?>"   min="1" max="31" style="width:54px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
        <input type="number" name="month" value="<?php echo $month; ?>" min="1" max="12" style="width:54px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
        <input type="number" name="year"  value="<?php echo $year_be; ?>"                style="width:74px; padding:6px 8px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;" placeholder="พ.ศ.">
        <select name="id_branch" style="padding:6px 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px;">
            <option value="1" <?php echo $id_branch == 1 ? 'selected' : ''; ?>>ดอกคำใต้</option>
            <option value="2" <?php echo $id_branch == 2 ? 'selected' : ''; ?>>ท่าวังทอง</option>
        </select>
        <button type="submit" class="btn-print" style="padding:7px 18px; font-size:13px;">🔍 แสดงข้อมูล</button>
    </form>
</div>

<!-- ── Document ──────────────────────────────────────────────── -->
<div class="container">

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td width="35%" class="bg-blue">ใบส่งต่อภาษีรายวัน</td>
            <td width="20%" class="bg-yellow"><?php echo "$day/$month/$year_be"; ?></td>
            <td width="25%" class="bg-pink"><?php echo htmlspecialchars($branch_name); ?></td>
            <td width="20%" class="bg-orange"><?php echo htmlspecialchars($branch_phone); ?></td>
        </tr>
    </table>

    <?php
    $sum_tax = $sum_fine = $sum_back_tax = $sum_book_fee = $sum_total = 0;
    $count_car        = count($rows_car);
    $count_motorcycle = count($rows_motorcycle);

    if ($count_car == 0 && $count_motorcycle == 0):
    ?>
        <div class="empty-state">
            📭 ไม่มีรายการส่งต่อภาษีสำหรับวันที่ <?php echo "$day/$month/$year_be"; ?><br>
            <span style="font-size:13px; font-weight:400; margin-top:6px; display:block; color:#94a3b8;">รายการที่แสดงคือบิลที่มี ภาษีปกติ / ภาษีย้อนหลัง / ค่าปรับ / ค่าเล่ม มากกว่า 0</span>
        </div>
    <?php else: ?>

        <?php renderTaxTable($rows_car,        '🚗 รายการส่งต่อภาษีรถยนต์',                     $sum_tax, $sum_fine, $sum_back_tax, $sum_book_fee, $sum_total); ?>
        <?php renderTaxTable($rows_motorcycle, '🏍️ รายการส่งต่อภาษีรถจักรยานยนต์ (จยย.)', $sum_tax, $sum_fine, $sum_back_tax, $sum_book_fee, $sum_total); ?>

        <?php if ($count_car > 0 && $count_motorcycle > 0): ?>
        <div style="margin:8px 0; font-weight:700; font-size:14px; text-align:right; font-family:'Prompt',sans-serif; color:#1e293b;">
            รวมภาษีทั้งสิ้น (รถยนต์ + จยย.): <?php echo number_format($sum_total, 0); ?> บาท
        </div>
        <?php endif; ?>

        <?php
        // ค่าธรรมเนียม ดึงจาก Database (Settings)
        $car_rate = floatval(get_system_setting('tax_car_fee', 10));
        $motorcycle_rate = floatval(get_system_setting('tax_motorcycle_fee', 5));

        $car_fee        = $count_car * $car_rate;
        $motorcycle_fee = $count_motorcycle * $motorcycle_rate;
        $grand_total    = $sum_total + $car_fee + $motorcycle_fee;
        ?>

        <!-- Summary table -->
        <table class="summary-table">
            <tr>
                <td width="15%" class="text-center" style="font-weight:700;">รถยนต์ (<?php echo $car_rate; ?> บ.)</td>
                <td width="20%" class="text-right"  style="font-weight:700;"><?php echo $count_car; ?> คัน</td>
                <td width="20%" class="text-center" style="font-weight:700;">เป็นเงิน</td>
                <td width="45%" class="text-right bg-yellow" style="font-weight:700; font-size:16px;"><?php echo number_format($car_fee, 0); ?> บาท</td>
            </tr>
            <tr>
                <td class="text-center" style="font-weight:700;">จยย. (<?php echo $motorcycle_rate; ?> บ.)</td>
                <td class="text-right"  style="font-weight:700;"><?php echo $count_motorcycle; ?> คัน</td>
                <td class="text-center" style="font-weight:700;">เป็นเงิน</td>
                <td class="text-right bg-yellow" style="font-weight:700; font-size:16px;"><?php echo number_format($motorcycle_fee, 0); ?> บาท</td>
            </tr>
            <tr>
                <td class="text-center bg-orange" style="font-weight:700; font-size:16px;">รวม</td>
                <td class="text-right bg-yellow"  style="font-weight:700; font-size:16px;"><?php echo ($count_car + $count_motorcycle); ?> คัน</td>
                <td class="text-center bg-orange" style="font-weight:700; font-size:16px;">รวมส่งเงิน</td>
                <td class="text-right bg-yellow"  style="font-weight:700; font-size:20px; color:#1e3a8a;"><?php echo number_format($grand_total, 0); ?> บาท</td>
            </tr>
            <tr>
                <td colspan="4" class="bg-purple" style="font-weight:700; font-size:15px; padding:12px; text-align:center;">
                    ** &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ( <?php echo baht_text($grand_total); ?> )
                </td>
            </tr>
        </table>

    <?php endif; ?>

</div><!-- /.container -->

</body>
</html>
