<?php
// daily_closing.php — สรุปส่งยอดรายวัน (Bovorn Service V2)
// Live daily closing report for one branch: income (by service, cash/transfer), expenses
// (tax forwarding = same total as print_tax_referral.php, plus other expenses saved in database)
// and the cash to transfer into the account = cash − tax forwarding − other expenses.

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

// ── Handle Save Expenses POST ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_expenses') {
    validate_csrf();
    
    // Authorization check: clerks can only save for their own branch
    if (!$is_manager && $id_branch != intval($_SESSION['id_branch'])) {
        http_response_code(403);
        die("Access Denied: You cannot modify expenses for another branch.");
    }
    
    $post_day = isset($_POST['day']) ? intval($_POST['day']) : $day;
    $post_month = isset($_POST['month']) ? intval($_POST['month']) : $month;
    $post_year = isset($_POST['year']) ? intval($_POST['year']) : $year_be;
    $post_year_ad = $post_year > 2500 ? $post_year - 543 : $post_year;
    $exp_date_str = sprintf('%04d-%02d-%02d', $post_year_ad, $post_month, $post_day);
    
    $descriptions = isset($_POST['expense_desc']) && is_array($_POST['expense_desc']) ? $_POST['expense_desc'] : [];
    $amounts = isset($_POST['expense_amount']) && is_array($_POST['expense_amount']) ? $_POST['expense_amount'] : [];
    $id_emp = isset($_SESSION['id_emp']) ? intval($_SESSION['id_emp']) : null;
    
    try {
        $pdo->beginTransaction();
        
        // Remove previous expense records for this branch and date
        $del_stmt = $pdo->prepare("DELETE FROM `daily_expense` WHERE id_branch = :branch AND expense_date = :exp_date");
        $del_stmt->execute([':branch' => $id_branch, ':exp_date' => $exp_date_str]);
        
        // Insert new records
        $ins_stmt = $pdo->prepare("INSERT INTO `daily_expense` (id_branch, expense_date, expense_desc, expense_amount, id_emp) VALUES (:branch, :exp_date, :desc, :amount, :id_emp)");
        
        $len = count($descriptions);
        for ($i = 0; $i < $len; $i++) {
            $desc = trim($descriptions[$i]);
            $amt = isset($amounts[$i]) ? floatval($amounts[$i]) : 0;
            if ($desc !== '' && $amt > 0) {
                $ins_stmt->execute([
                    ':branch' => $id_branch,
                    ':exp_date' => $exp_date_str,
                    ':desc' => $desc,
                    ':amount' => $amt,
                    ':id_emp' => $id_emp
                ]);
            }
        }
        
        $pdo->commit();
        header("Location: daily_closing.php?day=$post_day&month=$post_month&year=$post_year&id_branch=$id_branch&saved=1");
        exit;
    } catch (\PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Save daily expenses error: " . $e->getMessage());
        $db_error = "เกิดข้อผิดพลาดในการบันทึกรายจ่าย กรุณาลองใหม่อีกครั้ง";
    }
}

// ── Query branch list and totals ──
try {
    $branches = $pdo->query("SELECT id_branch, branch_name FROM `branch` ORDER BY id_branch")->fetchAll(PDO::FETCH_KEY_PAIR);
    $income = daily_closing_totals($pdo, $day, $month, $year_ad, $id_branch);
} catch (\PDOException $e) {
    error_log("daily_closing DB error: " . $e->getMessage());
    $db_error = "เกิดข้อผิดพลาดในการดึงข้อมูล กรุณาลองใหม่อีกครั้ง";
    $branches = [];
    $income = array_fill_keys(['bills', 'drafts', 'serv_1', 'serv_2', 'serv_3', 'serv_4', 'serv_5', 'serv_6', 'serv_7', 'discount', 'total',
        'cash', 'transfer', 'tax_total', 'cars', 'motorcycles', 'cancelled', 'forward_fee', 'tax_expense', 'other_expenses', 'cash_to_account'], 0);
    $income['car_rate'] = floatval(get_system_setting('tax_car_fee', 10));
    $income['motorcycle_rate'] = floatval(get_system_setting('tax_motorcycle_fee', 5));
}
$tax             = $income;
$cancelled       = $income['cancelled'];
$car_rate        = $income['car_rate'];
$motorcycle_rate = $income['motorcycle_rate'];
$tax_expense     = $income['tax_expense'];

// ── Query saved other expenses for this date and branch ──
$exp_date_str = sprintf('%04d-%02d-%02d', $year_ad, $month, $day);
$saved_expenses = [];
try {
    $exp_stmt = $pdo->prepare("SELECT expense_desc, expense_amount FROM `daily_expense` WHERE id_branch = :branch AND expense_date = :exp_date ORDER BY id_expense ASC");
    $exp_stmt->execute([':branch' => $id_branch, ':exp_date' => $exp_date_str]);
    $saved_expenses = $exp_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\PDOException $e) {
    error_log("Fetch daily expenses error: " . $e->getMessage());
}

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
            .expense-input { border: none; padding: 0; background: transparent; }
            .expense-row.empty, .remove-col, .no-print { display: none !important; }
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

                <?php if (isset($_GET['saved']) && $_GET['saved'] == '1'): ?>
                    <div class="badge badge-success no-print" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.25rem; font-size: 0.875rem; border-radius: var(--radius-md); background: #dcfce7; color: #166534; border: 1px solid #86efac;">
                        ✅ บันทึกรายการรายจ่ายอื่นๆ ลงฐานข้อมูลเรียบร้อยแล้ว
                    </div>
                <?php endif; ?>

                <?php if ($db_error): ?>
                    <p class="no-print" style="color: #b91c1c; margin-bottom: 1rem; text-align: center; font-weight: 600;"><?php echo htmlspecialchars($db_error); ?></p>
                <?php endif; ?>

                <div class="card">
                    <div class="report-head">
                        <h2 style="font-size: 1.3rem; font-weight: 800;">สรุปส่งยอดรายวัน — สาขา<?php echo htmlspecialchars($branch_name); ?></h2>
                        <div>ประจำวันที่ <strong><?php echo $date_label; ?></strong></div>
                    </div>

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
                            <tr><th colspan="3">รายจ่ายอื่นๆ (บันทึกจัดเก็บลงฐานข้อมูล)</th></tr>
                            <tbody id="expenseRows"></tbody>
                            <tr class="no-print">
                                <td colspan="3" style="padding-top: 0.75rem;">
                                    <div style="display: flex; gap: 0.5rem; align-items: center; justify-content: space-between;">
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="addExpenseRow()">+ เพิ่มรายการ</button>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="saveExpenses()" style="background:#059669; border-color:#059669; font-weight:600;">💾 บันทึกรายจ่ายลงระบบ</button>
                                    </div>
                                </td>
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

    <!-- Hidden form to save expenses via POST -->
    <form id="saveExpenseForm" method="POST" action="daily_closing.php?day=<?php echo $day; ?>&month=<?php echo $month; ?>&year=<?php echo $year_be; ?>&id_branch=<?php echo $id_branch; ?>" style="display:none;">
        <input type="hidden" name="action" value="save_expenses">
        <input type="hidden" name="day" value="<?php echo $day; ?>">
        <input type="hidden" name="month" value="<?php echo $month; ?>">
        <input type="hidden" name="year" value="<?php echo $year_be; ?>">
        <input type="hidden" name="id_branch" value="<?php echo $id_branch; ?>">
        <?php echo csrf_field(); ?>
    </form>

    <script>
        const CASH = <?php echo json_encode((float)$income['cash']); ?>;
        const TRANSFER = <?php echo json_encode((float)$income['transfer']); ?>;
        const TAX_EXPENSE = <?php echo json_encode((float)$tax_expense); ?>;
        const SAVED_EXPENSES = <?php echo json_encode($saved_expenses ?: [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

        function fmt(n) {
            return n.toLocaleString('th-TH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function escapeHtml(str) {
            return String(str || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function addExpenseRow(focus = true, initialDesc = '', initialAmt = '') {
            const tr = document.createElement('tr');
            tr.className = 'expense-row' + (initialDesc === '' && !initialAmt ? ' empty' : '');
            tr.innerHTML =
                '<td><input type="text" class="expense-input" placeholder="รายการ เช่น ค่าน้ำมัน" value="' + escapeHtml(initialDesc) + '"></td>' +
                '<td class="amt"><input type="number" step="0.01" min="0" class="expense-input amount" placeholder="0.00" value="' + (initialAmt ? parseFloat(initialAmt).toFixed(2) : '') + '"></td>' +
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

        function saveExpenses() {
            const form = document.getElementById('saveExpenseForm');
            form.querySelectorAll('.dyn-input').forEach(el => el.remove());
            
            let count = 0;
            let totalAmt = 0;
            document.querySelectorAll('#expenseRows .expense-row').forEach(tr => {
                const [descInput, amountInput] = tr.querySelectorAll('input');
                const desc = descInput.value.trim();
                const amt = parseFloat(amountInput.value) || 0;
                if (desc !== '' || amt > 0) {
                    count++;
                    totalAmt += amt;
                    const hDesc = document.createElement('input');
                    hDesc.type = 'hidden';
                    hDesc.name = 'expense_desc[]';
                    hDesc.value = desc;
                    hDesc.className = 'dyn-input';
                    form.appendChild(hDesc);
                    
                    const hAmt = document.createElement('input');
                    hAmt.type = 'hidden';
                    hAmt.name = 'expense_amount[]';
                    hAmt.value = amt;
                    hAmt.className = 'dyn-input';
                    form.appendChild(hAmt);
                }
            });
            
            if (count > 0) {
                if (!confirm('ยืนยันบันทึกรายจ่ายจำนวน ' + count + ' รายการ (รวม ' + fmt(totalAmt) + ' บาท) ลงระบบหรือไม่?')) {
                    return;
                }
            } else {
                if (!confirm('ไม่มีรายการรายจ่ายที่กรอกไว้ ระบบจะล้างรายจ่ายของวันนี้ออกทั้งหมด ต้องการดำเนินการต่อหรือไม่?')) {
                    return;
                }
            }
            form.submit();
        }

        // Initialize with saved expenses from database or 1 blank row
        if (SAVED_EXPENSES && SAVED_EXPENSES.length > 0) {
            SAVED_EXPENSES.forEach(exp => {
                addExpenseRow(false, exp.expense_desc, exp.expense_amount);
            });
        } else {
            addExpenseRow(false);
        }
        recalc();
    </script>
</body>
</html>