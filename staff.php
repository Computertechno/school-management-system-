<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
$page_title = 'Staff Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f2027 0%, #203a43 100%); color: white; position: fixed; height: 100vh; left: 0; top: 0; overflow-y: auto; }
        .sidebar-header { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .sidebar-header h3 { font-size: 24px; font-weight: 700; background: linear-gradient(135deg, #ffd89b, #c7e9fb); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; }
        .sidebar-menu li a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; font-size: 14px; }
        .sidebar-menu li a i { width: 28px; margin-right: 12px; }
        .sidebar-menu li a:hover, .sidebar-menu li.active a { background: rgba(255,255,255,0.1); color: white; }
        .main-content { margin-left: 280px; padding: 20px; }
        .top-bar { background: white; border-radius: 20px; padding: 15px 25px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 20px; font-weight: 600; margin: 0; }
        .btn-add { background: linear-gradient(135deg, #2e7d32, #1b5e20); color: white; padding: 10px 22px; border-radius: 12px; text-decoration: none; }
        .stats-row { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 25px; }
        .stat-box { background: white; border-radius: 20px; padding: 20px; text-align: center; }
        .stat-box h3 { font-size: 28px; font-weight: 700; color: #2e7d32; margin: 0; }
        .staff-table { background: white; border-radius: 20px; padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn-sm { padding: 5px 10px; border-radius: 8px; margin: 0 2px; text-decoration: none; display: inline-block; }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: #1a2c3e; }
        .btn-delete { background: #dc3545; color: white; }
        .status-active { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .department-badge { background: #e3f2fd; color: #1976d2; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3>🏫 GAIMS</h3></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li>
            <li class="active"><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
            <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
            <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li>
            <li><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
            <li><a href="report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a></li>
            <li><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
            <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
            <li><a href="medical.php"><i class="fas fa-heartbeat"></i> Medical</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h4 class="page-title"><i class="fas fa-chalkboard-user me-2"></i> Staff Management</h4>
            <a href="#" class="btn-add"><i class="fas fa-plus"></i> Add New Staff</a>
        </div>
        
        <div class="stats-row">
            <div class="stat-box"><h3>86</h3><p>Total Staff</p></div>
            <div class="stat-box"><h3>54</h3><p>Teaching Staff</p></div>
            <div class="stat-box"><h3>32</h3><p>Support Staff</p></div>
            <div class="stat-box"><h3>12</h3><p>Departments</p></div>
        </div>
        
        <div class="staff-table">
            <div style="overflow-x: auto;">
                <table class="table">
                    <thead><tr><th>Staff ID</th><th>Name</th><th>Department</th><th>Position</th><th>Phone</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <tr><td>STF/2024/001</td><td>Dr. Sarah Nakato</td><td><span class="department-badge">Administration</span></td><td>Principal</td><td>0772123456</td><td><span class="status-active">Active</span></td><td><a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a> <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a></td></tr>
                        <tr><td>STF/2024/002</td><td>Mr. Michael Okello</td><td><span class="department-badge">Teaching</span></td><td>Senior Teacher</td><td>0773123456</td><td><span class="status-active">Active</span></td><td><a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a> <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a></td></tr>
                        <tr><td>STF/2024/003</td><td>Mrs. Grace Nambi</td><td><span class="department-badge">Finance</span></td><td>Accountant</td><td>0782123456</td><td><span class="status-active">Active</span></td><td><a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a> <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a></td></tr>
                        <tr><td>STF/2024/004</td><td>Mr. John Mukasa</td><td><span class="department-badge">Teaching</span></td><td>Class Teacher</td><td>0774123456</td><td><span class="status-active">Active</span></td><td><a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a> <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a></td></tr>
                        <tr><td>STF/2024/005</td><td>Ms. Mary Nakato</td><td><span class="department-badge">Library</span></td><td>Librarian</td><td>0783123456</td><td><span class="status-active">Active</span></td><td><a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a> <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>