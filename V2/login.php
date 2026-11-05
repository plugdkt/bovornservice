<?php
// login.php
// Login page and authentication handler for Bovorn Service V2

session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['id_emp'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require("db_connect.php");
    
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($username) || empty($password)) {
        $error = "กรุณากรอกชื่อผู้ใช้และรหัสผ่าน";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM `employee` WHERE `username` = :username");
            $stmt->execute([':username' => $username]);
            $users = $stmt->fetchAll();
            
            $authenticated_user = null;
            foreach ($users as $u) {
                if (password_verify($password, $u['password'])) {
                    $authenticated_user = $u;
                    break;
                }
            }
            
            if ($authenticated_user) {
                $_SESSION['id_emp'] = $authenticated_user['id_emp'];
                $_SESSION['id_emp_type'] = $authenticated_user['id_emp_type'];
                $_SESSION['id_branch'] = $authenticated_user['id_branch'];
                $_SESSION['name'] = $authenticated_user['name'];
                
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง";
            }
        } catch (\PDOException $e) {
            $error = "เกิดข้อผิดพลาดของระบบ: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - Bovorn Service V2</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div class="login-logo">Bovorn Service</div>
            <p style="color: var(--text-muted); font-size: 0.875rem;">ระบบจัดการตรวจสภาพรถเอกชน (ตรอ.)</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; border-radius: var(--radius-md); margin-bottom: 1.25rem; font-size: 0.875rem;">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="username" class="form-label">ชื่อผู้ใช้ (Username)</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="กรอกชื่อผู้ใช้" required autofocus>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">รหัสผ่าน (Password)</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="กรอกรหัสผ่าน" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; margin-top: 1rem; font-weight: 600;">
                เข้าสู่ระบบ
            </button>
        </form>
    </div>
</body>
</html>
