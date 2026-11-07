<?php
// sidebar.php
// Reusable sidebar navigation component for Bovorn Service V2

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$in_manager_dir = (basename(dirname($_SERVER['PHP_SELF'])) === 'manager');
$prefix = $in_manager_dir ? '../' : '';
$manager_prefix = $in_manager_dir ? '' : 'manager/';

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'พนักงาน';
$user_role_name = (isset($_SESSION['id_emp_type']) && $_SESSION['id_emp_type'] == 1) ? 'ผู้จัดการ' : 'พนักงานขาย';
$user_branch_name = (isset($_SESSION['id_branch']) && $_SESSION['id_branch'] == 1) ? 'สาขาดอกคำใต้' : 'สาขาท่าวังทอง';

// Get initials for avatar
$words = explode(' ', $user_name);
$initials = '';
foreach ($words as $w) {
    $initials .= mb_substr($w, 0, 1, 'UTF-8');
}
$initials = mb_substr($initials, 0, 2, 'UTF-8');
?>

<div class="sidebar">
    <div class="sidebar-header-mobile">
        <div class="sidebar-brand">
            Bovorn V2
        </div>
        <button type="button" class="sidebar-toggle" onclick="toggleMobileMenu()">☰</button>
    </div>
    
    <ul class="sidebar-menu">
        <?php if (isset($_SESSION['id_emp_type']) && $_SESSION['id_emp_type'] == 1): ?>
            <!-- Manager Menu -->
            <li class="sidebar-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>dashboard.php">
                    <span>🏠</span> แดชบอร์ด
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'pay_list.php') ? 'active' : ''; ?>">
                <a href="<?php echo $manager_prefix; ?>pay_list.php">
                    <span>📊</span> รายงานการขาย
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'audit_log.php') ? 'active' : ''; ?>">
                <a href="<?php echo $manager_prefix; ?>audit_log.php">
                    <span>🛡️</span> ตรวจสอบประวัติการขาย
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'user_manager.php' || $current_page === 'add_emp.php' || $current_page === 'edit_emp.php') ? 'active' : ''; ?>">
                <a href="<?php echo $manager_prefix; ?>user_manager.php">
                    <span>👥</span> จัดการพนักงาน
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'ref_car_list.php') ? 'active' : ''; ?>">
                <a href="<?php echo $manager_prefix; ?>ref_car_list.php">
                    <span>🚗</span> เลขที่ ตรอ.
                </a>
            </li>

            <li class="sidebar-item <?php echo ($current_page === 'system_settings.php') ? 'active' : ''; ?>">
                <a href="<?php echo $manager_prefix; ?>system_settings.php">
                    <span>⚙️</span> ตั้งค่าระบบ
                </a>
            </li>
        <?php else: ?>
            <!-- Clerk Menu -->
            <li class="sidebar-item <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>dashboard.php">
                    <span>🏠</span> แดชบอร์ด
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'billing.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>billing.php">
                    <span>🛒</span> ระบบการขาย
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'pay_list.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>pay_list.php">
                    <span>📈</span> รายงานการขาย
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'car_list.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>car_list.php">
                    <span>🚗</span> ประเภทรถที่บริการ
                </a>
            </li>
            <li class="sidebar-item <?php echo ($current_page === 'notification.php') ? 'active' : ''; ?>">
                <a href="<?php echo $prefix; ?>notification.php">
                    <span>🔔</span> แจ้งเตือนลูกค้า
                </a>
            </li>

        <?php endif; ?>
    </ul>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo htmlspecialchars($initials); ?>
            </div>
            <div class="user-details">
                <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <span class="user-role"><?php echo htmlspecialchars($user_role_name); ?></span>
                <span class="user-role" style="font-size: 10px; color: #38bdf8;"><?php echo htmlspecialchars($user_branch_name); ?></span>
            </div>
        </div>
        <a href="<?php echo $prefix; ?>session_logout.php" class="btn btn-danger btn-sm" style="width: 100%; display: flex; align-items: center; justify-content: center; gap: 0.25rem;">
            <span>🚪</span> ออกจากระบบ
        </a>
    </div>
</div>

<script>
function toggleMobileMenu() {
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    sidebar.classList.toggle('active');
    
    if (sidebar.classList.contains('active')) {
        toggleBtn.innerHTML = '✕'; // Change hamburger to close icon
    } else {
        toggleBtn.innerHTML = '☰';
    }
}
</script>
