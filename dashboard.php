<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }
        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #0f2027 0%, #203a43 100%);
            color: white;
            position: fixed;
            height: 100vh;
            left: 0;
            top: 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 100;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sidebar-header p {
            font-size: 11px;
            opacity: 0.6;
        }
        .user-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 10px;
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
            margin: 0;
        }
        .sidebar-menu li {
            margin: 5px 0;
        }
        .sidebar-menu li a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        .sidebar-menu li a i {
            width: 28px;
            margin-right: 12px;
            font-size: 18px;
        }
        .sidebar-menu li a:hover {
            background: rgba(255,255,255,0.1);
            padding-left: 25px;
            color: white;
        }
        .sidebar-menu li.active a {
            background: linear-gradient(90deg, #2e7d32, #1b5e20);
            color: white;
            border-radius: 0 20px 20px 0;
        }
        .sidebar-menu .menu-divider {
            height: 1px;
            background: rgba(255,255,255,0.1);
            margin: 15px 20px;
        }
        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 20px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .welcome-section h4 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #1a2c3e;
        }
        .welcome-section p {
            font-size: 12px;
            color: #6c757d;
            margin: 0;
        }
        .logout-btn {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 10px 22px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231,76,60,0.3);
            color: white;
        }
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(46,125,50,0.1);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }
        .stat-icon i {
            font-size: 24px;
            color: #2e7d32;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: #1a2c3e;
            margin-bottom: 5px;
        }
        .stat-label {
            color: #6c757d;
            font-size: 13px;
            font-weight: 500;
        }
        /* Quick Actions */
        .section-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #e9ecef;
        }
        .section-title i {
            color: #2e7d32;
            margin-right: 10px;
        }
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }
        .quick-btn {
            background: #f8f9fa;
            padding: 12px 20px;
            border-radius: 12px;
            text-decoration: none;
            color: #1a2c3e;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-btn i {
            font-size: 16px;
        }
        .quick-btn:hover {
            background: #2e7d32;
            color: white;
            transform: translateY(-2px);
        }
        /* Recent Activity */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .activity-icon i {
            font-size: 18px;
            color: #2e7d32;
        }
        .activity-content {
            flex: 1;
        }
        .activity-text {
            font-weight: 500;
            margin-bottom: 3px;
        }
        .activity-time {
            font-size: 11px;
            color: #6c757d;
        }
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 5px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #2e7d32;
            border-radius: 5px;
        }
        @media (max-width: 768px) {
            .sidebar {
                left: -280px;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>🏫 GAIMS</h3>
            <p>Greenhill Academy</p>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="user-name"><?php echo $_SESSION['fullname']; ?></div>
            <div class="user-role">System Administrator</div>
        </div>
        <ul class="sidebar-menu">
            <li class="active"><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li>
            <li><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
            <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
            <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li>
            <li><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
            <li><a href="report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a></li>
            <li class="menu-divider"></li>
            <li><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fee Structure</a></li>
            <li><a href="invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a></li>
            <li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
            <li class="menu-divider"></li>
            <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
            <li><a href="medical.php"><i class="fas fa-heartbeat"></i> Medical</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li class="menu-divider"></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="welcome-section">
                <h4>Welcome back, <?php echo $_SESSION['fullname']; ?>!</h4>
                <p><i class="far fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number">1,284</div>
                <div class="stat-label">Total Students</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-chalkboard-user"></i></div>
                <div class="stat-number">86</div>
                <div class="stat-label">Teaching Staff</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                <div class="stat-number">892</div>
                <div class="stat-label">Parents</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-book-open"></i></div>
                <div class="stat-number">3,245</div>
                <div class="stat-label">Library Books</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                <div class="stat-number">UGX 12.5M</div>
                <div class="stat-label">Fees Collected</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar-check"></i></div>
                <div class="stat-number">94%</div>
                <div class="stat-label">Attendance Rate</div>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-bolt"></i> Quick Actions
            </div>
            <div class="quick-actions">
                <a href="students.php?action=add" class="quick-btn"><i class="fas fa-user-plus"></i> Add Student</a>
                <a href="staff.php?action=add" class="quick-btn"><i class="fas fa-user-tie"></i> Add Staff</a>
                <a href="invoices.php?action=generate" class="quick-btn"><i class="fas fa-file-invoice"></i> Generate Invoices</a>
                <a href="books.php?action=borrow" class="quick-btn"><i class="fas fa-hand-holding-heart"></i> Borrow Book</a>
                <a href="attendance.php" class="quick-btn"><i class="fas fa-calendar-check"></i> Mark Attendance</a>
                <a href="reports.php" class="quick-btn"><i class="fas fa-chart-line"></i> View Reports</a>
                <a href="grades.php" class="quick-btn"><i class="fas fa-edit"></i> Enter Grades</a>
                <a href="fee_structure.php" class="quick-btn"><i class="fas fa-money-bill-wave"></i> Fee Structure</a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-history"></i> Recent Activities
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-user-plus"></i></div>
                        <div class="activity-content">
                            <div class="activity-text">New student enrolled - John Mukasa</div>
                            <div class="activity-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-credit-card"></i></div>
                        <div class="activity-content">
                            <div class="activity-text">Fee payment received UGX 850,000</div>
                            <div class="activity-time">5 hours ago</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-book"></i></div>
                        <div class="activity-content">
                            <div class="activity-text">Book borrowed - "Things Fall Apart"</div>
                            <div class="activity-time">Yesterday</div>
                        </div>
                    </div>
                    <div class="activity-item">
                        <div class="activity-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="activity-content">
                            <div class="activity-text">Term 1 examinations completed</div>
                            <div class="activity-time">Yesterday</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="section-card">
                    <div class="section-title">
                        <i class="fas fa-chart-pie"></i> System Overview
                    </div>
                    <div style="height: 200px; display: flex; align-items: center; justify-content: center;">
                        <canvas id="attendanceChart" style="max-height: 180px;"></canvas>
                    </div>
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <div class="text-success fw-bold">94%</div>
                            <small class="text-muted">Attendance</small>
                        </div>
                        <div class="col-4">
                            <div class="text-primary fw-bold">85%</div>
                            <small class="text-muted">Fee Collection</small>
                        </div>
                        <div class="col-4">
                            <div class="text-warning fw-bold">92%</div>
                            <small class="text-muted">Pass Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-calendar-alt"></i> Upcoming Events
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="alert alert-success mb-0">
                        <i class="fas fa-calendar-day"></i> <strong>Mar 30, 2024</strong><br>
                        Fee Payment Deadline - Term 1
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-calendar-day"></i> <strong>Apr 10, 2024</strong><br>
                        Parent-Teacher Conference
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-calendar-day"></i> <strong>Apr 25, 2024</strong><br>
                        End of Term Examinations
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [94, 4, 2],
                    backgroundColor: ['#2e7d32', '#dc3545', '#ffc107'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { size: 10 } }
                    }
                }
            }
        });
    </script>
</body>
</html>