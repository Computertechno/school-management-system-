<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Redirect non-teachers
if ($user['role_name'] != 'teacher') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Get teacher staff ID
$staff_sql = "SELECT staff_id, first_name, last_name FROM staff WHERE user_id = ?";
$staff_stmt = $conn->prepare($staff_sql);
$staff_stmt->bind_param("i", $user['user_id']);
$staff_stmt->execute();
$staff_result = $staff_stmt->get_result();
$teacher = $staff_result->fetch_assoc();
$teacher_id = $teacher['staff_id'];

// Get assigned classes for current term
$classes_sql = "SELECT DISTINCT c.class_id, c.class_name, c.class_level,
                (SELECT COUNT(*) FROM students WHERE current_class_id = c.class_id AND enrollment_status = 'Active') as student_count
                FROM class_subjects cs
                JOIN classes c ON cs.class_id = c.class_id
                WHERE cs.teacher_id = ? AND cs.academic_year = ? AND cs.term = ?
                ORDER BY c.class_level, c.class_name";
$classes_stmt = $conn->prepare($classes_sql);
$classes_stmt->bind_param("isi", $teacher_id, CURRENT_ACADEMIC_YEAR, CURRENT_TERM);
$classes_stmt->execute();
$classes_result = $classes_stmt->get_result();
$assigned_classes = $classes_result->fetch_all(MYSQLI_ASSOC);

// Get pending grades (exams not yet completed for assigned classes)
$pending_sql = "SELECT e.exam_id, e.exam_name, e.exam_type, e.end_date, 
                COUNT(DISTINCT s.student_id) as total_students,
                COUNT(r.result_id) as graded_students
                FROM exams e
                JOIN class_subjects cs ON cs.academic_year = e.academic_year AND cs.term = e.term
                JOIN students s ON s.current_class_id = cs.class_id
                LEFT JOIN results r ON r.exam_id = e.exam_id AND r.subject_id = cs.subject_id AND r.student_id = s.student_id
                WHERE cs.teacher_id = ? AND e.status != 'Completed' AND s.enrollment_status = 'Active'
                GROUP BY e.exam_id, cs.subject_id
                HAVING graded_students < total_students
                ORDER BY e.end_date ASC
                LIMIT 5";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $teacher_id);
$pending_stmt->execute();
$pending_grades = $pending_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get today's attendance summary for assigned classes
$today = date('Y-m-d');
$attendance_summary = [];
foreach ($assigned_classes as $class) {
    $att_sql = "SELECT COUNT(CASE WHEN status = 'Present' THEN 1 END) as present,
                       COUNT(CASE WHEN status = 'Absent' THEN 1 END) as absent,
                       COUNT(CASE WHEN status = 'Late' THEN 1 END) as late,
                       COUNT(*) as total
                FROM student_attendance
                WHERE class_id = ? AND attendance_date = ?";
    $att_stmt = $conn->prepare($att_sql);
    $att_stmt->bind_param("is", $class['class_id'], $today);
    $att_stmt->execute();
    $att_result = $att_stmt->get_result();
    $attendance_summary[$class['class_id']] = $att_result->fetch_assoc();
}

// Get recent messages
$messages_sql = "SELECT * FROM messages 
                 WHERE receiver_id = ? 
                 ORDER BY created_at DESC LIMIT 5";
$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("i", $user['user_id']);
$messages_stmt->execute();
$recent_messages = $messages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming events (simplified - would come from events table in full implementation)
$upcoming_events = [
    ['title' => 'End of Term Examinations', 'date' => date('Y-m-d', strtotime('+2 weeks')), 'type' => 'exam'],
    ['title' => 'Staff Meeting', 'date' => date('Y-m-d', strtotime('next friday')), 'type' => 'meeting'],
    ['title' => 'Parent-Teacher Conference', 'date' => date('Y-m-d', strtotime('+3 weeks')), 'type' => 'conference']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
        }
        .sidebar .brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #3e5a6f;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            width: 25px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .top-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .class-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            transition: transform 0.2s;
        }
        .class-card:hover {
            transform: translateX(5px);
        }
        .pending-item {
            background: #fff3cd;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 3px solid #ffc107;
        }
        .message-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .event-item {
            display: flex;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .event-date {
            width: 60px;
            text-align: center;
            background: #3498db;
            color: white;
            border-radius: 8px;
            padding: 5px;
            margin-right: 15px;
        }
        .quick-action {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }
        .quick-action:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }
        .attendance-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-top: 8px;
        }
        .attendance-fill {
            height: 100%;
            background: #28a745;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h4>GAIMS</h4>
            <small>Greenhill Academy</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="teacher.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="../academics/my_classes.php">
                <i class="fas fa-chalkboard"></i> My Classes
            </a>
            <a class="nav-link" href="../academics/grades.php">
                <i class="fas fa-edit"></i> Enter Grades
            </a>
            <a class="nav-link" href="../attendance/student_attendance.php">
                <i class="fas fa-calendar-check"></i> Mark Attendance
            </a>
            <a class="nav-link" href="../academics/report_cards.php">
                <i class="fas fa-file-pdf"></i> Report Cards
            </a>
            <a class="nav-link" href="../communication/notifications.php">
                <i class="fas fa-bell"></i> Notifications
            </a>
            <a class="nav-link" href="../communication/messages.php">
                <i class="fas fa-envelope"></i> Messages
            </a>
            <hr class="my-2">
            <a class="nav-link" href="../auth/change_password.php">
                <i class="fas fa-key"></i> Change Password
            </a>
            <a class="nav-link text-danger" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Welcome back, <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>!</h4>
                <p class="text-muted mb-0">Term <?php echo CURRENT_TERM; ?> | <?php echo CURRENT_ACADEMIC_YEAR; ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['username']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="../auth/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-chalkboard fa-2x text-primary mb-2"></i>
                    <div class="stat-number"><?php echo count($assigned_classes); ?></div>
                    <div class="text-muted">Assigned Classes</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-users fa-2x text-success mb-2"></i>
                    <div class="stat-number">
                        <?php 
                        $total_students = 0;
                        foreach ($assigned_classes as $class) {
                            $total_students += $class['student_count'];
                        }
                        echo $total_students;
                        ?>
                    </div>
                    <div class="text-muted">Total Students</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-tasks fa-2x text-warning mb-2"></i>
                    <div class="stat-number"><?php echo count($pending_grades); ?></div>
                    <div class="text-muted">Pending Grades</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- My Classes Section -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-chalkboard"></i> My Classes
                    </div>
                    <div class="card-body">
                        <?php if (count($assigned_classes) > 0): ?>
                            <?php foreach ($assigned_classes as $class): 
                                $att = $attendance_summary[$class['class_id']] ?? ['present' => 0, 'absent' => 0, 'late' => 0, 'total' => 0];
                                $attendance_percent = $att['total'] > 0 ? round(($att['present'] / $att['total']) * 100, 0) : 0;
                            ?>
                                <div class="class-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($class['class_name']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $class['student_count']; ?> students
                                                <span class="mx-1">|</span>
                                                <i class="fas fa-level-up-alt"></i> <?php echo $class['class_level']; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-<?php echo $attendance_percent >= 80 ? 'success' : ($attendance_percent >= 50 ? 'warning' : 'danger'); ?>">
                                                <?php echo $attendance_percent; ?>% present
                                            </span>
                                        </div>
                                    </div>
                                    <div class="attendance-bar mt-2">
                                        <div class="attendance-fill" style="width: <?php echo $attendance_percent; ?>%"></div>
                                    </div>
                                    <div class="mt-2 d-flex justify-content-between">
                                        <a href="../attendance/student_attendance.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-calendar-check"></i> Mark Attendance
                                        </a>
                                        <a href="../academics/grades.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-edit"></i> Enter Grades
                                        </a>
                                        <a href="../academics/class_view.php?id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-outline-info">
                                            <i class="fas fa-eye"></i> View Class
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-chalkboard fa-2x text-muted mb-2"></i>
                                <p>No classes assigned for the current term.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Pending Grades Section -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-tasks"></i> Pending Grades
                        <a href="../academics/grades.php" class="btn btn-sm btn-primary float-end">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($pending_grades) > 0): ?>
                            <?php foreach ($pending_grades as $pending): 
                                $remaining = $pending['total_students'] - $pending['graded_students'];
                            ?>
                                <div class="pending-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($pending['exam_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">Due: <?php echo formatDate($pending['end_date']); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-warning"><?php echo $remaining; ?> remaining</span>
                                            <br>
                                            <a href="../academics/grade_entry.php?exam_id=<?php echo $pending['exam_id']; ?>" class="btn btn-sm btn-primary mt-1">
                                                Enter Now
                                            </a>
                                        </div>
                                    </div>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo ($pending['graded_students'] / $pending['total_students']) * 100; ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                <p>All grades are up to date!</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Recent Messages -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-envelope"></i> Recent Messages
                        <a href="../communication/messages.php" class="btn btn-sm btn-primary float-end">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_messages) > 0): ?>
                            <?php foreach ($recent_messages as $msg): ?>
                                <div class="message-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($msg['subject'] ?: 'No Subject'); ?></strong>
                                        <small class="text-muted"><?php echo formatDate($msg['created_at']); ?></small>
                                    </div>
                                    <p class="mb-0 small"><?php echo htmlspecialchars(substr($msg['message'], 0, 100)); ?>...</p>
                                    <div class="mt-1">
                                        <a href="../communication/messages.php?id=<?php echo $msg['message_id']; ?>" class="small">Read more</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                                <p>No new messages.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-calendar-alt"></i> Upcoming Events
                    </div>
                    <div class="card-body">
                        <?php foreach ($upcoming_events as $event): ?>
                            <div class="event-item">
                                <div class="event-date">
                                    <div class="small"><?php echo date('M', strtotime($event['date'])); ?></div>
                                    <div class="fw-bold"><?php echo date('d', strtotime($event['date'])); ?></div>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($event['title']); ?></strong>
                                    <div class="small text-muted">
                                        <i class="fas fa-clock"></i> <?php echo date('l, F j', strtotime($event['date'])); ?>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $event['type'] == 'exam' ? 'danger' : ($event['type'] == 'meeting' ? 'info' : 'primary'); ?>">
                                    <?php echo ucfirst($event['type']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-bolt"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="../attendance/student_attendance.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-calendar-check fa-2x text-primary mb-2"></i>
                                <div>Mark Attendance</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../academics/grades.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-edit fa-2x text-success mb-2"></i>
                                <div>Enter Grades</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../academics/report_cards.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                <div>Generate Reports</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../communication/notifications.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-bell fa-2x text-warning mb-2"></i>
                                <div>Send Notification</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>