<?php
// V2/test_conn.php
// Script to test database connection and verify usernames/roles (Restricted to Managers)

require("db_connect.php"); // Connects using PDO
check_login([1]); // Security enforcement: Only managers (role = 1) can access

header('Content-Type: text/html; charset=utf-8');
echo "<h2>Bovorn Service V2 - Database Connection Test Page</h2>";

try {
    // 1. Verify Connection
    echo "<p style='color:green; font-weight:bold; font-size:1.2rem;'>✅ Database Connected Successfully!</p>";
    echo "<p><strong>Host:</strong> " . htmlspecialchars($host) . "</p>";
    echo "<p><strong>Database Name:</strong> " . htmlspecialchars($db) . "</p>";
    echo "<p><strong>Charset:</strong> " . htmlspecialchars($charset) . "</p>";
    
    // 2. Fetch Employee list to verify usernames
    echo "<h3>👥 พนักงานในระบบ (ที่เชื่อมต่อได้):</h3>";
    $stmt = $pdo->query("SELECT id_emp, name, username, id_emp_type, LENGTH(password) as pass_len, password FROM `employee` ORDER BY id_emp_type ASC");
    $users = $stmt->fetchAll();
    
    echo "<table border='1' cellpadding='8' style='border-collapse:collapse;'>";
    echo "<tr style='background-color:#eee;'>
            <th>ID</th>
            <th>ชื่อ</th>
            <th>ชื่อผู้ใช้ (Username)</th>
            <th>ประเภท (1=ผู้จัดการ, 2=พนักงานขาย)</th>
            <th>ความยาวรหัสผ่านใน DB</th>
            <th>รหัสผ่านที่เก็บใน DB (20 ตัวแรก)</th>
          </tr>";
    
    foreach ($users as $u) {
        $type = $u['id_emp_type'] == 1 ? "ผู้จัดการ" : "พนักงานขาย";
        $hash_preview = htmlspecialchars(substr($u['password'], 0, 20)) . "...";
        echo "<tr>";
        echo "<td>" . $u['id_emp'] . "</td>";
        echo "<td>" . htmlspecialchars($u['name']) . "</td>";
        echo "<td><strong style='color:blue;'>" . htmlspecialchars($u['username']) . "</strong></td>";
        echo "<td>" . $type . "</td>";
        echo "<td>" . $u['pass_len'] . " ตัวอักษร</td>";
        echo "<td><code>" . $hash_preview . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (\PDOException $e) {
    echo "<p style='color:red; font-weight:bold; font-size:1.2rem;'>❌ Database Connection Failed!</p>";
    echo "<p><strong>Error Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>