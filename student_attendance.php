<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_teacher = ($user['role_name'] == 'teacher');

if (!$is_admin && !$is_teacher) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$attendance_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$error = '';
$success = '';

// Get classes for teacher
if ($is_teacher) {
    $teacher_sql = "SELECT DISTINCT c.class_id, c.class_name 
                    FROM staff s 
                    JOIN class_subjects cs ON cs.teacher_id = s.staff_id 
                    JOIN classes c ON cs.class_id = c.class_id 
                    WHERE s.user_id = ?";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bind_param("i", $user['user_id']);
    $teacher_stmt->execute();
    $teacher_classes = $teacher_stmt->get_result();
    $class_options = [];
    while ($row = $teacher_classes->fetch_assoc()) {
        $class_options[$row['class_id']] = $row['class_name'];
    }
} else {
    $all_classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
    $all_classes_result = $conn->query($all_classes_sql);
    $class_options = [];
    while ($row = $all_classes_result->fetch_assoc()) {
        $class_options[$row['class_id']] = $row['class_name'];
    }
}

// Get students for selected class
$students = [];
$attendance_records = [];
if ($class_id > 0 && isset($class_options[$class_id])) {
    $students_sql = "SELECT student_id, admission_no, first_name, last_name 
                     FROM students 
                     WHERE current_class_id = ? AND enrollment_status = 'Active'
                     ORDER BY last_name, first_name";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("i", $class_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    $students = $students_result->fetch_all(MYSQLI_ASSOC);
    
    // Get existing attendance for this date
    if (count($students) > 0) {
        $ids = implode(',', array_column($students, 'student_id'));
        $attendance_sql = "SELECT student_id, status, arrival_time, reason 
                           FROM student_attendance 
                           WHERE attendance_date = ? AND student_id IN ($ids)";
        $attendance_stmt = $conn->prepare($attendance_sql);
        $attendance_stmt->bind_param("s", $attendance_date);
        $attendance_stmt->execute();
        $attendance_result = $attendance_stmt->get_result();
        while ($row = $attendance_result->fetch_assoc()) {
            $attendance_records[$row['student_id']] = $row;
        }
    }
}

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $class_id = (int)$_POST['class_id'];
    $attendance_date = $_POST['attendance_date'];
    $statuses = $_POST['status'];
    $reasons = $_POST['reason'];
    
    $saved = 0;
    foreach ($statuses as $student_id => $status) {
        $reason = $reasons[$student_id] ?? '';
        $arrival_time = ($status == 'Late') ? date('H:i:s') : null;
        
        // Check if record exists
        $check_sql = "SELECT attendance_id FROM student_attendance 
                      WHERE student_id = ? AND attendance_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $student_id, $attendance_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_sql = "UPDATE student_attendance 
                           SET status = ?, arrival_time = ?, reason = ?, marked_by = ?, marked_at = NOW() 
                           WHERE student_id = ? AND attendance_date = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssiss", $status, $arrival_time, $reason, $user['user_id'], $student_id, $attendance_date);
            $update_stmt->execute();
        } else {
            $insert_sql = "INSERT INTO student_attendance (student_id, class_id, attendance_date, status, arrival_time, reason, marked_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iissssi", $student_id, $class_id, $attendance_date, $status, $arrival_time, $reason, $user['user_id']);
            $insert_stmt->execute();
        }
        
        // Send SMS for absent students
        if ($status == 'Absent' && SMS_ENABLED) {
            $student_name = getStudentName($student_id);
            $parent_sql = "SELECT p.phone FROM parents p 
                           JOIN student_parents sp ON p.parent_id = sp.parent_id 
                           WHERE sp.student_id = ? AND p.is_primary_contact = 1 LIMIT 1";
            $parent_stmt = $conn->prepare($parent_sql);
            $parent_stmt->bind_param("i", $student_id);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
            if ($parent = $parent_result->fetch_assoc()) {
                $message = "Greenhill Academy: $student_name was absent on " . date('d M Y', strtotime($attendance_date)) . ". Reason: " . ($reason ?: 'Not specified') . ". Please contact the school if you have any concerns.";
                sendSMS($parent['phone'], $message);
            }
        }
        $saved++;
    }
    
    if ($saved > 0) {
        $success = "Attendance saved successfully for $saved student(s)!";
        logActivity($user['user_id'], 'MARK_ATTENDANCE', 'student_attendance', $class_id);
        
        // Refresh attendance records
        $attendance_records = [];
        if (count($students) > 0) {
            $ids = implode(',', array_column($students, 'student_id'));
            $attendance_sql = "SELECT student_id, status, arrival_time, reason 
                               FROM student_attendance 
                               WHERE attendance_date = ? AND student_id IN ($ids)";
            $attendance_stmt = $conn->prepare($attendance_sql);
            $attendance_stmt->bind_param("s", $attendance_date);
            $attendance_stmt->execute();
            $attendance_result = $attendance_stmt->get_result();
            while ($row = $attendance_result->fetch_assoc()) {
                $attendance_records[$row['student_id']] = $row;
            }
        }
    }
}

// Calculate attendance summary
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$excused_count = 0;

foreach ($students as $student) {
    $status = $attendance_records[$student['student_id']]['status'] ?? '';
    if ($status == 'Present') $present_count++;
    elseif ($status == 'Absent') $absent_count++;
    elseif ($status == 'Late') $late_count++;
    elseif ($status == 'Excused') $excused_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #f4f6f9;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 600;
        }
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .summary-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        .summary-number {
            font-size: 28px;
            font-weight: bold;
        }
        .present { color: #28a745; }
        .absent { color: #dc3545; }
        .late { color: #ffc107; }
        .excused { color: #17a2b8; }
        .attendance-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }
        .status-select {
            width: 120px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-calendar-check"></i> Student Attendance</h4>
            <a href="reports.php" class="btn btn-info">
                <i class="fas fa-chart-line"></i> Attendance Reports
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Select Class --</option>
                        <?php foreach ($class_options as $cid => $cname): ?>
                            <option value="<?php echo $cid; ?>" <?php echo ($class_id == $cid) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $attendance_date; ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-outline-primary" onclick="setToday()">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($class_id > 0 && count($students) > 0): ?>
            <!-- Attendance Summary -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-number present"><?php echo $present_count; ?></div>
                        <div>Present</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-number absent"><?php echo $absent_count; ?></div>
                        <div>Absent</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-number late"><?php echo $late_count; ?></div>
                        <div>Late</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="summary-card">
                        <div class="summary-number excused"><?php echo $excused_count; ?></div>
                        <div>Excused</div>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Mark Attendance - <?php echo date('l, d M Y', strtotime($attendance_date)); ?>
                    <span class="badge bg-secondary float-end"><?php echo count($students); ?> students</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                        <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
                        <input type="hidden" name="save_attendance" value="1">
                        
                        <div class="table-responsive">
                            <table class="table table-bordered attendance-table">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Admission No</th>
                                        <th>Student Name</th>
                                        <th width="130">Status</th>
                                        <th>Reason (if absent/late)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): 
                                        $current_status = $attendance_records[$student['student_id']]['status'] ?? 'Present';
                                        $current_reason = $attendance_records[$student['student_id']]['reason'] ?? '';
                                    ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo $student['admission_no']; ?></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td>
                                                <select name="status[<?php echo $student['student_id']; ?>]" class="form-select status-select" onchange="toggleReason(this, <?php echo $student['student_id']; ?>)">
                                                    <option value="Present" <?php echo $current_status == 'Present' ? 'selected' : ''; ?>>Present</option>
                                                    <option value="Absent" <?php echo $current_status == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                    <option value="Late" <?php echo $current_status == 'Late' ? 'selected' : ''; ?>>Late</option>
                                                    <option value="Excused" <?php echo $current_status == 'Excused' ? 'selected' : ''; ?>>Excused</option>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="text" name="reason[<?php echo $student['student_id']; ?>]" 
                                                       class="form-control reason-input-<?php echo $student['student_id']; ?>"
                                                       value="<?php echo htmlspecialchars($current_reason); ?>"
                                                       placeholder="Reason for absence/late"
                                                       <?php echo ($current_status == 'Present') ? 'disabled' : ''; ?>>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save Attendance
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function toggleReason(select, studentId) {
                    let reasonInput = document.querySelector(`.reason-input-${studentId}`);
                    if (select.value === 'Present') {
                        reasonInput.disabled = true;
                        reasonInput.value = '';
                    } else {
                        reasonInput.disabled = false;
                    }
                }
                
                function setToday() {
                    let today = new Date().toISOString().split('T')[0];
                    window.location.href = `?class_id=<?php echo $class_id; ?>&date=${today}`;
                }
            </script>
            
        <?php elseif ($class_id > 0 && count($students) == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p>No students found in this class.</p>
                </div>
            </div>
        <?php elseif ($class_id == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                    <p>Please select a class to mark attendance.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>