<?php
// daily_closing_lib.php — shared figures for "สรุปส่งยอดรายวัน"
// Used by daily_closing.php (full report) and manager/pay_list.php (per-branch summary boxes),
// so both always show the same numbers.

// Day totals for one branch (cancelled bills excluded):
//   income by service, cash / transfer, tax forwarding (same rows and formula as print_tax_referral.php)
//   and the cash to transfer into the owner's account before other expenses.
// $year_ad is the Gregorian year. Throws PDOException on database errors.
function daily_closing_totals($pdo, $day, $month, $year_ad, $id_branch) {
    $params = [':day' => $day, ':month' => $month, ':year' => $year_ad, ':branch' => $id_branch];
    $where = "pay_day = :day AND pay_month = :month AND pay_year = :year AND id_branch = :branch";

    // Cash/transfer are derived from pay_type because bills migrated from the old system have pay_cash/pay_transfer = 0
    $stmt = $pdo->prepare("SELECT
            COUNT(*) AS bills,
            COALESCE(SUM(pay_status = 1), 0) AS drafts,
            COALESCE(SUM(serv_1), 0) AS serv_1, COALESCE(SUM(serv_2), 0) AS serv_2, COALESCE(SUM(serv_3), 0) AS serv_3,
            COALESCE(SUM(serv_4), 0) AS serv_4, COALESCE(SUM(serv_5), 0) AS serv_5, COALESCE(SUM(serv_6), 0) AS serv_6,
            COALESCE(SUM(serv_7), 0) AS serv_7, COALESCE(SUM(discount), 0) AS discount, COALESCE(SUM(pay_total), 0) AS total,
            COALESCE(SUM(CASE pay_type WHEN 2 THEN 0 WHEN 3 THEN pay_cash ELSE pay_total END), 0) AS cash,
            COALESCE(SUM(CASE pay_type WHEN 2 THEN pay_total WHEN 3 THEN pay_transfer ELSE 0 END), 0) AS transfer
        FROM `payment` WHERE $where AND pay_status != 3");
    $stmt->execute($params);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    // Car type 4 = motorcycle
    $stmt = $pdo->prepare("SELECT
            COALESCE(SUM(serv_3 + serv_4 + serv_5 + serv_6), 0) AS tax_total,
            COALESCE(SUM(id_car_type <> 4), 0) AS cars,
            COALESCE(SUM(id_car_type = 4), 0) AS motorcycles
        FROM `payment` WHERE $where AND pay_status != 3
          AND (serv_3 > 0 OR serv_4 > 0 OR serv_5 > 0 OR serv_6 > 0)");
    $stmt->execute($params);
    $t += $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `payment` WHERE $where AND pay_status = 3");
    $stmt->execute($params);
    $t['cancelled'] = (int)$stmt->fetchColumn();

    $t['car_rate']        = floatval(get_system_setting('tax_car_fee', 10));
    $t['motorcycle_rate'] = floatval(get_system_setting('tax_motorcycle_fee', 5));
    $t['forward_fee']     = $t['cars'] * $t['car_rate'] + $t['motorcycles'] * $t['motorcycle_rate'];
    $t['tax_expense']     = $t['tax_total'] + $t['forward_fee'];
    $t['cash_to_account'] = $t['cash'] - $t['tax_expense'];
    return $t;
}
