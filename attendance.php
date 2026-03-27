<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$page_title = 'Attendance Reports';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$report_type = isset($_GET['type']) ? $_GET['type'] : 'class';

$year = date('Y', strtotime($month));
$month_num = date('m', strtotime($month));

// Get classes for filter
$classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
$classes_result = $conn->query($classes_sql);

// Get students for filter
$students = [];
if ($class_id > 0) {
    $students_sql = "SELECT student_id, admission_no, first_name, last_name 
                     FROM students 
                     WHERE current_class_id = ? AND enrollment_status = 'Active'
                     ORDER BY last_name, first_name";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("i", $class_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    $students = $students_result->fetch_all(MYSQLI_ASSOC);
}

// Get attendance data
$attendance_data = [];
$attendance_summary = [];

if ($report_type == 'student' && $student_id > 0) {
    // Monthly attendance for a specific student
    $attendance_sql = "SELECT attendance_date, status, arrival_time, reason 
                       FROM student_attendance 
                       WHERE student_id = ? 
                       AND YEAR(attendance_date) = ? 
                       AND MONTH(attendance_date) = ?
                       ORDER BY attendance_date";
    $attendance_stmt = $conn->prepare($attendance_sql);
    $attendance_stmt->bind_param("iii", $student_id, $year, $month_num);
    $attendance_stmt->execute();
    $attendance_data = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $total_days = count($attendance_data);
    $present = 0;
    $absent = 0;
    $late = 0;
    $excused = 0;
    
    foreach ($attendance_data as $record) {
        if ($record['status'] == 'Present') $present++;
        elseif ($record['status'] == 'Absent') $absent++;
        elseif ($record['status'] == 'Late') $late++;
        elseif ($record['status'] == 'Excused') $excused++;
    }
    
    $attendance_summary = [
        'total' => $total_days,
        'present' => $present,
        'absent' => $absent,
        'late' => $late,
        'excused' => $excused,
        'percentage' => $total_days > 0 ? round(($present / $total_days) * 100, 1) : 0
    ];
    
    $student_name = getStudentName($student_id);
    
} elseif ($report_type == 'class' && $class_id > 0) {
    // Class attendance summary
    $class_name = getClassName($class_id);
    
    $attendance_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name,
                       COUNT(CASE WHEN a.status = 'Present' THEN 1 END) as present_count,
                       COUNT(CASE WHEN a.status = 'Absent' THEN 1 END) as absent_count,
                       COUNT(CASE WHEN a.status = 'Late' THEN 1 END) as late_count,
                       COUNT(CASE WHEN a.status = 'Excused' THEN 1 END) as excused_count,
                       COUNT(a.attendance_id) as total_days
                       FROM students s
                       LEFT JOIN student_attendance a ON s.student_id = a.student_id 
                           AND YEAR(a.attendance_date) = ? AND MONTH(a.attendance_date) = ?
                       WHERE s.current_class_id = ? AND s.enrollment_status = 'Active'
                       GROUP BY s.student_id
                       ORDER BY s.last_name, s.first_name";
    $attendance_stmt = $conn->prepare($attendance_sql);
    $attendance_stmt->bind_param("iii", $year, $month_num, $class_id);
    $attendance_stmt->execute();
    $attendance_data = $attendance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate class summary
    $total_present = 0;
    $total_absent = 0;
    $total_late = 0;
    $total_excused = 0;
    $total_students = count($attendance_data);
    $total_days_sum = 0;
    
    foreach ($attendance_data as $record) {
        $total_present += $record['present_count'];
        $total_absent += $record['absent_count'];
        $total_late += $record['late_count'];
        $total_excused += $record['excused_count'];
        $total_days_sum += $record['total_days'];
    }
    
    $avg_attendance = $total_days_sum > 0 ? round(($total_present / $total_days_sum) * 100, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #eee; padding: 15px 20px; font-weight: 600; }
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .stat-box { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: bold; }
        .present { color: #28a745; }
        .absent { color: #dc3545; }
        .late { color: #ffc107; }
        .btn-export { background: #2e7d32; color: white; padding: 8px 20px; border-radius: 8px; }
        .attendance-table th { background: #f8f9fa; }
        .status-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .status-Present { background: #d4edda; color: #155724; }
        .status-Absent { background: #f8d7da; color: #721c24; }
        .status-Late { background: #fff3cd; color: #856404; }
        .status-Excused { background: #cce5ff; color: #004085; }
        .attendance-progress { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; background: #28a745; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-calendar-check me-2"></i> Attendance Reports</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        
        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="type" value="<?php echo $report_type; ?>">
                <div class="col-md-2">
                    <label class="form-label">Report Type</label>
                    <select name="type" class="form-select" onchange="this.form.submit()">
                        <option value="class" <?php echo $report_type == 'class' ? 'selected' : ''; ?>>Class Report</option>
                        <option value="student" <?php echo $report_type == 'student' ? 'selected' : ''; ?>>Student Report</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Month</label>
                    <input type="month" name="month" class="form-control" value="<?php echo $month; ?>" onchange="this.form.submit()">
                </div>
                <?php if ($report_type == 'student'): ?>
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">Select Class</option>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Student</label>
                        <select name="student_id" class="form-select" onchange="this.form.submit()" <?php echo $class_id == 0 ? 'disabled' : ''; ?>>
                            <option value="0">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo $student['student_id']; ?>" <?php echo ($student_id == $student['student_id']) ? 'selected' : ''; ?>>
                                    <?php echo $student['first_name'] . ' ' . $student['last_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" onchange="this.form.submit()">
                            <option value="0">Select Class</option>
                            <?php 
                            $classes_result2 = $conn->query($classes_sql);
                            while ($class = $classes_result2->fetch_assoc()): 
                            ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-chart-line"></i> Generate</button>
                </div>
            </form>
        </div>
        
        <?php if ($report_type == 'student' && $student_id > 0): ?>
            <!-- Student Attendance Report -->
            <div class="row mb-4">
                <div class="col-md-3"><div class="stat-box"><div class="stat-number present"><?php echo $attendance_summary['percentage']; ?>%</div><div>Attendance Rate</div></div></div>
                <div class="col-md-3"><div class="stat-box"><div class="stat-number present"><?php echo $attendance_summary['present']; ?></div><div>Present Days</div></div></div>
                <div class="col-md-3"><div class="stat-box"><div class="stat-number absent"><?php echo $attendance_summary['absent']; ?></div><div>Absent Days</div></div></div>
                <div class="col-md-3"><div class="stat-box"><div class="stat-number late"><?php echo $attendance_summary['late']; ?></div><div>Late Days</div></div></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i> Daily Attendance - <?php echo htmlspecialchars($student_name); ?>
                    <span class="float-end"><?php echo date('F Y', strtotime($month)); ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>一面<th>Date</th><th>Day</th><th>Status</th><th>Arrival Time</th><th>Reason</th> </thead>
                            <tbody>
                                <?php if (count($attendance_data) > 0): ?>
                                    <?php foreach ($attendance_data as $record): ?>
                                    <tr>
                                        <td><?php echo formatDate($record['attendance_date']); ?></td>
                                        <td><?php echo date('l', strtotime($record['attendance_date'])); ?></td>
                                        <td><span class="status-badge status-<?php echo $record['status']; ?>"><?php echo $record['status']; ?></span></td>
                                        <td><?php echo $record['arrival_time'] ?: '-'; ?></td>
                                        <td><?php echo htmlspecialchars($record['reason'] ?: '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center">No attendance records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header"><i class="fas fa-chart-pie"></i> Attendance Distribution</div>
                <div class="card-body"><canvas id="attendanceChart" height="250"></canvas></div>
            </div>
            
            <script>
                new Chart(document.getElementById('attendanceChart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Present', 'Absent', 'Late', 'Excused'],
                        datasets: [{
                            data: [<?php echo $attendance_summary['present']; ?>, <?php echo $attendance_summary['absent']; ?>, <?php echo $attendance_summary['late']; ?>, <?php echo $attendance_summary['excused']; ?>],
                            backgroundColor: ['#28a745', '#dc3545', '#ffc107', '#17a2b8'],
                            borderWidth: 0
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
                });
            </script>
            
        <?php elseif ($report_type == 'class' && $class_id > 0 && count($attendance_data) > 0): ?>
            <!-- Class Attendance Report -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Class Attendance Summary - <?php echo htmlspecialchars($class_name); ?>
                    <span class="float-end"><?php echo date('F Y', strtotime($month)); ?> | Average: <?php echo $avg_attendance; ?>%</span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead> <th>#</th><th>Admission No</th><th>Student Name</th><th>Present</th><th>Absent</th><th>Late</th><th>Excused</th><th>Total Days</th><th>Attendance %</th> </thead>
                            <tbody>
                                <?php foreach ($attendance_data as $index => $record): 
                                    $total = $record['total_days'];
                                    $percentage = $total > 0 ? round(($record['present_count'] / $total) * 100, 1) : 0;
                                    $color = $percentage >= 80 ? 'success' : ($percentage >= 60 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo $record['admission_no']; ?></td>
                                    <td><?php echo $record['first_name'] . ' ' . $record['last_name']; ?></td>
                                    <td class="text-success"><?php echo $record['present_count']; ?></td>
                                    <td class="text-danger"><?php echo $record['absent_count']; ?></td>
                                    <td class="text-warning"><?php echo $record['late_count']; ?></td>
                                    <td class="text-info"><?php echo $record['excused_count']; ?></td>
                                    <td><?php echo $total; ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="text-<?php echo $color; ?> fw-bold me-2"><?php echo $percentage; ?>%</span>
                                            <div class="attendance-progress flex-grow-1"><div class="progress-fill" style="width: <?php echo $percentage; ?>%"></div></div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif ($report_type == 'class' && $class_id > 0 && count($attendance_data) == 0): ?>
            <div class="alert alert-info">No attendance records found for this class in <?php echo date('F Y', strtotime($month)); ?>.</div>
        <?php else: ?>
            <div class="alert alert-info">Select a class or student to view attendance report.</div>
        <?php endif; ?>
    </div>
</body>
</html>