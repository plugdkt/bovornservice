<?php
// manager/edit_emp.php
// Edit employee details (Manager view) for Bovorn Service V2

require("con_db.php");

$id_emp = isset($_GET['id_emp']) ? intval($_GET['id_emp']) : 0;

if ($id_emp === 0) {
    header("Location: user_manager.php");
    exit;
}

$error = "";

try {
    // Fetch employee details
    $stmt = $pdo->prepare("SELECT * FROM `employee` WHERE `id_emp` = :id_emp");
    $stmt->execute([':id_emp' => $id_emp]);
    $emp = $stmt->fetch();
    
    if (!$emp) {
        die("Error: Employee not found.");
    }
    
    // Fetch branches & roles for dropdowns
    $stmt_b = $pdo->query("SELECT * FROM `branch`");
    $branches = $stmt_b->fetchAll();
    
    $stmt_t = $pdo->query("SELECT * FROM `emp_type`");
    $roles = $stmt_t->fetchAll();
} catch (\PDOException $e) {
    $error = "เกิดข้อผิดพลาดของระบบ: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();
    $name = trim($_POST['name']);
    $addr = trim($_POST['addr']);
    $phone = trim($_POST['phone']);
    $position = intval($_POST['position']);
    $branch = intval($_POST['branch']);
    $username = trim($_POST['username']);
    $password_new = trim($_POST['password_new']);
    
    if (empty($name) || empty($addr) || empty($phone) || $position === 0 || $branch === 0 || empty($username)) {
        $error = "กรุณากรอกข้อมูลให้ครบถ้วน";
    } else {
        try {
            // Check unique username globally (if changed)
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM `employee` WHERE `username` = :username AND `id_emp` != :id_emp");
            $stmt_check->execute([':username' => $username, ':id_emp' => $id_emp]);
            
            if ($stmt_check->fetchColumn() > 0) {
                $error = "ชื่อผู้ใช้นี้ถูกใช้งานในระบบแล้ว กรุณาใช้ชื่ออื่น";
            } else {
                
                // If a new password is typed, hash it, otherwise keep the old password hash
                if (!empty($password_new)) {
                    $password_hash = password_hash($password_new, PASSWORD_BCRYPT);
                    $sql = "UPDATE `employee` SET 
                        name = :name,
                        addr = :addr,
                        phone = :phone,
                        username = :username,
                        password = :password,
                        id_emp_type = :position,
                        id_branch = :branch
                        WHERE id_emp = :id_emp";
                        
                    $params = [
                        ':name' => $name,
                        ':addr' => $addr,
                        ':phone' => $phone,
                        ':username' => $username,
                        ':password' => $password_hash,
                        ':position' => $position,
                        ':branch' => $branch,
                        ':id_emp' => $id_emp
                    ];
                } else {
                    $sql = "UPDATE `employee` SET 
                        name = :name,
                        addr = :addr,
                        phone = :phone,
                        username = :username,
                        id_emp_type = :position,
                        id_branch = :branch
                        WHERE id_emp = :id_emp";
                        
                    $params = [
                        ':name' => $name,
                        ':addr' => $addr,
                        ':phone' => $phone,
                        ':username' => $username,
                        ':position' => $position,
                        ':branch' => $branch,
                        ':id_emp' => $id_emp
                    ];
                }
                
                $stmt_update = $pdo->prepare($sql);
                $stmt_update->execute($params);
                
                // Update session name if editing own account
                if ($id_emp == $_SESSION['id_emp']) {
                    $_SESSION['name'] = $name;
                }
                
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
    <title>แก้ไขข้อมูลพนักงาน - Bovorn Service V2</title>
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
                        <h3 class="card-title">✏️ แก้ไขข้อมูลบัญชีพนักงาน: <?php echo htmlspecialchars($emp['name']); ?></h3>
                    </div>
                    
                    <form action="edit_emp.php?id_emp=<?php echo $emp['id_emp']; ?>" method="POST" id="editEmpForm" onsubmit="return validateForm()">
                        <?php echo csrf_field(); ?>
                        <div class="form-group">
                            <label for="name" class="form-label">ชื่อ - นามสกุล <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name" id="name" class="form-control" required value="<?php echo htmlspecialchars($emp['name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="addr" class="form-label">ที่อยู่ปัจจุบัน <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="addr" id="addr" class="form-control" required value="<?php echo htmlspecialchars($emp['addr']); ?>">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์ <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="phone" id="phone" class="form-control" required value="<?php echo htmlspecialchars($emp['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label for="position" class="form-label">ตำแหน่ง / สิทธิ์ผู้ใช้ <span style="color:var(--danger)">*</span></label>
                                <select name="position" id="position" class="form-control" required>
                                    <option value="0">-- เลือกตำแหน่ง --</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo $r['id_emp_type']; ?>" <?php echo $r['id_emp_type'] == $emp['id_emp_type'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($r['emp_type_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="branch" class="form-label">สาขาประจำการ <span style="color:var(--danger)">*</span></label>
                                <select name="branch" id="branch" class="form-control" required>
                                    <option value="0">-- เลือกสาขา --</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo $b['id_branch']; ?>" <?php echo $b['id_branch'] == $emp['id_branch'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b['branch_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row" style="background-color: var(--bg-main); padding: 1.25rem; border-radius: var(--radius-md); border: 1px solid var(--border); margin-bottom: 1.5rem; margin-top: 1rem;">
                            <div class="form-group">
                                <label for="username" class="form-label">ชื่อผู้ใช้ (Username) <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="username" id="username" class="form-control" required value="<?php echo htmlspecialchars($emp['username']); ?>">
                                <span id="usernameStatus" style="font-size: 0.775rem; font-weight: 600; display: block; margin-top: 4px;"></span>
                            </div>
                            <div class="form-group">
                                <label for="password_new" class="form-label">รหัสผ่านใหม่ (Password)</label>
                                <input type="password" name="password_new" id="password_new" class="form-control" placeholder="ปล่อยว่างไว้หากไม่ต้องการแก้ไขรหัสผ่าน">
                            </div>
                        </div>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
                            <a href="user_manager.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" id="submitBtn" class="btn btn-primary" style="padding-left: 2rem; padding-right: 2rem;">บันทึกการแก้ไข</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        let debounceTimer;
        const usernameInput = document.getElementById('username');
        const statusSpan = document.getElementById('usernameStatus');
        const submitBtn = document.getElementById('submitBtn');
        const excludeId = <?php echo intval($emp['id_emp']); ?>;
        
        usernameInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            const username = this.value.trim();
            
            if (username.length === 0) {
                statusSpan.textContent = '';
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                submitBtn.style.cursor = 'pointer';
                return;
            }
            
            statusSpan.style.color = 'var(--text-muted)';
            statusSpan.textContent = '⏳ กำลังตรวจสอบชื่อผู้ใช้...';
            
            debounceTimer = setTimeout(() => {
                fetch(`check_username.php?username=${encodeURIComponent(username)}&exclude_id=${excludeId}`)
                    .then(r => r.json())
                    .then(data => {
                        if (data.exists) {
                            statusSpan.style.color = 'var(--danger)';
                            statusSpan.textContent = '❌ ชื่อผู้ใช้นี้ถูกใช้งานแล้วในระบบ';
                            submitBtn.disabled = true;
                            submitBtn.style.opacity = '0.5';
                            submitBtn.style.cursor = 'not-allowed';
                        } else {
                            statusSpan.style.color = 'var(--success)';
                            statusSpan.textContent = '✅ ชื่อผู้ใช้นี้สามารถใช้งานได้';
                            submitBtn.disabled = false;
                            submitBtn.style.opacity = '1';
                            submitBtn.style.cursor = 'pointer';
                        }
                    })
                    .catch(() => {
                        statusSpan.textContent = '';
                        submitBtn.disabled = false;
                    });
            }, 250);
        });

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
            return true;
        }
    </script>
</body>
</html>
