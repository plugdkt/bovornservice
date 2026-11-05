<?php
// manager/user_manager.php
// Employee accounts management dashboard (Manager view) for Bovorn Service V2

require("con_db.php");

// POST Action - Add New Employee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_employee') {
    $name = trim($_POST['name']);
    $addr = trim($_POST['addr']);
    $phone = trim($_POST['phone']);
    $position = intval($_POST['position']);
    $branch = intval($_POST['branch']);
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    if (empty($name) || empty($addr) || empty($phone) || $position === 0 || $branch === 0 || empty($username) || empty($password)) {
        header("Location: user_manager.php?error=" . urlencode("กรุณากรอกข้อมูลให้ครบถ้วนทุกช่อง"));
        exit;
    } else {
        try {
            // Check unique username globally
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM `employee` WHERE `username` = :username");
            $stmt_check->execute([':username' => $username]);
            if ($stmt_check->fetchColumn() > 0) {
                header("Location: user_manager.php?error=" . urlencode("ชื่อผู้ใช้นี้ถูกใช้งานในระบบแล้ว กรุณาใช้ชื่ออื่น"));
                exit;
            } else {
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
            header("Location: user_manager.php?error=" . urlencode("เกิดข้อผิดพลาดของระบบ: " . $e->getMessage()));
            exit;
        }
    }
}

try {
    $sql = "SELECT e.*, b.branch_name, t.emp_type_name 
            FROM `employee` e
            JOIN `branch` b ON e.id_branch = b.id_branch
            JOIN `emp_type` t ON e.id_emp_type = t.id_emp_type
            ORDER BY e.id_branch ASC, e.id_emp_type ASC";
    $stmt = $pdo->query($sql);
    $employees = $stmt->fetchAll();
    
    // Fetch branches & roles for dropdowns in modal
    $stmt_b = $pdo->query("SELECT * FROM `branch`");
    $branches = $stmt_b->fetchAll();
    
    $stmt_t = $pdo->query("SELECT * FROM `emp_type`");
    $roles = $stmt_t->fetchAll();
} catch (\PDOException $e) {
    $db_error = "เกิดข้อผิดพลาดของระบบ: " . $e->getMessage();
    $employees = [];
    $branches = [];
    $roles = [];
}

$current_manager_id = $_SESSION['id_emp'];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการพนักงาน - Bovorn Service V2</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .text-center { text-align: center; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        /* Modal Container */
        .modal-container {
            background-color: var(--bg-card);
            border-radius: var(--radius-xl);
            border: 1px solid var(--border);
            width: 95%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-lg);
            animation: modalSlideIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
            padding: 1.75rem;
            text-align: left;
        }
        @keyframes modalSlideIn {
            from {
                transform: scale(0.9) translateY(20px);
                opacity: 0;
            }
            to {
                transform: scale(1) translateY(0);
                opacity: 1;
            }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }
        .modal-close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: var(--transition);
        }
        .modal-close-btn:hover {
            color: var(--text-main);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar Navigation Component -->
        <?php include("../sidebar.php"); ?>
        
        <div class="main-wrapper">
            <div class="header">
                <div class="header-title">ระบบการจัดการข้อมูลบัญชีพนักงาน</div>
                <button type="button" onclick="openAddModal()" class="btn btn-primary btn-sm">➕ เพิ่มพนักงานใหม่</button>
            </div>
            
            <div class="content-body">
                <?php if (isset($_GET['save']) && $_GET['save'] === 'success'): ?>
                    <div class="badge badge-success" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        บันทึกข้อมูลพนักงานเรียบร้อยแล้ว
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['del']) && $_GET['del'] === 'success'): ?>
                    <div class="badge badge-success" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        ลบพนักงานออกจากระบบเรียบร้อยแล้ว
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($db_error)): ?>
                    <div class="badge badge-danger" style="display: block; width: 100%; text-align: center; padding: 0.75rem; margin-bottom: 1.5rem; font-size: 0.875rem; border-radius: var(--radius-md);">
                        <?php echo htmlspecialchars($db_error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">👥 บัญชีรายชื่อพนักงานทั้งหมด (รวม <?php echo count($employees); ?> คน)</h3>
                    </div>
                    
                    <div class="table-container">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th style="width: 50px;" class="text-center">#</th>
                                    <th>ชื่อ - นามสกุล</th>
                                    <th>ที่อยู่พนักงาน</th>
                                    <th style="width: 130px;">เบอร์โทรศัพท์</th>
                                    <th style="width: 120px;" class="text-center">ตำแหน่ง</th>
                                    <th style="width: 150px;" class="text-center">สาขาประจำการ</th>
                                    <th style="width: 120px;" class="text-center">ชื่อผู้ใช้</th>
                                    <th style="width: 150px;" class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $idx = 1; foreach ($employees as $emp): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $idx++; ?></td>
                                        <td><strong><?php echo htmlspecialchars($emp['name']); ?></strong></td>
                                        <td><span style="font-size: 0.825rem; color: var(--text-muted);"><?php echo htmlspecialchars($emp['addr']); ?></span></td>
                                        <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                        <td class="text-center">
                                            <span class="badge <?php echo $emp['id_emp_type'] == 1 ? 'badge-warning' : 'badge-success'; ?>">
                                                <?php echo htmlspecialchars($emp['emp_type_name']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center"><strong><?php echo htmlspecialchars($emp['branch_name']); ?></strong></td>
                                        <td class="text-center" style="font-family: monospace; font-size: 0.9rem;"><?php echo htmlspecialchars($emp['username']); ?></td>
                                        <td class="text-center">
                                            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                                <a href="edit_emp.php?id_emp=<?php echo $emp['id_emp']; ?>" class="btn btn-secondary btn-sm" style="padding: 4px 10px;">แก้ไข</a>
                                                
                                                <?php if ($emp['id_emp'] != 2 && $emp['id_emp'] != $current_manager_id): ?>
                                                    <a href="del_emp.php?id_emp=<?php echo $emp['id_emp']; ?>" class="btn btn-danger btn-sm" style="padding: 4px 10px;" onclick="return confirm('ยืนยันที่จะลบบัญชีพนักงานของ <?php echo htmlspecialchars($emp['name']); ?> ออกจากระบบอย่างถาวรหรือไม่?');">ลบ</a>
                                                <?php else: ?>
                                                    <button class="btn btn-secondary btn-sm" style="padding: 4px 10px; opacity: 0.5; cursor: not-allowed;" disabled title="ไม่สามารถลบบัญชีผู้จัดการหลักหรือบัญชีของคุณเองได้">ลบ</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Employee Modal Overlay -->
    <div class="modal-overlay" id="addEmpModal">
        <div class="modal-container" style="max-width: 580px;">
            <div class="modal-header">
                <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin: 0;">➕ เพิ่มบัญชีผู้ใช้พนักงานใหม่</h3>
                <button type="button" class="modal-close-btn" onclick="closeAddModal()">&times;</button>
            </div>
            <form action="user_manager.php" method="POST" id="addEmpForm" onsubmit="return validateForm()">
                <input type="hidden" name="action" value="add_employee">
                
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
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="username" class="form-label">ชื่อผู้ใช้ (Username) <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="username" id="username" class="form-control" required placeholder="สำหรับล็อกอินเข้าระบบ">
                        <span id="usernameStatus" style="font-size: 0.775rem; font-weight: 600; display: block; margin-top: 4px;"></span>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="password" class="form-label">รหัสผ่าน (Password) <span style="color:var(--danger)">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" required placeholder="รหัสผ่านเข้าสู่ระบบ">
                    </div>
                </div>
                
                <div style="display: flex; gap: 0.75rem; justify-content: flex-end; border-top: 1px solid var(--border); padding-top: 1rem; margin-top: 1rem;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddModal()">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn" style="padding-left: 2rem; padding-right: 2rem;">บันทึกพนักงานใหม่</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            // Reset form first
            document.getElementById('addEmpForm').reset();
            document.getElementById('usernameStatus').textContent = '';
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
            
            const modal = document.getElementById('addEmpModal');
            modal.style.display = 'flex';
            setTimeout(() => {
                modal.classList.add('active');
            }, 10);
        }
        
        function closeAddModal() {
            const modal = document.getElementById('addEmpModal');
            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }
        
        // Close modal clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('addEmpModal');
            if (event.target === modal) {
                closeAddModal();
            }
        });
        
        // Real-time username check logic
        let debounceTimer;
        const usernameInput = document.getElementById('username');
        const statusSpan = document.getElementById('usernameStatus');
        const submitBtn = document.getElementById('submitBtn');
        
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
                fetch('check_username.php?username=' + encodeURIComponent(username))
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
            if (document.getElementById('password').value.trim() === "") {
                alert("กรุณาตั้งค่ารหัสผ่าน (Password)");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
