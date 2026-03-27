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
    <title>Students - Greenhill Academy</title>
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
        }
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
        }
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar-header h3 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #ffd89b, #c7e9fb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
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
        }
        .sidebar-menu li a:hover, .sidebar-menu li.active a {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
        }
        .top-bar {
            background: white;
            border-radius: 20px;
            padding: 15px 25px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
        }
        .btn-add {
            background: linear-gradient(135deg, #2e7d32, #1b5e20);
            color: white;
            padding: 10px 22px;
            border-radius: 12px;
            text-decoration: none;
        }
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }
        .stat-box {
            background: white;
            border-radius: 20px;
            padding: 20px;
            text-align: center;
        }
        .stat-box h3 {
            font-size: 28px;
            font-weight: 700;
            color: #2e7d32;
            margin: 0;
        }
        .students-table {
            background: white;
            border-radius: 20px;
            padding: 20px;
        }
        .search-box {
            margin-bottom: 20px;
        }
        .search-box input {
            width: 300px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        th {
            background: #f8f9fa;
            font-weight: 600;
        }
        .btn-sm {
            padding: 5px 10px;
            border-radius: 8px;
            margin: 0 2px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view { background: #17a2b8; color: white; }
        .btn-edit { background: #ffc107; color: #1a2c3e; }
        .btn-delete { background: #dc3545; color: white; }
        .status-active {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>🏫 GAIMS</h3>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li class="active"><a href="students.php"><i class="fas fa-users"></i> Students</a></li>
            <li><a href="parents.php"><i class="fas fa-user-friends"></i> Parents</a></li>
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
            <h4 class="page-title"><i class="fas fa-users me-2"></i> Student Management</h4>
            <a href="#" class="btn-add"><i class="fas fa-plus"></i> Add New Student</a>
        </div>
        
        <div class="stats-row">
            <div class="stat-box">
                <h3>1,284</h3>
                <p>Total Students</p>
            </div>
            <div class="stat-box">
                <h3>682</h3>
                <p>Boys</p>
            </div>
            <div class="stat-box">
                <h3>602</h3>
                <p>Girls</p>
            </div>
            <div class="stat-box">
                <h3>94%</h3>
                <p>Active</p>
            </div>
        </div>
        
        <div class="students-table">
            <div class="search-box">
                <input type="text" placeholder="Search by name or admission number...">
            </div>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Admission No</th>
                            <th>Student Name</th>
                            <th>Class</th>
                            <th>Gender</th>
                            <th>Date of Birth</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </thead>
                        <tbody>
                            <tr>
                                <td>GHA/2024/0001</td>
                                <td>John Mukasa</td>
                                <td>Primary 5 A</td>
                                <td>Male</td>
                                <td>2015-03-15</td>
                                <td><span class="status-active">Active</span></td>
                                <td>
                                    <a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a>
                                    <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn-sm btn-delete"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td>GHA/2024/0002</td>
                                <td>Sarah Nakato</td>
                                <td>Primary 5 A</td>
                                <td>Female</td>
                                <td>2015-05-20</td>
                                <td><span class="status-active">Active</span></td>
                                <td>
                                    <a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a>
                                    <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn-sm btn-delete"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                            <tr>
                                <td>GHA/2024/0003</td>
                                <td>James Ssempijja</td>
                                <td>Senior 2 B</td>
                                <td>Male</td>
                                <td>2010-08-10</td>
                                <td><span class="status-active">Active</span></td>
                                <td>
                                    <a href="#" class="btn-sm btn-view"><i class="fas fa-eye"></i></a>
                                    <a href="#" class="btn-sm btn-edit"><i class="fas fa-edit"></i></a>
                                    <a href="#" class="btn-sm btn-delete"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-3">
                    <nav>
                        <ul class="pagination justify-content-center">
                            <li class="page-item disabled"><a class="page-link">Previous</a></li>
                            <li class="page-item active"><a class="page-link">1</a></li>
                            <li class="page-item"><a class="page-link">2</a></li>
                            <li class="page-item"><a class="page-link">3</a></li>
                            <li class="page-item"><a class="page-link">Next</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</body>
</html>