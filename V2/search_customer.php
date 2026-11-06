<?php
// search_customer.php
// AJAX endpoint: customer autocomplete with autofill data
//   ?q=...            search by customer name  (latest bill per name)
//   ?q=...&by=plate   search by licence plate  (latest bill per plate + province; spaces/dashes ignored)
// Returns JSON array of up to 8 most-recent matches

require("db_connect.php");
check_login([2]); // Only clerk (role = 2)

header('Content-Type: application/json; charset=utf-8');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$by_plate = isset($_GET['by']) && $_GET['by'] === 'plate';
if ($by_plate) {
    // Plates are stored as typed ("กข 1234", "กข-1234", "กข1234"), so compare without separators
    $q = str_replace([' ', '-'], '', $q);
}
$id_branch = $_SESSION['id_branch'];

// Require at least 2 characters
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

try {
    /*
     * Performance-optimised query using a derived-table subquery instead of a
     * correlated subquery.
     *
     * Old approach (slow, O(n²)):
     *   WHERE id_pay = (SELECT MAX(p2.id_pay) FROM payment p2 WHERE p2.cus_name = p.cus_name ...)
     *   → MySQL re-executes the inner SELECT for EVERY row in the outer table.
     *
     * New approach (fast, O(n log n)):
     *   1. Inner: GROUP BY cus_name → get MAX(id_pay) per unique name in one pass.
     *   2. Outer: JOIN on id_pay → fetch only the winning rows.
     */
    $plate_key = "REPLACE(REPLACE(car_regis_id, ' ', ''), '-', '')";
    $latest = $by_plate
        ? "SELECT MAX(id_pay) AS max_id
                FROM `payment`
                WHERE id_branch  = :id_branch
                  AND $plate_key LIKE :q
                  AND pay_status != 3
                GROUP BY $plate_key, province_id"
        : "SELECT MAX(id_pay) AS max_id
                FROM `payment`
                WHERE id_branch  = :id_branch
                  AND cus_name   LIKE :q
                  AND pay_status != 3
                GROUP BY cus_name";

    $sql = "SELECT p.cus_name, p.cus_addr, p.cus_phone,
                   p.car_regis_id, p.id_car_type, p.province_id,
                   c.car_type_name, pr.PROVINCE_NAME
            FROM `payment` p
            JOIN ($latest) latest ON p.id_pay = latest.max_id
            JOIN `car_type` c  ON p.id_car_type = c.id_car_type
            JOIN `province` pr ON p.province_id  = pr.PROVINCE_ID
            ORDER BY p.id_pay DESC
            LIMIT 8";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id_branch' => $id_branch,
        ':q'         => '%' . $q . '%',
    ]);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);

} catch (\PDOException $e) {
    error_log("search_customer error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
