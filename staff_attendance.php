<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_accountant = ($user['role_name'] == 'accountant');

if (!$is_admin && !$is_accountant) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$attendance_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$department_filter = isset($_GET['department']) ? trim($_GET['department']) : '';
$error = '';
$success = '';

// Get all active staff
$staff_sql = "SELECT staff_id, staff_no, first_name, last_name, position, department 
              FROM staff 
              WHERE is_active = 1";
if (!empty($department_filter)) {
    $staff_sql .= " AND department = ?";
    $staff_stmt = $conn->prepare($staff_sql);
    $staff_stmt->bind_param("s", $department_filter);
} else {
    $staff_stmt = $conn->prepare($staff_sql);
}
$staff_stmt->execute();
$staff_result = $staff_stmt->get_result();
$staff = $staff_result->fetch_all(MYSQLI_ASSOC);

// Get existing attendance
$attendance_records = [];
if (count($staff) > 0) {
    $ids = implode(',', array_column($staff, 'staff_id'));
    $attendance_sql = "SELECT staff_id, status, arrival_time, departure_time, reason 
                       FROM staff_attendance 
                       WHERE attendance_date = ? AND staff_id IN ($ids)";
    $attendance_stmt = $conn->prepare($attendance_sql);
    $attendance_stmt->bind_param("s", $attendance_date);
    $attendance_stmt->execute();
    $attendance_result = $attendance_stmt->get_result();
    while ($row = $attendance_result->fetch_assoc()) {
        $attendance_records[$row['staff_id']] = $row;
    }
}

// Get departments for filter
$dept_sql = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != ''";
$dept_result = $conn->query($dept_sql);

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $statuses = $_POST['status'];
    $arrival_times = $_POST['arrival_time'];
    $departure_times = $_POST['departure_time'];
    $reasons = $_POST['reason'];
    
    $saved = 0;
    foreach ($statuses as $staff_id => $status) {
        $arrival_time = $arrival_times[$staff_id] ?? null;
        $departure_time = $departure_times[$staff_id] ?? null;
        $reason = $reasons[$staff_id] ?? '';
        
        // Check if record exists
        $check_sql = "SELECT attendance_id FROM staff_attendance 
                      WHERE staff_id = ? AND attendance_date = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("is", $staff_id, $attendance_date);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_sql = "UPDATE staff_attendance 
                           SET status = ?, arrival_time = ?, departure_time = ?, reason = ?, marked_by = ?, marked_at = NOW() 
                           WHERE staff_id = ? AND attendance_date = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("sssssis", $status, $arrival_time, $departure_time, $reason, $user['user_id'], $staff_id, $attendance_date);
            $update_stmt->execute();
        } else {
            $insert_sql = "INSERT INTO staff_attendance (staff_id, attendance_date, status, arrival_time, departure_time, reason, marked_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("isssssi", $staff_id, $attendance_date, $status, $arrival_time, $departure_time, $reason, $user['user_id']);
            $insert_stmt->execute();
        }
        $saved++;
    }
    
    if ($saved > 0) {
        $success = "Staff attendance saved successfully for $saved staff member(s)!";
        logActivity($user['user_id'], 'MARK_STAFF_ATTENDANCE', 'staff_attendance', 0);
        
        // Refresh attendance records
        $attendance_records = [];
        if (count($staff) > 0) {
            $ids = implode(',', array_column($staff, 'staff_id'));
            $attendance_sql = "SELECT staff_id, status, arrival_time, departure_time, reason 
                               FROM staff_attendance 
                               WHERE attendance_date = ? AND staff_id IN ($ids)";
            $attendance_stmt = $conn->prepare($attendance_sql);
            $attendance_stmt->bind_param("s", $attendance_date);
            $attendance_stmt->execute();
            $attendance_result = $attendance_stmt->get_result();
            while ($row = $attendance_result->fetch_assoc()) {
                $attendance_records[$row['staff_id']] = $row;
            }
        }
    }
}

// Calculate summary
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$leave_count = 0;

foreach ($staff as $member) {
    $status = $attendance_records[$member['staff_id']]['status'] ?? '';
    if ($status == 'Present') $present_count++;
    elseif ($status == 'Absent') $absent_count++;
    elseif ($status == 'Late') $late_count++;
    elseif ($status == 'Leave') $leave_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Attendance - <?php echo SITE_NAME; ?></title>
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
        .leave { color: #17a2b8; }
        .status-select {
            width: 100px;
        }
        .time-input {
            width: 100px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-user-clock"></i> Staff Attendance</h4>
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
                <div class="col-md-3">
                    <label class="form-label">Date</label>
                    <input type="date" name="date" class="form-control" value="<?php echo $attendance_date; ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Department</label>
                    <select name="department" class="form-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php while ($dept = $dept_result->fetch_assoc()): ?>
                            <option value="<?php echo $dept['department']; ?>" <?php echo ($department_filter == $dept['department']) ? 'selected' : ''; ?>>
                                <?php echo $dept['department']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <button type="button" class="btn btn-outline-primary" onclick="setToday()">
                            <i class="fas fa-calendar-day"></i> Today
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <?php if ($department_filter): ?>
                            <a href="?date=<?php echo $attendance_date; ?>" class="btn btn-secondary w-100">Clear Filter</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
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
                    <div class="summary-number leave"><?php echo $leave_count; ?></div>
                    <div>On Leave</div>
                </div>
            </div>
        </div>
        
        <!-- Attendance Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Mark Staff Attendance - <?php echo date('l, d M Y', strtotime($attendance_date)); ?>
                <span class="badge bg-secondary float-end"><?php echo count($staff); ?> staff members</span>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="attendance_date" value="<?php echo $attendance_date; ?>">
                    <input type="hidden" name="save_attendance" value="1">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="50">#</th>
                                    <th>Staff No</th>
                                    <th>Staff Name</th>
                                    <th>Position</th>
                                    <th>Department</th>
                                    <th width="100">Status</th>
                                    <th width="100">Arrival</th>
                                    <th width="100">Departure</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff as $index => $member): 
                                    $current_status = $attendance_records[$member['staff_id']]['status'] ?? 'Present';
                                    $current_arrival = $attendance_records[$member['staff_id']]['arrival_time'] ?? '';
                                    $current_departure = $attendance_records[$member['staff_id']]['departure_time'] ?? '';
                                    $current_reason = $attendance_records[$member['staff_id']]['reason'] ?? '';
                                ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo $member['staff_no']; ?></td>
                                        <td><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></td>
                                        <td><?php echo $member['position']; ?></td>
                                        <td><?php echo $member['department']; ?></td>
                                        <td>
                                            <select name="status[<?php echo $member['staff_id']; ?>]" class="form-select status-select" onchange="toggleStaffReason(this, <?php echo $member['staff_id']; ?>)">
                                                <option value="Present" <?php echo $current_status == 'Present' ? 'selected' : ''; ?>>Present</option>
                                                <option value="Absent" <?php echo $current_status == 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                                <option value="Late" <?php echo $current_status == 'Late' ? 'selected' : ''; ?>>Late</option>
                                                <option value="Leave" <?php echo $current_status == 'Leave' ? 'selected' : ''; ?>>On Leave</option>
                                            </select>
                                        </td>
                                        <td>
                                            <input type="time" name="arrival_time[<?php echo $member['staff_id']; ?>]" 
                                                   class="form-control time-input"
                                                   value="<?php echo $current_arrival; ?>"
                                                   <?php echo ($current_status == 'Absent' || $current_status == 'Leave') ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="time" name="departure_time[<?php echo $member['staff_id']; ?>]" 
                                                   class="form-control time-input"
                                                   value="<?php echo $current_departure; ?>"
                                                   <?php echo ($current_status == 'Absent' || $current_status == 'Leave') ? 'disabled' : ''; ?>>
                                        </td>
                                        <td>
                                            <input type="text" name="reason[<?php echo $member['staff_id']; ?>]" 
                                                   class="form-control reason-input-<?php echo $member['staff_id']; ?>"
                                                   value="<?php echo htmlspecialchars($current_reason); ?>"
                                                   placeholder="Reason"
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
            function toggleStaffReason(select, staffId) {
                let reasonInput = document.querySelector(`.reason-input-${staffId}`);
                let arrivalInput = document.querySelector(`input[name="arrival_time[${staffId}]"]`);
                let departureInput = document.querySelector(`input[name="departure_time[${staffId}]"]`);
                
                if (select.value === 'Present') {
                    reasonInput.disabled = true;
                    reasonInput.value = '';
                    arrivalInput.disabled = false;
                    departureInput.disabled = false;
                } else if (select.value === 'Absent' || select.value === 'Leave') {
                    reasonInput.disabled = false;
                    arrivalInput.disabled = true;
                    arrivalInput.value = '';
                    departureInput.disabled = true;
                    departureInput.value = '';
                } else if (select.value === 'Late') {
                    reasonInput.disabled = false;
                    arrivalInput.disabled = false;
                    departureInput.disabled = false;
                }
            }
            
            function setToday() {
                let today = new Date().toISOString().split('T')[0];
                let url = new URL(window.location.href);
                url.searchParams.set('date', today);
                window.location.href = url.toString();
            }
        </script>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>