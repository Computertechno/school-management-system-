<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Redirect non-parents
if ($user['role_name'] != 'parent') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Get parent ID from user
$parent_sql = "SELECT parent_id FROM parents WHERE email = ? OR phone = ?";
$parent_stmt = $conn->prepare($parent_sql);
$parent_stmt->bind_param("ss", $user['email'], $user['phone']);
$parent_stmt->execute();
$parent_result = $parent_stmt->get_result();
$parent = $parent_result->fetch_assoc();
$parent_id = $parent['parent_id'];

// Get children
$children_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name, s.current_class_id, 
                 c.class_name, c.class_level,
                 (SELECT AVG(percentage) FROM results r WHERE r.student_id = s.student_id AND r.exam_id IN 
                  (SELECT exam_id FROM exams WHERE term = ? AND academic_year = ?)) as current_term_avg
                 FROM students s
                 JOIN student_parents sp ON s.student_id = sp.student_id
                 JOIN classes c ON s.current_class_id = c.class_id
                 WHERE sp.parent_id = ? AND s.enrollment_status = 'Active'
                 ORDER BY s.last_name";
$children_stmt = $conn->prepare($children_sql);
$children_stmt->bind_param("isi", CURRENT_TERM, CURRENT_ACADEMIC_YEAR, $parent_id);
$children_stmt->execute();
$children = $children_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get fee summary for children
$fee_summary = [];
foreach ($children as $child) {
    $fee_sql = "SELECT SUM(amount_due) as total_due, SUM(amount_paid) as total_paid 
                FROM invoices 
                WHERE student_id = ? AND academic_year = ?";
    $fee_stmt = $conn->prepare($fee_sql);
    $fee_stmt->bind_param("is", $child['student_id'], CURRENT_ACADEMIC_YEAR);
    $fee_stmt->execute();
    $fee_result = $fee_stmt->get_result();
    $fee_summary[$child['student_id']] = $fee_result->fetch_assoc();
}

// Get recent notifications for this parent
$notifications_sql = "SELECT * FROM notifications 
                      WHERE recipient_type = 'parent' 
                      ORDER BY created_at DESC LIMIT 5";
$notifications_result = $conn->query($notifications_sql);
$recent_notifications = $notifications_result->fetch_all(MYSQLI_ASSOC);

// Get upcoming events
$upcoming_events = [
    ['title' => 'End of Term Examinations', 'date' => date('Y-m-d', strtotime('+2 weeks')), 'type' => 'exam'],
    ['title' => 'Parent-Teacher Conference', 'date' => date('Y-m-d', strtotime('+3 weeks')), 'type' => 'conference'],
    ['title' => 'School Sports Day', 'date' => date('Y-m-d', strtotime('+4 weeks')), 'type' => 'sports']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Portal - <?php echo SITE_NAME; ?></title>
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
        .child-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .child-header {
            background: linear-gradient(135deg, #3498db, #2c3e50);
            color: white;
            padding: 15px 20px;
        }
        .child-body {
            padding: 20px;
        }
        .performance-stat {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            text-align: center;
        }
        .grade-badge {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
        }
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #cce5ff; color: #004085; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #ffe5d0; color: #e67e22; }
        .grade-E { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
        .notification-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 3px solid #3498db;
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
        .fee-status {
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h4>GAIMS</h4>
            <small>Parent Portal</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="parent.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="../students/my_children.php">
                <i class="fas fa-child"></i> My Children
            </a>
            <a class="nav-link" href="../academics/grades.php">
                <i class="fas fa-chart-line"></i> Grades
            </a>
            <a class="nav-link" href="../fees/balance.php">
                <i class="fas fa-money-bill-wave"></i> Fee Balance
            </a>
            <a class="nav-link" href="../attendance/view.php">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>
            <a class="nav-link" href="../medical/view.php">
                <i class="fas fa-heartbeat"></i> Medical
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
                <h4 class="mb-0">Welcome to the Parent Portal!</h4>
                <p class="text-muted mb-0">Track your children's academic progress, fees, and school activities</p>
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
        
        <!-- Children Cards -->
        <?php foreach ($children as $child): 
            $fee = $fee_summary[$child['student_id']] ?? ['total_due' => 0, 'total_paid' => 0];
            $balance = ($fee['total_due'] ?? 0) - ($fee['total_paid'] ?? 0);
            $avg = round($child['current_term_avg'] ?? 0, 1);
            $grade_class = '';
            if ($avg >= 80) $grade_class = 'success';
            elseif ($avg >= 70) $grade_class = 'info';
            elseif ($avg >= 60) $grade_class = 'primary';
            elseif ($avg >= 50) $grade_class = 'warning';
            else $grade_class = 'danger';
        ?>
            <div class="child-card">
                <div class="child-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h5>
                            <small>Admission No: <?php echo $child['admission_no']; ?> | Class: <?php echo $child['class_name']; ?></small>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark">Term Average: <?php echo $avg; ?>%</span>
                            <span class="badge bg-<?php echo $grade_class; ?> ms-2">Grade: <?php echo calculateGrade($avg, $child['class_level']); ?></span>
                        </div>
                    </div>
                </div>
                <div class="child-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="performance-stat">
                                <div class="text-muted">Current Term Average</div>
                                <div class="h3 text-<?php echo $grade_class; ?>"><?php echo $avg; ?>%</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="performance-stat">
                                <div class="text-muted">Fee Balance</div>
                                <div class="h3 text-<?php echo $balance > 0 ? 'danger' : 'success'; ?>">
                                    <?php echo formatMoney($balance); ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="performance-stat">
                                <div class="text-muted">Class</div>
                                <div class="h3"><?php echo $child['class_name']; ?></div>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 d-flex justify-content-between">
                        <a href="../academics/student_grades.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-chart-line"></i> View Grades
                        </a>
                        <a href="../fees/student_balance.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-info">
                            <i class="fas fa-money-bill-wave"></i> Fee Details
                        </a>
                        <a href="../attendance/student_view.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                        <a href="../academics/report_cards.php?student_id=<?php echo $child['student_id']; ?>" class="btn btn-sm btn-warning">
                            <i class="fas fa-file-pdf"></i> Report Card
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
        
        <?php if (count($children) == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-child fa-3x text-muted mb-3"></i>
                    <p>No children linked to your account.</p>
                    <p class="text-muted small">Please contact the school administration to link your children.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Recent Notifications -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-bell"></i> Recent Notifications
                        <a href="../communication/history.php" class="btn btn-sm btn-primary float-end">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_notifications) > 0): ?>
                            <?php foreach ($recent_notifications as $notif): ?>
                                <div class="notification-item">
                                    <div class="d-flex justify-content-between">
                                        <strong><?php echo htmlspecialchars($notif['title']); ?></strong>
                                        <small class="text-muted"><?php echo formatDate($notif['created_at']); ?></small>
                                    </div>
                                    <p class="mb-0 small"><?php echo htmlspecialchars(substr($notif['message'], 0, 150)); ?>...</p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-bell-slash fa-2x text-muted mb-2"></i>
                                <p>No recent notifications.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Upcoming Events -->
            <div class="col-md-5">
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
                                <span class="badge bg-<?php echo $event['type'] == 'exam' ? 'danger' : ($event['type'] == 'conference' ? 'info' : 'success'); ?>">
                                    <?php echo ucfirst($event['type']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card mt-3">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-bolt"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="../fees/pay.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-credit-card fa-2x text-primary mb-2"></i>
                                <div>Pay Fees</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../communication/messages.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-envelope fa-2x text-success mb-2"></i>
                                <div>Contact Teacher</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../academics/report_cards.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                <div>Download Reports</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../medical/view.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-heartbeat fa-2x text-warning mb-2"></i>
                                <div>Medical Records</div>
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