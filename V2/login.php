<?php
// login.php
// Login page and authentication handler for Bovorn Service V2

require_once("db_connect.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to dashboard if already logged in
if (isset($_SESSION['id_emp'])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// Check lockout
if (isset($_SESSION['login_lockout_until']) && time() < $_SESSION['login_lockout_until']) {
    $remaining_seconds = $_SESSION['login_lockout_until'] - time();
    $remaining_minutes = ceil($remaining_seconds / 60);
    $error = "คุณกรอกรหัสผ่านผิดเกินกำหนด ระบบถูกล็อกชั่วคราว กรุณารออีก $remaining_minutes นาที";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    
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
                // Defend against Session Fixation: regenerate session ID
                session_regenerate_id(true);
                
                // Clear lockout counters
                unset($_SESSION['login_attempts']);
                unset($_SESSION['login_lockout_until']);
                
                $_SESSION['id_emp'] = $authenticated_user['id_emp'];
                $_SESSION['id_emp_type'] = $authenticated_user['id_emp_type'];
                $_SESSION['id_branch'] = $authenticated_user['id_branch'];
                $_SESSION['name'] = $authenticated_user['name'];
                
                header("Location: dashboard.php");
                exit;
            } else {
                // Increment failed attempts
                $attempts = isset($_SESSION['login_attempts']) ? intval($_SESSION['login_attempts']) + 1 : 1;
                $_SESSION['login_attempts'] = $attempts;
                
                if ($attempts >= 5) {
                    $_SESSION['login_lockout_until'] = time() + (5 * 60); // 5 minutes lockout
                    $error = "คุณกรอกรหัสผ่านผิดเกิน 5 ครั้ง ระบบถูกล็อกชั่วคราวเป็นเวลา 5 นาที";
                } else {
                    $remaining = 5 - $attempts;
                    $error = "ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง (เหลือโอกาสอีก $remaining ครั้ง)";
                }
            }
        } catch (\PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = "เกิดข้อผิดพลาดของระบบในการเชื่อมต่อ กรุณาลองใหม่อีกครั้ง";
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
            <?php echo csrf_field(); ?>
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