<?php
// receipt.php — Modern receipt design for Bovorn Service V2
date_default_timezone_set('Asia/Bangkok');
require("db_connect.php");
check_login([1, 2]);

$id_pay = isset($_GET['id_pay']) ? intval($_GET['id_pay']) : 0;
if ($id_pay === 0) { header("Location: billing.php"); exit; }

try {
    $stmt = $pdo->prepare("SELECT p.*, c.car_type_name, pr.PROVINCE_NAME, e.name as emp_name, e.id_branch
                           FROM `payment` p
                           JOIN `car_type` c  ON p.id_car_type = c.id_car_type
                           JOIN `province` pr ON p.province_id  = pr.PROVINCE_ID
                           JOIN `employee` e  ON p.id_emp       = e.id_emp
                           WHERE p.id_pay = :id_pay");
    $stmt->execute([':id_pay' => $id_pay]);
    $pay = $stmt->fetch();
    if (!$pay) die("Error: Transaction not found.");

    if ($pay['pay_status'] == 1) {
        $pdo->prepare("UPDATE `payment` SET `pay_status` = 2 WHERE `id_pay` = :id_pay")
            ->execute([':id_pay' => $id_pay]);
        $pay['pay_status'] = 2;
    }
} catch (\PDOException $e) { die("Database Error: " . $e->getMessage()); }

$tx_time    = substr($pay['pay_time'], 0, 5); // HH:MM
$tx_date    = sprintf('%02d/%02d/%d', $pay['pay_day'], $pay['pay_month'], $pay['pay_year'] + 543);
$branch_id  = $pay['id_branch'];
$branch_name = ($branch_id == 1) ? 'ดอกคำใต้' : 'ท่าวังทอง';
$branch_tel  = ($branch_id == 1) ? '054-491 910' : '054-480 445, 087-656-2656';
$branch_addr = ($branch_id == 1)
    ? '177 ม.7 ต.ดอกคำใต้ อ.ดอกคำใต้ จ.พะเยา 56120'
    : '312 ม.4 ต.ท่าวังทอง อ.เมืองพะเยา จ.พะเยา 56000';

// Build service rows (only rows with value > 0)
$services = [];
if ($pay['serv_1'] > 0)
    $services[] = ['label' => 'ต่อ พ.ร.บ. คุ้มครองผู้ประสบภัยจากรถ', 'amount' => $pay['serv_1']];
if ($pay['serv_2'] > 0)
    $services[] = ['label' => 'ตรวจสภาพรถ (ตรอ.)' . (!empty($pay['serv_2_1']) ? ' เลขที่ ' . htmlspecialchars($pay['serv_2_1']) : ''), 'amount' => $pay['serv_2']];
if ($pay['serv_3'] > 0)
    $services[] = ['label' => 'ต่อภาษีประจำปี (กรณีปกติ)', 'amount' => $pay['serv_3']];
if ($pay['serv_4'] > 0) {
    $y_label = $pay['serv_4_1'] > 0 ? ' (' . $pay['serv_4_1'] . ' ปี ตั้งแต่ พ.ศ. ' . htmlspecialchars($pay['serv_4_2']) . ')' : '';
    $services[] = ['label' => 'ต่อภาษีย้อนหลัง' . $y_label, 'amount' => $pay['serv_4']];
}
if ($pay['serv_5'] > 0)
    $services[] = ['label' => 'ค่าปรับล่าช้าภาษี', 'amount' => $pay['serv_5']];
if ($pay['serv_6'] > 0)
    $services[] = ['label' => 'ค่าสมุดเล่มทะเบียน', 'amount' => $pay['serv_6']];
if ($pay['serv_7'] > 0)
    $services[] = ['label' => 'ค่าบริการอื่นๆ' . (!empty($pay['serv_7_1']) ? ': ' . htmlspecialchars($pay['serv_7_1']) : ''), 'amount' => $pay['serv_7']];
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<title>ใบเสร็จรับเงิน #<?php echo $pay['id_pay']; ?> — บวรเซอร์วิส</title>
<style>
/* ── Google Font (Sarabun — Thai-optimised) ── */
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap');

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --ink:        #0f172a;
    --ink-mid:    #334155;
    --ink-light:  #64748b;
    --accent:     #1e3a5f;   /* deep navy */
    --accent-lt:  #e8f0fa;
    --gold:       #b8860b;
    --divider:    #cbd5e1;
    --surface:    #f8fafc;
    --white:      #ffffff;
    --danger:     #dc2626;
    --radius:     6px;
}

body {
    font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
    font-size: 13px;
    color: var(--ink);
    background: #e2e8f0;
    padding: 20px;
    line-height: 1.5;
}

/* ── Wrapper: two columns side-by-side ── */
.page {
    display: flex;
    gap: 18px;
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
}

/* ── Single receipt card ── */
.receipt {
    flex: 1;
    background: var(--white);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0,0,0,0.12);
    display: flex;
    flex-direction: column;
    position: relative;
}

/* ── Diagonal watermark badge ── */
.receipt::before {
    content: attr(data-label);
    position: absolute;
    top: 22px;
    right: -22px;
    background: var(--gold);
    color: #fff;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 1.5px;
    padding: 3px 28px;
    transform: rotate(45deg);
    transform-origin: center;
    box-shadow: 0 2px 6px rgba(0,0,0,0.25);
    z-index: 5;
}

/* ── Navy header bar ── */
.receipt-header {
    background: linear-gradient(135deg, #1e3a5f 0%, #2d5a8e 100%);
    color: #fff;
    padding: 14px 16px 12px;
    position: relative;
    overflow: hidden;
}
.receipt-header::after {
    content: '';
    position: absolute;
    bottom: -12px; left: 0; right: 0;
    height: 24px;
    background: var(--white);
    border-radius: 50% 50% 0 0 / 24px 24px 0 0;
}

.brand-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 6px;
}
.brand-icon {
    width: 36px; height: 36px;
    background: rgba(255,255,255,0.15);
    border: 2px solid rgba(255,255,255,0.35);
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.brand-name {
    font-size: 15px;
    font-weight: 800;
    letter-spacing: 0.5px;
    line-height: 1.2;
}
.brand-sub {
    font-size: 10.5px;
    font-weight: 400;
    opacity: 0.85;
    line-height: 1.4;
    margin-top: 1px;
}

.receipt-type-badge {
    display: inline-block;
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.35);
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.8px;
    padding: 2px 10px;
    margin-top: 4px;
    text-transform: uppercase;
}

/* ── Body ── */
.receipt-body {
    padding: 20px 16px 14px;
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* ── Receipt number + date row ── */
.meta-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--accent-lt);
    border-radius: var(--radius);
    padding: 7px 12px;
    border-left: 4px solid var(--accent);
}
.meta-no {
    font-size: 15px;
    font-weight: 800;
    color: var(--accent);
    letter-spacing: 0.5px;
}
.meta-date {
    font-size: 11.5px;
    color: var(--ink-mid);
    text-align: right;
    line-height: 1.6;
}

/* ── Customer info block ── */
.info-block {
    background: var(--surface);
    border-radius: var(--radius);
    padding: 9px 12px;
    border: 1px solid var(--divider);
}
.info-block-title {
    font-size: 10px;
    font-weight: 700;
    color: var(--ink-light);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-bottom: 7px;
    display: flex;
    align-items: center;
    gap: 5px;
}
.info-block-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--divider);
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 4px 14px;
}
.info-item { font-size: 11.5px; line-height: 1.5; }
.info-label { color: var(--ink-light); font-size: 10px; font-weight: 600; display: block; }
.info-value { color: var(--ink); font-weight: 600; }
.plate-badge {
    display: inline-block;
    background: var(--accent);
    color: #fff;
    border-radius: 4px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
}

/* ── Service table ── */
.service-section-title {
    font-size: 10px;
    font-weight: 700;
    color: var(--ink-light);
    letter-spacing: 1px;
    text-transform: uppercase;
    display: flex;
    align-items: center;
    gap: 5px;
}
.service-section-title::after {
    content: '';
    flex: 1;
    height: 1px;
    background: var(--divider);
}

.service-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11.5px;
}
.service-table thead tr {
    background: var(--accent);
    color: #fff;
}
.service-table thead th {
    padding: 5px 8px;
    font-weight: 600;
    font-size: 10.5px;
    letter-spacing: 0.3px;
}
.service-table thead th:first-child { border-radius: 4px 0 0 4px; }
.service-table thead th:last-child  { border-radius: 0 4px 4px 0; }
.service-table tbody tr:nth-child(even) td { background: var(--surface); }
.service-table tbody td {
    padding: 5px 8px;
    border-bottom: 1px solid #f1f5f9;
    color: var(--ink);
    vertical-align: middle;
}
.service-table .num { text-align: right; font-variant-numeric: tabular-nums; font-weight: 500; }
.service-table .center { text-align: center; color: var(--ink-light); font-size: 10.5px; }

/* Summary rows */
.summary-rows { margin-top: 2px; }
.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 4px 8px;
    font-size: 11.5px;
}
.summary-row.subtotal { color: var(--ink-mid); border-top: 1px dashed var(--divider); margin-top: 2px; }
.summary-row.discount { color: var(--danger); }
.summary-row.total {
    background: var(--accent);
    color: #fff;
    border-radius: var(--radius);
    margin-top: 4px;
    padding: 7px 10px;
}
.summary-label { font-weight: 600; }
.summary-amount {
    font-weight: 700;
    font-size: 13px;
    font-variant-numeric: tabular-nums;
}
.summary-row.total .summary-amount { font-size: 15px; }

/* ── Signature row ── */
.sig-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    margin-top: auto;
    padding-top: 8px;
    border-top: 1px dashed var(--divider);
    font-size: 11px;
    color: var(--ink-mid);
    gap: 10px;
}
.sig-block { flex: 1; }
.sig-line {
    border-bottom: 1px solid var(--ink);
    min-width: 100px;
    margin-bottom: 4px;
    height: 22px;
}
.sig-name { font-size: 10.5px; text-align: center; }

/* ── Footer warning ── */
.receipt-footer {
    background: #fef9ec;
    border-top: 1px solid #fde68a;
    padding: 5px 12px;
    font-size: 9.5px;
    color: #92400e;
    text-align: center;
    font-weight: 600;
    letter-spacing: 0.3px;
}

/* ═══════════════════════════
   SCREEN-ONLY: action bar
═══════════════════════════ */
.action-bar {
    display: flex;
    justify-content: center;
    gap: 12px;
    margin-top: 20px;
    padding-bottom: 10px;
}
.btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 22px; border-radius: 8px;
    font-size: 14px; font-weight: 600; font-family: inherit;
    cursor: pointer; border: none; text-decoration: none;
    transition: all 0.18s ease;
}
.btn-primary { background: #2563eb; color: #fff; }
.btn-primary:hover { background: #1d4ed8; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(37,99,235,0.35); }
.btn-ghost { background: #fff; color: #1e293b; border: 1px solid #cbd5e1; }
.btn-ghost:hover { background: #f1f5f9; }

/* ═══════════════════════════
   PRINT RULES
═══════════════════════════ */
@media print {
    @page { size: A4 landscape; margin: 8mm 10mm; }

    *, *::before, *::after {
        color: #000000 !important;
        background: transparent !important;
        background-color: transparent !important;
        box-shadow: none !important;
        text-shadow: none !important;
    }

    body {
        background: #ffffff !important;
        padding: 0;
        font-family: 'Sarabun', 'Tahoma', 'Arial', sans-serif;
        font-size: 11px;
    }
    .page {
        gap: 14px;
        max-width: 100%;
    }
    .receipt {
        box-shadow: none;
        border: 1px solid #000000 !important;
        border-radius: 6px;
        page-break-inside: avoid;
        background: #ffffff !important;
    }
    .receipt::before {
        display: none !important;
    }
    .receipt-header {
        background: #ffffff !important;
        color: #000000 !important;
        padding: 10px 12px 10px;
        border-bottom: 1px solid #000000 !important;
    }
    .receipt-header::after {
        display: none !important;
    }
    .brand-name { font-size: 13px; font-weight: bold; }
    .brand-sub { font-size: 9.5px; color: #000000 !important; }
    .receipt-type-badge {
        background: transparent !important;
        border: 1px solid #000000 !important;
        color: #000000 !important;
        font-size: 10px;
        font-weight: bold;
    }
    .receipt-body { padding: 14px 12px 10px; gap: 9px; }
    
    .meta-row {
        background: #ffffff !important;
        border: 1px solid #000000 !important;
        border-left: 4px solid #000000 !important;
        padding: 6px 10px;
    }
    .meta-no { font-size: 13px; font-weight: bold; color: #000000 !important; }
    .meta-date { font-size: 10px; color: #000000 !important; }
    
    .info-block {
        background: #ffffff !important;
        border: 1px solid #000000 !important;
        padding: 8px 10px;
    }
    .info-block-title {
        color: #000000 !important;
        border-bottom: 1px solid #000000 !important;
        padding-bottom: 2px;
    }
    .info-block-title::after {
        display: none !important;
    }
    .info-item { font-size: 10.5px; }
    .info-label { font-size: 9.5px; color: #000000 !important; }
    .info-value { color: #000000 !important; }
    .plate-badge {
        background: transparent !important;
        border: 1px solid #000000 !important;
        color: #000000 !important;
    }
    
    .service-section-title {
        color: #000000 !important;
        border-bottom: 1px solid #000000 !important;
    }
    .service-section-title::after {
        display: none !important;
    }
    
    .service-table {
        font-size: 10.5px;
        border-collapse: collapse !important;
    }
    .service-table thead tr {
        background: #ffffff !important;
        color: #000000 !important;
        border-bottom: 1.5px solid #000000 !important;
    }
    .service-table thead th {
        font-size: 10px;
        padding: 4px 6px;
        color: #000000 !important;
        border: 1px solid #000000 !important;
    }
    .service-table tbody td {
        padding: 3px 6px;
        border: 1px solid #000000 !important;
        background: #ffffff !important;
    }
    
    .summary-row {
        font-size: 11px;
    }
    .summary-row.subtotal {
        border-top: 1px dashed #000000 !important;
    }
    .summary-row.total {
        background: #ffffff !important;
        color: #000000 !important;
        border: 1.5px solid #000000 !important;
    }
    .summary-row.total .summary-amount { font-size: 13px; }
    
    .sig-row {
        font-size: 10px;
        border-top: 1px dashed #000000 !important;
    }
    .sig-line {
        border-bottom: 1px solid #000000 !important;
    }
    
    .receipt-footer {
        font-size: 8.5px;
        padding: 4px 10px;
        background: #ffffff !important;
        border-top: 1px solid #000000 !important;
        color: #000000 !important;
    }
    .action-bar { display: none; }
}
</style>
</head>
<body onload="runPrint()">

<?php
// Reusable function to render one receipt column
function renderReceipt(array $pay, $type, $label, $branch_name, $branch_addr, $branch_tel, $tx_date, $tx_time, array $services) {
    $is_original = ($type === 'original');
?>
<div class="receipt" data-label="<?php echo $label; ?>">

    <!-- ── Header ── -->
    <div class="receipt-header">
        <div class="brand-row">
            <div class="brand-icon">🚗</div>
            <div>
                <div class="brand-name">บวรเซอร์วิส</div>
                <div class="brand-sub">
                    สถานตรวจสภาพรถเอกชน &nbsp;|&nbsp; สาขา<?php echo htmlspecialchars($branch_name); ?><br>
                    <?php echo htmlspecialchars($branch_addr); ?> &nbsp; โทร <?php echo htmlspecialchars($branch_tel); ?>
                </div>
            </div>
        </div>
        <span class="receipt-type-badge">ใบเสร็จรับเงิน &middot; <?php echo $is_original ? 'ต้นฉบับ' : 'สำเนา'; ?></span>
    </div>

    <!-- ── Body ── -->
    <div class="receipt-body">

        <!-- Receipt No + Date -->
        <div class="meta-row">
            <div class="meta-no">เลขที่ &nbsp;#<?php echo $pay['id_pay']; ?></div>
            <div class="meta-date">
                📅 <?php echo $tx_date; ?><br>
                🕐 <?php echo $tx_time; ?> น.
            </div>
        </div>

        <!-- Customer Info -->
        <div class="info-block">
            <div class="info-block-title">👤 ข้อมูลลูกค้าและชำระเงิน</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">ชื่อ - นามสกุล</span>
                    <span class="info-value"><?php echo htmlspecialchars($pay['cus_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ที่อยู่</span>
                    <span class="info-value"><?php echo htmlspecialchars($pay['cus_addr']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ทะเบียนรถ</span>
                    <span class="info-value">
                        <span class="plate-badge"><?php echo htmlspecialchars($pay['car_regis_id']); ?></span>
                        &nbsp;<?php echo htmlspecialchars($pay['PROVINCE_NAME']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">ประเภทรถ</span>
                    <span class="info-value"><?php echo htmlspecialchars($pay['car_type_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">ชำระโดย</span>
                    <span class="info-value" style="font-weight: 700; color: var(--accent);">
                    <?php
                    $pt = isset($pay['pay_type']) ? intval($pay['pay_type']) : 1;
                    if ($pt == 2) {
                        echo 'เงินโอน';
                    } elseif ($pt == 3) {
                        $pc  = floatval(isset($pay['pay_cash'])     ? $pay['pay_cash']     : 0);
                        $ptr = floatval(isset($pay['pay_transfer']) ? $pay['pay_transfer'] : 0);
                        echo 'สด: ' . number_format($pc, 2) . ' / โอน: ' . number_format($ptr, 2) . ' บาท';
                    } else {
                        echo 'เงินสด';
                    }
                    ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Services -->
        <div>
            <div class="service-section-title" style="margin-bottom:7px;">💳 รายการบริการ</div>
            <table class="service-table">
                <thead>
                    <tr>
                        <th class="center" style="width:28px;">#</th>
                        <th style="text-align:left;">รายการ</th>
                        <th style="text-align:right; width:90px;">จำนวนเงิน</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $i => $s): ?>
                    <tr>
                        <td class="center"><?php echo $i + 1; ?></td>
                        <td><?php echo $s['label']; ?></td>
                        <td class="num"><?php echo number_format($s['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($services)): ?>
                    <tr><td colspan="3" style="text-align:center; color:#94a3b8; padding:8px;">— ไม่มีรายการ —</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Summary -->
            <div class="summary-rows">
                <?php if ($pay['pay_sum'] != $pay['pay_total']): ?>
                <div class="summary-row subtotal">
                    <span class="summary-label">รวมก่อนส่วนลด</span>
                    <span class="summary-amount"><?php echo number_format($pay['pay_sum'], 2); ?></span>
                </div>
                <div class="summary-row discount">
                    <span class="summary-label">ส่วนลด</span>
                    <span class="summary-amount">- <?php echo number_format($pay['discount'], 2); ?></span>
                </div>
                <?php endif; ?>
                <div class="summary-row total">
                    <span class="summary-label">ยอดชำระสุทธิ (บาท)</span>
                    <span class="summary-amount"><?php echo number_format($pay['pay_total'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Signature -->
        <div class="sig-row">
            <div class="sig-block">
                <div style="font-size:10px; color:#64748b; margin-bottom:3px;">ลงชื่อผู้รับเงิน</div>
                <div class="sig-line"></div>
                <div class="sig-name">( <?php echo htmlspecialchars($pay['emp_name']); ?> )</div>
            </div>
            <div class="sig-block" style="text-align:right;">
                <div style="font-size:10px; color:#64748b; margin-bottom:3px;">วันรับเล่มทะเบียน</div>
                <div class="sig-line"></div>
                <div class="sig-name">&nbsp;</div>
            </div>
        </div>

    </div><!-- /.receipt-body -->

    <!-- Footer warning -->
    <div class="receipt-footer">
        ⚠ กรุณาอย่ารับบิลที่มีการแก้ไขตัวเลขโดยการเขียนหรือแก้ไขขูดลบ
    </div>

</div><!-- /.receipt -->
<?php } ?>

<div class="page">
    <?php renderReceipt($pay, 'copy',     'COPY',     $branch_name, $branch_addr, $branch_tel, $tx_date, $tx_time, $services); ?>
    <?php renderReceipt($pay, 'original', 'ORIGINAL', $branch_name, $branch_addr, $branch_tel, $tx_date, $tx_time, $services); ?>
</div>

<!-- Screen-only action bar -->
<div class="action-bar">
    <button onclick="window.print()" class="btn btn-primary">🖨️ พิมพ์ใบเสร็จ</button>
    <button id="back-btn" onclick="goBack()" class="btn btn-ghost">← กลับหน้ารายการขาย</button>
</div>

<script>
function runPrint() {
    window.print();
    updateButtonToClose();
}

function updateButtonToClose() {
    var btn = document.getElementById('back-btn');
    if (btn) {
        btn.innerHTML = '❌ ปิดหน้าต่างนี้';
        btn.onclick = function() { goBack(); };
    }
}

function goBack() {
    // Set flag so billing.php knows to refresh when it regains focus
    try { localStorage.setItem('bovorn_receipt_printed', '1'); } catch(e) {}
    if (window.opener && !window.opener.closed) {
        try { window.opener.location.reload(); } catch(e) {}
        window.close();
    } else {
        window.location.href = 'billing.php';
    }
}

// Set localStorage flag immediately (pay_status is already updated server-side)
try { localStorage.setItem('bovorn_receipt_printed', '1'); } catch(e) {}

// Also try to reload opener immediately as fast path
if (window.opener && !window.opener.closed) {
    try { window.opener.location.reload(); } catch(e) {}
}

// Hook to afterprint for browsers that handle printing asynchronously
window.addEventListener('afterprint', updateButtonToClose);
</script>
</body>
</html>
