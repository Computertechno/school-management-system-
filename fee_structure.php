<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
$page_title = 'Fee Structure';
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
        .btn-edit { background: #ffc107; color: #1a2c3e; padding: 10px 22px; border-radius: 12px; text-decoration: none; }
        .fee-table { background: white; border-radius: 20px; padding: 20px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .fee-total { background: #e8f5e9; font-weight: bold; }
        .campus-badge { background: #e3f2fd; padding: 2px 10px; border-radius: 20px; font-size: 11px; display: inline-block; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3>🏫 GAIMS</h3></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li><li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li><li><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
            <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li><li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
            <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li><li><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
            <li><a href="report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a></li>
            <li class="active"><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
            <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li>
            <li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
            <li><a href="medical.php"><i class="fas fa-heartbeat"></i> Medical</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="top-bar"><h4 class="page-title"><i class="fas fa-money-bill-wave me-2"></i> Fee Structure</h4><a href="#" class="btn-edit"><i class="fas fa-edit"></i> Edit Structure</a></div>
        
        <div class="fee-table">
            <h5><i class="fas fa-school"></i> Kibuli Campus - 2024/2025 Academic Year</h5>
            <table class="table">
                <thead><tr><th>Class</th><th>Tuition</th><th>Development</th><th>Library</th><th>Sports</th><th>Medical</th><th>Uniform</th><th>Total (UGX)</th></tr></thead>
                <tbody>
                    <tr><td>Nursery 1-3</td><td>500,000</td><td>100,000</td><td>50,000</td><td>50,000</td><td>50,000</td><td>150,000</td><td class="fee-total">900,000</td></tr>
                    <tr><td>Primary 1-3</td><td>600,000</td><td>120,000</td><td>60,000</td><td>60,000</td><td>60,000</td><td>180,000</td><td class="fee-total">1,080,000</td></tr>
                    <tr><td>Primary 4-7</td><td>700,000</td><td>150,000</td><td>75,000</td><td>75,000</td><td>75,000</td><td>200,000</td><td class="fee-total">1,275,000</td></tr>
                    <tr><td>Senior 1-4</td><td>900,000</td><td>200,000</td><td>100,000</td><td>100,000</td><td>100,000</td><td>250,000</td><td class="fee-total">1,650,000</td></tr>
                    <tr><td>Senior 5-6</td><td>1,200,000</td><td>250,000</td><td>120,000</td><td>120,000</td><td>120,000</td><td>300,000</td><td class="fee-total">2,110,000</td></tr>
                </tbody>
            </table>
            
            <h5 class="mt-4"><i class="fas fa-school"></i> Buwaate Campus - 2024/2025 Academic Year</h5>
            <table class="table">
                <thead><tr><th>Class</th><th>Tuition</th><th>Development</th><th>Library</th><th>Sports</th><th>Medical</th><th>Uniform</th><th>Total (UGX)</th></tr></thead>
                <tbody>
                    <tr><td>Nursery 1-3</td><td>450,000</td><td>90,000</td><td>45,000</td><td>45,000</td><td>45,000</td><td>150,000</td><td class="fee-total">825,000</td></tr>
                    <tr><td>Primary 1-3</td><td>550,000</td><td>110,000</td><td>55,000</td><td>55,000</td><td>55,000</td><td>180,000</td><td class="fee-total">1,005,000</td></tr>
                    <tr><td>Primary 4-7</td><td>650,000</td><td>140,000</td><td>70,000</td><td>70,000</td><td>70,000</td><td>200,000</td><td class="fee-total">1,200,000</td></tr>
                </tbody>
            </table>
            <div class="alert alert-info mt-3"><i class="fas fa-info-circle"></i> Boarding Fee (Kibuli Campus): Additional UGX 500,000 per term</div>
        </div>
    </div>
</body>
</html>