<?php
/**
 * GAIMS - Dashboard Sidebar
 * Include this in all dashboard pages
 */

$user = getCurrentUser();
$role = $user['role_name'] ?? $_SESSION['role'] ?? 'admin';
$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));
?>
<style>
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #0f2027 0%, #203a43 100%);
        color: white;
        position: fixed;
        height: 100vh;
        left: 0;
        top: 0;
        overflow-y: auto;
        z-index: 100;
        transition: all 0.3s;
    }
    .sidebar-header {
        padding: 25px 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .sidebar-header h3 {
        font-size: 24px;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, #ffd89b, #c7e9fb);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .sidebar-header p {
        font-size: 11px;
        opacity: 0.6;
        margin: 5px 0 0;
    }
    .user-info {
        padding: 20px;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }
    .user-avatar {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #ffd89b, #c7e9fb);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
    }
    .user-avatar i {
        font-size: 28px;
        color: #1a2c3e;
    }
    .user-name {
        font-weight: 600;
        font-size: 14px;
    }
    .user-role {
        font-size: 11px;
        opacity: 0.7;
    }
    .sidebar-menu {
        list-style: none;
        padding: 0;
        margin: 10px 0;
    }
    .sidebar-menu li {
        margin: 0;
    }
    .sidebar-menu li a {
        display: flex;
        align-items: center;
        padding: 12px 20px;
        color: rgba(255,255,255,0.8);
        text-decoration: none;
        transition: all 0.3s;
        font-size: 14px;
    }
    .sidebar-menu li a i {
        width: 28px;
        margin-right: 12px;
        font-size: 16px;
    }
    .sidebar-menu li a:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        padding-left: 25px;
    }
    .sidebar-menu li.active a {
        background: linear-gradient(90deg, #2e7d32, #1b5e20);
        color: white;
    }
    .sidebar-menu .menu-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 15px 20px;
    }
    .sidebar-menu .menu-header {
        padding: 15px 20px 5px;
        font-size: 11px;
        text-transform: uppercase;
        color: #95a5a6;
        font-weight: 600;
    }
    @media (max-width: 768px) {
        .sidebar {
            left: -280px;
        }
        .sidebar.active {
            left: 0;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .mobile-toggle {
            display: flex;
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
            background: #2e7d32;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            cursor: pointer;
        }
    }
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <h3>🏫 GAIMS</h3>
        <p>Greenhill Academy</p>
    </div>
    <div class="user-info">
        <div class="user-avatar">
            <i class="fas fa-user-circle"></i>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($_SESSION['fullname'] ?? $_SESSION['username'] ?? 'Admin'); ?></div>
        <div class="user-role"><?php echo ucfirst($role); ?></div>
    </div>
    
    <ul class="sidebar-menu">
        <li class="<?php echo ($current_page == 'admin.php' || $current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        </li>
        
        <li class="menu-header">MANAGEMENT</li>
        <li class="<?php echo ($current_module == 'students') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/students/index.php"><i class="fas fa-users"></i> Students</a>
        </li>
        <li class="<?php echo ($current_module == 'parents') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/parents/index.php"><i class="fas fa-user-friends"></i> Parents</a>
        </li>
        <li class="<?php echo ($current_module == 'staff') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/staff/index.php"><i class="fas fa-chalkboard-user"></i> Staff</a>
        </li>
        <li class="<?php echo ($current_module == 'admissions') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/admissions/index.php"><i class="fas fa-door-open"></i> Admissions</a>
        </li>
        
        <li class="menu-header">ACADEMICS</li>
        <li class="<?php echo ($current_module == 'academics' && strpos($current_page, 'classes') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/academics/classes.php"><i class="fas fa-chalkboard"></i> Classes</a>
        </li>
        <li class="<?php echo ($current_module == 'academics' && strpos($current_page, 'subjects') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/academics/subjects.php"><i class="fas fa-book"></i> Subjects</a>
        </li>
        <li class="<?php echo ($current_module == 'academics' && strpos($current_page, 'exams') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/academics/exams.php"><i class="fas fa-file-alt"></i> Exams</a>
        </li>
        <li class="<?php echo ($current_module == 'academics' && strpos($current_page, 'grades') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/academics/grades.php"><i class="fas fa-edit"></i> Grades</a>
        </li>
        <li class="<?php echo ($current_module == 'academics' && strpos($current_page, 'report_cards') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/academics/report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a>
        </li>
        
        <li class="menu-header">FINANCE</li>
        <li class="<?php echo ($current_module == 'fees' && strpos($current_page, 'structure') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/fees/structure.php"><i class="fas fa-money-bill-wave"></i> Fee Structure</a>
        </li>
        <li class="<?php echo ($current_module == 'fees' && strpos($current_page, 'invoices') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/fees/invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a>
        </li>
        <li class="<?php echo ($current_module == 'fees' && strpos($current_page, 'payments') !== false) ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/fees/payments.php"><i class="fas fa-credit-card"></i> Payments</a>
        </li>
        
        <li class="menu-header">OTHER</li>
        <li class="<?php echo ($current_module == 'library') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/library/books.php"><i class="fas fa-book-open"></i> Library</a>
        </li>
        <li class="<?php echo ($current_module == 'attendance') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/attendance/student_attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a>
        </li>
        <li class="<?php echo ($current_module == 'medical') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/medical/records.php"><i class="fas fa-heartbeat"></i> Medical</a>
        </li>
        <li class="<?php echo ($current_module == 'communication') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/communication/notifications.php"><i class="fas fa-bell"></i> Notifications</a>
        </li>
        <li class="<?php echo ($current_module == 'reports') ? 'active' : ''; ?>">
            <a href="<?php echo SITE_URL; ?>modules/reports/index.php"><i class="fas fa-chart-line"></i> Reports</a>
        </li>
        
        <li class="menu-divider"></li>
        <li>
            <a href="<?php echo SITE_URL; ?>modules/auth/change_password.php"><i class="fas fa-key"></i> Change Password</a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>logout.php" class="text-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </li>
    </ul>
</div>

<div class="mobile-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</div>

<script>
    function toggleSidebar() {
        document.querySelector('.sidebar').classList.toggle('active');
    }
</script>