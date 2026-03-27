<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: index.php'); exit(); }
$page_title = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        .report-card { background: white; border-radius: 20px; padding: 20px; margin-bottom: 20px; }
        .report-header { border-bottom: 2px solid #2e7d32; padding-bottom: 10px; margin-bottom: 20px; }
        .btn-export { background: #2e7d32; color: white; padding: 10px 25px; border-radius: 12px; text-decoration: none; }
    </style>
</head>
<body>
    <div class="sidebar"><div class="sidebar-header"><h3>🏫 GAIMS</h3></div>
        <ul class="sidebar-menu"><li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li><li><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
        <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li><li><a href="staff.php"><i class="fas fa-chalkboard-user"></i> Staff</a></li>
        <li><a href="classes.php"><i class="fas fa-chalkboard"></i> Classes</a></li><li><a href="subjects.php"><i class="fas fa-book"></i> Subjects</a></li>
        <li><a href="exams.php"><i class="fas fa-file-alt"></i> Exams</a></li><li><a href="grades.php"><i class="fas fa-edit"></i> Grades</a></li>
        <li><a href="report_cards.php"><i class="fas fa-file-pdf"></i> Report Cards</a></li><li><a href="fee_structure.php"><i class="fas fa-money-bill-wave"></i> Fees</a></li>
        <li><a href="invoices.php"><i class="fas fa-file-invoice"></i> Invoices</a></li><li><a href="payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
        <li><a href="books.php"><i class="fas fa-book-open"></i> Library</a></li><li><a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a></li>
        <li><a href="medical.php"><i class="fas fa-heartbeat"></i> Medical</a></li><li class="active"><a href="reports.php"><i class="fas fa-chart-line"></i> Reports</a></li>
        <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li></ul></div>
    <div class="main-content">
        <div class="top-bar"><h4 class="page-title"><i class="fas fa-chart-line me-2"></i> Reports Dashboard</h4><a href="#" class="btn-export"><i class="fas fa-download"></i> Export All Reports</a></div>
        
        <div class="row"><div class="col-md-6"><div class="report-card"><div class="report-header"><h5><i class="fas fa-chart-pie"></i> Student Enrollment by Class</h5></div><canvas id="classChart" height="250"></canvas></div></div>
        <div class="col-md-6"><div class="report-card"><div class="report-header"><h5><i class="fas fa-chart-line"></i> Fee Collection Trend</h5></div><canvas id="feeChart" height="250"></canvas></div></div></div>
        
        <div class="row"><div class="col-md-6"><div class="report-card"><div class="report-header"><h5><i class="fas fa-chart-bar"></i> Attendance by Class</h5><canvas id="attendanceChart" height="250"></canvas></div></div></div>
        <div class="col-md-6"><div class="report-card"><div class="report-header"><h5><i class="fas fa-chart-line"></i> Academic Performance</h5><canvas id="performanceChart" height="250"></canvas></div></div></div></div>
        
        <div class="report-card"><div class="report-header"><h5><i class="fas fa-table"></i> Fee Collection Summary</h5></div>
        <table class="table"><thead><tr><th>Class</th><th>Total Students</th><th>Total Fees Due</th><th>Amount Collected</th><th>Balance</th><th>Collection %</th></tr></thead>
        <tbody><tr><td>Nursery</td><td>150</td><td>UGX 135M</td><td>UGX 121.5M</td><td>UGX 13.5M</td><td>90%</td></tr>
        <tr><td>Primary</td><td>850</td><td>UGX 1.08B</td><td>UGX 918M</td><td>UGX 162M</td><td>85%</td></tr>
        <tr><td>Secondary</td><td>284</td><td>UGX 468.6M</td><td>UGX 375M</td><td>UGX 93.6M</td><td>80%</td></tr></tbody></table></div>
    </div>
    <script>new Chart(document.getElementById('classChart'),{type:'pie',data:{labels:['Nursery','Primary','Secondary'],datasets:[{data:[150,850,284],backgroundColor:['#2e7d32','#ffc107','#17a2b8']}]}});
    new Chart(document.getElementById('feeChart'),{type:'line',data:{labels:['Jan','Feb','Mar','Apr','May','Jun'],datasets:[{label:'Collection (UGX M)',data:[8.5,10.2,12.5,14.1,13.8,15.2],borderColor:'#2e7d32',fill:false}]}});
    new Chart(document.getElementById('attendanceChart'),{type:'bar',data:{labels:['Nursery','Primary 1','Primary 5','Senior 1','Senior 4'],datasets:[{label:'Attendance %',data:[92,88,94,89,91],backgroundColor:'#2e7d32'}]}});
    new Chart(document.getElementById('performanceChart'),{type:'line',data:{labels:['Term 1','Term 2','Term 3'],datasets:[{label:'Average Score %',data:[78,82,85],borderColor:'#ffc107',fill:false}]}});</script>
</body>
</html>