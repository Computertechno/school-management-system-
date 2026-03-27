<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
$page_title = 'Grade Management';
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
        .grade-table { background: white; border-radius: 20px; padding: 20px; }
        .class-selector { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .grade-A { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .grade-B { background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .grade-C { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .grade-D { background: #ffe5d0; color: #e67e22; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .grade-E { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; display: inline-block; }
        .btn-save { background: #2e7d32; color: white; padding: 10px 30px; border-radius: 12px; border: none; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h3>🏫 GAIMS</h3></div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li><li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li><li><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
            <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li><li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
            <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li><li class="active"><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
            <li><a href="report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a></li><li><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
            <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li><li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
            <li><a href="medical.php"><i class="fas fa-heartbeat"></i> Medical</a></li><li><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="top-bar"><h4 class="page-title"><i class="fas fa-edit me-2"></i> Grade Management</h4><a href="#" class="btn-add"><i class="fas fa-plus"></i> Enter Grades</a></div>
        <div class="grade-table">
            <div class="class-selector"><label>Select Class: </label><select class="form-select w-25 d-inline-block mx-2"><option>Primary 5 A</option><option>Primary 5 B</option><option>Senior 1 A</option></select><label>Select Exam: </label><select class="form-select w-25 d-inline-block mx-2"><option>Mid Term 1</option><option>End of Term 1</option></select></div>
            <table class="table">
                <thead><tr><th>Admission No</th><th>Student Name</th><th>English</th><th>Mathematics</th><th>Science</th><th>Social Studies</th><th>Average</th><th>Grade</th></tr></thead>
                <tbody>
                    <tr><td>GHA/2024/0001</td><td>John Mukasa</td><td><input type="text" value="85" class="form-control w-75"></td><td><input type="text" value="78" class="form-control w-75"></td><td><input type="text" value="92" class="form-control w-75"></td><td><input type="text" value="88" class="form-control w-75"></td><td>85.8%</td><td><span class="grade-A">A</span></td></tr>
                    <tr><td>GHA/2024/0002</td><td>Sarah Nakato</td><td><input type="text" value="92" class="form-control w-75"></td><td><input type="text" value="88" class="form-control w-75"></td><td><input type="text" value="95" class="form-control w-75"></td><td><input type="text" value="90" class="form-control w-75"></td><td>91.3%</td><td><span class="grade-A">A</span></td></tr>
                    <tr><td>GHA/2024/0003</td><td>James Ssempijja</td><td><input type="text" value="65" class="form-control w-75"></td><td><input type="text" value="72" class="form-control w-75"></td><td><input type="text" value="68" class="form-control w-75"></td><td><input type="text" value="70" class="form-control w-75"></td><td>68.8%</td><td><span class="grade-C">C</span></td></tr>
                </tbody>
            </table>
            <button class="btn-save"><i class="fas fa-save"></i> Save All Grades</button>
        </div>
    </div>
</body>
</html>