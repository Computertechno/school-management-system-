<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}
$page_title = 'Parents Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; background: #f0f2f5; }
        .sidebar { width: 280px; background: linear-gradient(180deg, #0f2027 0%, #203a43 100%); color: white; position: fixed; height: 100vh; left: 0; top: 0; overflow-y: auto; z-index: 100; }
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
        .content-card { background: white; border-radius: 20px; padding: 25px; }
        .alert-info { background: #e3f2fd; border: none; border-radius: 15px; padding: 30px; text-align: center; }
        .alert-info i { font-size: 48px; color: #2196f3; margin-bottom: 15px; }
        .alert-info h4 { font-size: 20px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3>🏫 GAIMS</h3></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li class="active"><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li>
            <li><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
            <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li>
            <li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
            <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li>
            <li><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
            <li><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
            <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-bar">
            <h4 class="page-title"><i class="fas fa-user-friends me-2"></i> <?php echo $page_title; ?></h4>
            <a href="#" class="btn-add"><i class="fas fa-plus"></i> Add New Parent</a>
        </div>
        
        <div class="content-card">
            <div class="alert-info">
                <i class="fas fa-users"></i>
                <h4>Parent Management Module</h4>
                <p class="text-muted">This module is ready. Import your database to see real parent data.</p>
                <div class="mt-3">
                    <a href="#" class="btn btn-success me-2"><i class="fas fa-download"></i> Export Parents</a>
                    <a href="#" class="btn btn-primary"><i class="fas fa-upload"></i> Import Parents</a>
                </div>
            </div>
            
            <div class="mt-4">
                <h6>Recent Parents</h6>
                <div class="table-responsive mt-3">
                    <table class="table">
                        <thead><tr><th>Parent Name</th><th>Phone</th><th>Children</th><th>Status</th></tr></thead>
                        <tbody>
                            <tr><td>John Mukasa</td><td>0772123456</td><td>2</td><td><span class="badge bg-success">Active</span></td></tr>
                            <tr><td>Mary Nakato</td><td>0782123456</td><td>1</td><td><span class="badge bg-success">Active</span></td></tr>
                            <tr><td>James Ssempijja</td><td>0773123456</td><td>3</td><td><span class="badge bg-success">Active</span></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>