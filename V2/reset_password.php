<?php
// V2/reset_password.php
// Foolproof password reset utility for Bovorn Service V2

header('Content-Type: text/html; charset=utf-8');
echo "<h2 style='text-align:center; font-family:sans-serif;'>Bovorn Service V2 - Password Reset Utility</h2>";

require("db_connect.php"); // Connects using PDO

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = trim($_POST['new_password']);
    
    if (empty($username) || empty($new_password)) {
        $message = "<p style='color:red;'>กรุณากรอกข้อมูลให้ครบถ้วน</p>";
    } else {
        try {
            // First verify if user exists
            $stmt = $pdo->prepare("SELECT id_emp FROM `employee` WHERE `username` = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Ensure column is VARCHAR(255) to prevent truncation
                $pdo->exec("ALTER TABLE `employee` MODIFY COLUMN `password` VARCHAR(255) NOT NULL;");
                
                // Hash and update
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt_update = $pdo->prepare("UPDATE `employee` SET `password` = :password WHERE `id_emp` = :id");
                $stmt_update->execute([
                    ':password' => $hash,
                    ':id' => $user['id_emp']
                ]);
                $message = "<p style='color:green; font-weight:bold; font-size:1.1rem;'>✅ รีเซ็ตรหัสผ่านของผู้ใช้ <strong>" . htmlspecialchars($username) . "</strong> สำเร็จแล้ว! รหัสผ่านใหม่ของท่านคือ: <strong>" . htmlspecialchars($new_password) . "</strong></p>";
            } else {
                $message = "<p style='color:red;'>❌ ไม่พบชื่อผู้ใช้ <strong>" . htmlspecialchars($username) . "</strong> ในระบบ</p>";
            }
        } catch (\PDOException $e) {
            $message = "<p style='color:red;'>❌ เกิดข้อผิดพลาด: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
}

// Fetch all available usernames for easy dropdown selection
try {
    $stmt = $pdo->query("SELECT username, name FROM `employee` ORDER BY username ASC");
    $users = $stmt->fetchAll();
} catch (\PDOException $e) {
    $users = [];
}
?>

<div style="background:#f4f4f4; padding:20px; border-radius:8px; max-width:450px; margin:20px auto; border:1px solid #ccc; font-family:sans-serif;">
    <?php echo $message; ?>
    <form method="POST" action="reset_password.php">
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">เลือกชื่อผู้ใช้ (Username):</label>
            <select name="username" style="width:100%; padding:8px; border-radius:4px;" required>
                <option value="">-- เลือกผู้ใช้ --</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?php echo htmlspecialchars($u['username']); ?>">
                        <?php echo htmlspecialchars($u['username']) . " (" . htmlspecialchars($u['name']) . ")"; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="margin-bottom:15px;">
            <label style="display:block; margin-bottom:5px; font-weight:bold;">ตั้งรหัสผ่านใหม่:</label>
            <input type="text" name="new_password" placeholder="พิมพ์รหัสผ่านใหม่ที่ต้องการ" style="width:96%; padding:8px; border-radius:4px;" required>
        </div>
        <button type="submit" style="background:#007bff; color:#fff; padding:10px 15px; border:none; border-radius:4px; font-weight:bold; cursor:pointer; width:100%;">
            💾 บันทึกรหัสผ่านใหม่
        </button>
    </form>
    <p style="text-align:center; font-size:0.85rem; color:#777; margin-top:20px;">
        <a href="login.php">← ไปหน้าล็อกอิน</a>
    </p>
</div>
