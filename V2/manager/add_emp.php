<?php
// manager/add_emp.php
// Add new employee account (Manager view) for Bovorn Service V2

require("con_db.php");

$error = "";

// Fetch branches & roles for dropdowns
try {
    $stmt_b = $pdo->query("SELECT * FROM `branch`");
    $branches = $stmt_b->fetchAll();
    
    $stmt_t = $pdo->query("SELECT * FROM `emp_type`");
    $roles = $stmt_t->fetchAll();
} catch (\PDOException $e) {
    $error = "เกิดข้อผิดพลาดในการดึงข้อมูลตัวเลือก: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $addr = trim($_POST['addr']);
    $phone = trim($_POST['phone']);
    $position = intval($_POST['position']);
    $branch = intval($_POST['branch']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($name) || empty($addr) || empty($phone) || $position === 0 || $branch === 0 || empty($username) || empty($password)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง";
    } else {
        try {
            // Check unique username per branch
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM `employee` WHERE `username` = :username AND `id_branch` = :branch");
            $stmt_check->execute([':username' => $username, ':branch' => $branch]);
            if ($stmt_check->fetchColumn() > 0) {
                $error = "ชื่อผู้ใช้นี้ถูกใช้งานในสาขาที่เลือกแล้ว กรุณาใช้ชื่ออื่น";
            } else {
                // Securely hash password
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                
                $sql = "INSERT INTO `employee` (name, addr, phone, username, password, id_emp_type, id_branch) 
                        VALUES (:name, :addr, :phone, :username, :password, :position, :branch)";
                $stmt_insert = $pdo->prepare($sql);
                $stmt_insert->execute([
                    ':name' => $name,
                    ':addr' => $addr,
                    ':phone' => $phone,
                    ':username' => $username,
                    ':password' => $password_hash,
                    ':position' => $position,
                    ':branch' => $branch
                ]);
                
                header("Location: user_manager.php?save=success");
                exit;
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
    <title>เพิ่มพนักงาน - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">ระบบบริหารพนักงาน</div>
            </div>
            
            <div class="content-body" style="max-width: 800px; margin: 0 auto;">
                <?php if (!empty($error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card" style="border-top: 4px solid var(--primary);">
                    <div class="card-header">
                        <h3 class="card-title">➕ เพิ่มบัญชีผู้ใช้พนักงานใหม่</h3>
                    </div>
                    
                    <form action="add_emp.php" method="POST" id="addEmpForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label for="name" class="form-label">ชื่อ - นามสกุล <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" required placeholder="เช่น นายสมใจ ดีจริง">
                        </div>
                        
                        <div class="form-group">
                            <label for="addr" class="form-label">ที่อยู่ปัจจุบัน <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="addr" id="addr" class="form-control" required placeholder="บ้านเลขที่ ถนน ซอย ตำบล อำเภอ จังหวัด">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์ <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="phone" id="phone" class="form-control" required placeholder="เช่น 0891234567">
                            </div>
                            <div class="form-group">
                                <label for="position" class="form-label">ตำแหน่ง / สิทธิ์ผู้ใช้ <span style="color:var(--danger)">*</span></label>
                                <select name="position" id="position" class="form-control" required>
                                    <option value="0">-- เลือกตำแหน่ง --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id_emp_type']; ?>"><?php echo htmlspecialchars($r['emp_type_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="branch" class="form-label">สาขาประจำการ <span style="color:var(--danger)">*</span></label>
                                <select name="branch" id="branch" class="form-control" required>
                                    <option value="0">-- เลือกสาขา --</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo $b['id_branch']; ?>"><?php echo htmlspecialchars($b['branch_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row" style="background-color: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1.5rem; margin-top: 1rem;">
                            <div class="form-group">
                                <label for="username" class="form-label">ชื่อผู้ใช้ (Username) <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="username" id="username" class="form-control" required placeholder="สำหรับล็อกอินเข้าระบบ">
                            </div>
                            <div class="form-group">
                                <label for="password" class="form-label">รหัสผ่าน (Password) <span style="color:var(--danger)">*</span></label>
                                <input type="password" name="password" id="password" class="form-control" required placeholder="รหัสผ่านเข้าสู่ระบบ">
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                            <a href="user_manager.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function validateForm() {
            if (document.getElementById('name').value.trim() === "") {
                alert("กรุณากรอกชื่อพนักงาน");
                return false;
            }
            if (document.getElementById('addr').value.trim() === "") {
                alert("กรุณากรอกที่อยู่พนักงาน");
                return false;
            }
            if (document.getElementById('phone').value.trim() === "") {
                alert("กรุณากรอกเบอร์โทรศัพท์");
                return false;
            }
            if (document.getElementById('position').value == "0") {
                alert("กรุณาเลือกตำแหน่งของพนักงาน");
                return false;
            }
            if (document.getElementById('branch').value == "0") {
                alert("กรุณาเลือกสาขาของพนักงาน");
                return false;
            }
            if (document.getElementById('username').value.trim() === "") {
                alert("กรุณาตั้งค่าชื่อผู้ใช้ (Username)");
                return false;
            }
            if (document.getElementById('password').value.trim() === "") {
                alert("กรุณาตั้งค่ารหัสผ่าน (Password)");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
