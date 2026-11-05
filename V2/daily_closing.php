<?php
// daily_closing.php — สรุปส่งยอดรายวัน (Bovorn Service V2)
// Opened as a popup from the "สรุปส่งยอดรายวัน" button next to the tax-referral button in the sales reports.
// Live daily closing report for one branch: income (by service, cash/transfer), expenses
// (tax forwarding = same total as print_tax_referral.php, plus other expenses typed in on this page)
// and the cash to transfer into the account = cash − tax forwarding − other expenses.
// Nothing is saved: other expenses exist only on the page/printout.

require("db_connect.php");
require("daily_closing_lib.php");
check_login([1, 2]);
date_default_timezone_set('Asia/Bangkok');

$is_manager = $_SESSION['id_emp_type'] == 1;

// ── Date (year accepted as BE or AD, like print_tax_referral.php) ──
$day   = isset($_GET['day'])   ? intval($_GET['day'])   : intval(date('d'));
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year_input = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y')) + 543;
$year_ad = $year_input > 2500 ? $year_input - 543 : $year_input;
$year_be = $year_ad + 543;
if (!checkdate($month, $day, $year_ad)) {
    $day = intval(date('d')); $month = intval(date('m')); $year_ad = intval(date('Y')); $year_be = $year_ad + 543;
}

// ── Branch: managers choose, clerks always see their own ──
$id_branch = ($is_manager && isset($_GET['id_branch'])) ? intval($_GET['id_branch']) : intval($_SESSION['id_branch']);

$thai_months = [1 => 'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
                'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'];

$db_error = null;
try {
    $branches = $pdo->query("SELECT id_branch, branch_name FROM `branch` ORDER BY id_branch")->fetchAll(PDO::FETCH_KEY_PAIR);
    $income = daily_closing_totals($pdo, $day, $month, $year_ad, $id_branch);
} catch (\PDOException $e) {
    error_log("daily_closing DB error: " . $e->getMessage());
    $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูล กรุณาลองใหม่อีกครั้ง";
    $branches = [];
    $income = array_fill_keys(['bills', 'drafts', 'serv_1', 'serv_2', 'serv_3', 'serv_4', 'serv_5', 'serv_6', 'serv_7', 'discount', 'total',
        'cash', 'transfer', 'tax_total', 'cars', 'motorcycles', 'cancelled', 'forward_fee', 'tax_expense', 'cash_to_account'], 0);
    $income['car_rate'] = floatval(get_system_setting('tax_car_fee', 10));
    $income['motorcycle_rate'] = floatval(get_system_setting('tax_motorcycle_fee', 5));
}
$tax             = $income;
$cancelled       = $income['cancelled'];
$car_rate        = $income['car_rate'];
$motorcycle_rate = $income['motorcycle_rate'];
$tax_expense     = $income['tax_expense'];

$branch_name = isset($branches[$id_branch]) ? $branches[$id_branch] : ('สาขา ' . $id_branch);
$date_label  = $day . ' ' . $thai_months[$month] . ' ' . $year_be;

$service_lines = [
    'serv_1' => 'ต่อ พ.ร.บ.',
    'serv_2' => 'ตรวจสภาพรถ (ตรอ.)',
    'serv_3' => 'ภาษีประจำปี',
    'serv_4' => 'ภาษีย้อนหลัง',
    'serv_5' => 'ค่าปรับล่าช้า',
    'serv_6' => 'ค่าสมุดเล่มทะเบียน',
    'serv_7' => 'ค่าบริการอื่นๆ',
];

function baht($n) { return number_format((float)$n, 2); }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปส่งยอดรายวัน - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .closing-table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        .closing-table td, .closing-table th { padding: 0.55rem 0.75rem; border-bottom: 1px solid var(--border); }
        .closing-table th { text-align: left; background: var(--bg-main); color: var(--text-muted); font-weight: 600; }
        .closing-table .amt { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
        .closing-table .sub td { color: var(--text-muted); font-size: 0.875rem; }
        .closing-table .sum td { font-weight: 700; border-top: 2px solid var(--border); }
        .closing-section { margin-bottom: 1.75rem; }
        .closing-section h3 { font-size: 1.05rem; font-weight: 700; margin-bottom: 0.6rem; }
        .closing-result { padding: 1.25rem 1.5rem; border-radius: var(--radius-md); background: var(--primary-light); }
        .closing-result .big { font-size: 1.6rem; font-weight: 800; color: var(--primary); }
        .closing-result.negative { background: #fee2e2; }
        .closing-result.negative .big { color: #b91c1c; }
        .expense-input { width: 100%; padding: 0.35rem 0.5rem; border: 1px solid var(--border); border-radius: 6px; font: inherit; }
        .expense-input.amount { text-align: right; max-width: 160px; }
        .report-head { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.25rem; }
        .signatures { display: none; }
        @media print {
            .expense-input { border: none; padding: 0; }
            .expense-row.empty, .remove-col { display: none !important; }
            .closing-result { background: none !important; border: 2px solid #000; }
            .signatures { display: flex; justify-content: space-around; margin-top: 3rem; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">📒 สรุปส่งยอดรายวัน</div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="window.close()">ปิดหน้าต่าง</button>
            </div>

            <div class="content-body">
                <!-- Filter -->
                <div class="card no-print" style="margin-bottom: 1.5rem;">
                    <form method="GET" action="daily_closing.php" class="form-row" style="align-items: end;">
                        <div class="form-group">
                            <label class="form-label">วันที่</label>
                            <select name="day" class="form-control">
                                <?php for ($d = 1; $d <= 31; $d++): ?>
                                    <option value="<?php echo $d; ?>" <?php echo $d == $day ? 'selected' : ''; ?>><?php echo $d; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">เดือน</label>
                            <select name="month" class="form-control">
                                <?php foreach ($thai_months as $m => $label): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">ปี (พ.ศ.)</label>
                            <select name="year" class="form-control">
                                <?php for ($y = intval(date('Y')) + 543; $y >= intval(date('Y')) + 540; $y--): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y == $year_be ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <?php if ($is_manager): ?>
                            <div class="form-group">
                                <label class="form-label">สาขา</label>
                                <select name="id_branch" class="form-control">
                                    <?php foreach ($branches as $bid => $bname): ?>
                                        <option value="<?php echo (int)$bid; ?>" <?php echo $bid == $id_branch ? 'selected' : ''; ?>><?php echo htmlspecialchars($bname); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>
                        <div class="form-group" style="display: flex; gap: 0.5rem;">
                            <button type="submit" class="btn btn-primary">แสดงรายงาน</button>
                            <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ พิมพ์</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <div class="report-head">
                        <h2 style="font-size: 1.3rem; font-weight: 800;">สรุปส่งยอดรายวัน — สาขา<?php echo htmlspecialchars($branch_name); ?></h2>
                        <div>ประจำวันที่ <strong><?php echo $date_label; ?></strong></div>
                    </div>

                    <?php if ($db_error): ?>
                        <p style="color: #b91c1c; margin-bottom: 1rem;"><?php echo htmlspecialchars($db_error); ?></p>
                    <?php endif; ?>
                    <?php if ($income['drafts'] > 0): ?>
                        <p class="no-print" style="color: #b45309; margin-bottom: 1rem;">⚠️ มีบิลที่ยังไม่พิมพ์ใบเสร็จ <?php echo (int)$income['drafts']; ?> ใบ (นับรวมในยอดแล้ว)</p>
                    <?php endif; ?>

                    <!-- 1. Income -->
                    <div class="closing-section">
                        <h3>1. รายรับ (<?php echo number_format($income['bills']); ?> บิล<?php echo $cancelled ? ', ไม่นับบิลยกเลิก ' . $cancelled . ' บิล' : ''; ?>)</h3>
                        <table class="closing-table">
                            <?php foreach ($service_lines as $key => $label): ?>
                                <?php if ($income[$key] != 0): ?>
                                    <tr><td><?php echo $label; ?></td><td class="amt"><?php echo baht($income[$key]); ?></td></tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($income['discount'] != 0): ?>
                                <tr><td>หักส่วนลด</td><td class="amt">−<?php echo baht($income['discount']); ?></td></tr>
                            <?php endif; ?>
                            <tr class="sum"><td>รวมรายรับ</td><td class="amt"><?php echo baht($income['total']); ?></td></tr>
                            <tr class="sub"><td>&nbsp;&nbsp;• รับเป็นเงินสด</td><td class="amt"><?php echo baht($income['cash']); ?></td></tr>
                            <tr class="sub"><td>&nbsp;&nbsp;• ลูกค้าโอนเข้าบัญชี</td><td class="amt"><?php echo baht($income['transfer']); ?></td></tr>
                        </table>
                    </div>

                    <!-- 2. Expenses -->
                    <div class="closing-section">
                        <h3>2. รายจ่าย</h3>
                        <table class="closing-table" id="expenseTable">
                            <tr>
                                <td>
                                    รายจ่ายต่อภาษี (ตามใบส่งต่อภาษีรายวัน)
                                    <div style="font-size: 0.8rem; color: var(--text-muted);">
                                        ภาษี/ค่าปรับ/ภาษีย้อนหลัง/ค่าเล่ม <?php echo baht($tax['tax_total']); ?>
                                        + ค่าส่งต่อ รถยนต์ <?php echo (int)$tax['cars']; ?> คัน × <?php echo $car_rate + 0; ?>
                                        และ จยย. <?php echo (int)$tax['motorcycles']; ?> คัน × <?php echo $motorcycle_rate + 0; ?>
                                        <a class="no-print" href="print_tax_referral.php?day=<?php echo $day; ?>&month=<?php echo $month; ?>&year=<?php echo $year_be; ?>&id_branch=<?php echo $id_branch; ?>" target="_blank">ดูใบส่งต่อภาษี</a>
                                    </div>
                                </td>
                                <td class="amt"><?php echo baht($tax_expense); ?></td>
                                <td class="remove-col no-print"></td>
                            </tr>
                            <tr><th colspan="3">รายจ่ายอื่นๆ (กรอกก่อนพิมพ์ ระบบไม่ได้บันทึกไว้)</th></tr>
                            <tbody id="expenseRows"></tbody>
                            <tr class="no-print">
                                <td colspan="3"><button type="button" class="btn btn-secondary btn-sm" onclick="addExpenseRow()">+ เพิ่มรายการ</button></td>
                            </tr>
                            <tr class="sum"><td>รวมรายจ่าย</td><td class="amt" id="expenseTotal"><?php echo baht($tax_expense); ?></td><td class="remove-col no-print"></td></tr>
                        </table>
                    </div>

                    <!-- 3. Cash to transfer into the account -->
                    <div class="closing-section">
                        <h3>3. เงินโอนเข้าบัญชี ณ ปิดยอด</h3>
                        <table class="closing-table">
                            <tr><td>รับเป็นเงินสด</td><td class="amt"><?php echo baht($income['cash']); ?></td></tr>
                            <tr><td>หัก รายจ่ายต่อภาษี</td><td class="amt">−<?php echo baht($tax_expense); ?></td></tr>
                            <tr><td>หัก รายจ่ายอื่นๆ</td><td class="amt" id="otherTotal">−0.00</td></tr>
                        </table>
                        <div class="closing-result" id="closingResult" style="margin-top: 1rem;">
                            <div id="closingLabel">เงินสดที่ต้องโอนเข้าบัญชี</div>
                            <div class="big" id="closingAmount"></div>
                            <div style="font-size: 0.875rem; margin-top: 0.4rem;">
                                ยอดเข้าบัญชีทั้งวัน (ลูกค้าโอน <?php echo baht($income['transfer']); ?> + เงินสดที่โอน) = <strong id="accountTotal"></strong> บาท
                            </div>
                        </div>
                    </div>

                    <div class="signatures">
                        <div>ลงชื่อ ............................................<br>ผู้ส่งยอด</div>
                        <div>ลงชื่อ ............................................<br>ผู้รับยอด</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const CASH = <?php echo json_encode((float)$income['cash']); ?>;
        const TRANSFER = <?php echo json_encode((float)$income['transfer']); ?>;
        const TAX_EXPENSE = <?php echo json_encode((float)$tax_expense); ?>;

        function fmt(n) {
            return n.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function addExpenseRow(focus = true) {
            const tr = document.createElement('tr');
            tr.className = 'expense-row empty';
            tr.innerHTML =
                '<td><input type="text" class="expense-input" placeholder="รายการ เช่น ค่าน้ำมัน"></td>' +
                '<td class="amt"><input type="number" step="0.01" min="0" class="expense-input amount" placeholder="0.00"></td>' +
                '<td class="remove-col no-print"><button type="button" class="btn btn-danger btn-sm" title="ลบรายการ">✕</button></td>';
            tr.querySelectorAll('input').forEach(input => input.addEventListener('input', recalc));
            tr.querySelector('button').addEventListener('click', () => { tr.remove(); recalc(); });
            document.getElementById('expenseRows').appendChild(tr);
            if (focus) tr.querySelector('input').focus();
        }

        function recalc() {
            let other = 0;
            document.querySelectorAll('#expenseRows .expense-row').forEach(tr => {
                const [desc, amount] = tr.querySelectorAll('input');
                const value = Math.max(0, parseFloat(amount.value) || 0);
                other += value;
                // Rows left blank are not printed
                tr.classList.toggle('empty', value === 0 && desc.value.trim() === '');
            });
            const closing = CASH - TAX_EXPENSE - other;
            document.getElementById('expenseTotal').textContent = fmt(TAX_EXPENSE + other);
            document.getElementById('otherTotal').textContent = '−' + fmt(other);
            document.getElementById('closingAmount').textContent = fmt(Math.abs(closing)) + ' บาท';
            document.getElementById('closingLabel').textContent = closing >= 0
                ? 'เงินสดที่ต้องโอนเข้าบัญชี'
                : 'เงินสดไม่พอจ่าย — ต้องใช้เงินจากบัญชีเพิ่ม';
            document.getElementById('closingResult').classList.toggle('negative', closing < 0);
            document.getElementById('accountTotal').textContent = fmt(TRANSFER + closing);
        }

        addExpenseRow(false);
        recalc();
    </script>
</body>
</html>
