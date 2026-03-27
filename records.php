<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_medical = ($user['role_name'] == 'medical');

if (!$is_admin && !$is_medical) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$error = '';
$success = '';

// Handle medical record update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_medical'])) {
    $student_id = (int)$_POST['student_id'];
    $blood_group = $_POST['blood_group'];
    $genotype = $_POST['genotype'];
    $allergies = trim($_POST['allergies']);
    $chronic_conditions = trim($_POST['chronic_conditions']);
    $medications = trim($_POST['medications']);
    $immunization_status = trim($_POST['immunization_status']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);
    
    // Check if record exists
    $check_sql = "SELECT medical_id FROM medical_records WHERE student_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $sql = "UPDATE medical_records SET blood_group = ?, genotype = ?, allergies = ?, chronic_conditions = ?, 
                medications = ?, immunization_status = ?, emergency_contact_name = ?, emergency_contact_phone = ? 
                WHERE student_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssi", $blood_group, $genotype, $allergies, $chronic_conditions, 
                          $medications, $immunization_status, $emergency_contact_name, $emergency_contact_phone, $student_id);
    } else {
        $sql = "INSERT INTO medical_records (student_id, blood_group, genotype, allergies, chronic_conditions, 
                medications, immunization_status, emergency_contact_name, emergency_contact_phone) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issssssss", $student_id, $blood_group, $genotype, $allergies, $chronic_conditions, 
                          $medications, $immunization_status, $emergency_contact_name, $emergency_contact_phone);
    }
    
    if ($stmt->execute()) {
        $success = "Medical records updated successfully!";
        logActivity($user['user_id'], 'UPDATE', 'medical_records', $student_id);
    } else {
        $error = "Failed to update medical records.";
    }
}

// Get student list for selection
$students_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name, s.current_class_id, c.class_name,
                 m.blood_group, m.allergies, m.chronic_conditions, m.emergency_contact_name, m.emergency_contact_phone
                 FROM students s
                 LEFT JOIN classes c ON s.current_class_id = c.class_id
                 LEFT JOIN medical_records m ON s.student_id = m.student_id
                 WHERE s.enrollment_status = 'Active'";

if (!empty($search)) {
    $students_sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ?)";
    $search_term = "%$search%";
    $stmt = $conn->prepare($students_sql);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
} else {
    $stmt = $conn->prepare($students_sql);
}
$stmt->execute();
$students_result = $stmt->get_result();

// Get selected student details
$selected_student = null;
if ($student_id > 0) {
    $detail_sql = "SELECT s.*, c.class_name, m.* 
                   FROM students s
                   LEFT JOIN classes c ON s.current_class_id = c.class_id
                   LEFT JOIN medical_records m ON s.student_id = m.student_id
                   WHERE s.student_id = ?";
    $detail_stmt = $conn->prepare($detail_sql);
    $detail_stmt->bind_param("i", $student_id);
    $detail_stmt->execute();
    $selected_student = $detail_stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Records - <?php echo SITE_NAME; ?></title>
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
        .student-list {
            max-height: 500px;
            overflow-y: auto;
        }
        .student-item {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
            transition: background 0.2s;
        }
        .student-item:hover {
            background: #f8f9fa;
        }
        .student-item.active {
            background: #e3f2fd;
            border-left: 3px solid #3498db;
        }
        .alert-badge {
            background: #f8d7da;
            color: #721c24;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-heartbeat"></i> Student Medical Records</h4>
            <a href="alerts.php" class="btn btn-warning">
                <i class="fas fa-bell"></i> Health Alerts
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Student List Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Students
                        <form method="GET" action="" class="mt-2">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search student..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="student-list">
                        <?php if ($students_result->num_rows > 0): ?>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                                <a href="?student_id=<?php echo $student['student_id']; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="student-item <?php echo ($student_id == $student['student_id']) ? 'active' : ''; ?> d-block text-decoration-none text-dark">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $student['admission_no']; ?> | <?php echo $student['class_name']; ?></small>
                                        </div>
                                        <?php if ($student['allergies'] || $student['chronic_conditions']): ?>
                                            <span class="alert-badge">
                                                <i class="fas fa-exclamation-triangle"></i> Alert
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted">
                                No students found.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Medical Record Form -->
            <div class="col-md-8">
                <?php if ($selected_student): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-notes-medical"></i> Medical Record
                            <span class="float-end"><?php echo htmlspecialchars($selected_student['first_name'] . ' ' . $selected_student['last_name']); ?></span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                <input type="hidden" name="save_medical" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Blood Group</label>
                                        <select name="blood_group" class="form-select">
                                            <option value="Unknown" <?php echo ($selected_student['blood_group'] ?? 'Unknown') == 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                            <option value="A+" <?php echo ($selected_student['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                                            <option value="A-" <?php echo ($selected_student['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                                            <option value="B+" <?php echo ($selected_student['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                                            <option value="B-" <?php echo ($selected_student['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                                            <option value="AB+" <?php echo ($selected_student['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                            <option value="AB-" <?php echo ($selected_student['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                            <option value="O+" <?php echo ($selected_student['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                                            <option value="O-" <?php echo ($selected_student['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Genotype</label>
                                        <select name="genotype" class="form-select">
                                            <option value="Unknown" <?php echo ($selected_student['genotype'] ?? 'Unknown') == 'Unknown' ? 'selected' : ''; ?>>Unknown</option>
                                            <option value="AA" <?php echo ($selected_student['genotype'] ?? '') == 'AA' ? 'selected' : ''; ?>>AA</option>
                                            <option value="AS" <?php echo ($selected_student['genotype'] ?? '') == 'AS' ? 'selected' : ''; ?>>AS</option>
                                            <option value="SS" <?php echo ($selected_student['genotype'] ?? '') == 'SS' ? 'selected' : ''; ?>>SS</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Allergies</label>
                                    <textarea name="allergies" class="form-control" rows="2" placeholder="List any allergies (food, drugs, insect bites, etc.)"><?php echo htmlspecialchars($selected_student['allergies'] ?? ''); ?></textarea>
                                    <small class="text-muted">e.g., Peanuts, Penicillin, Bee stings</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Chronic Conditions</label>
                                    <textarea name="chronic_conditions" class="form-control" rows="2" placeholder="List any chronic conditions (asthma, diabetes, epilepsy, etc.)"><?php echo htmlspecialchars($selected_student['chronic_conditions'] ?? ''); ?></textarea>
                                    <small class="text-muted">e.g., Asthma, Diabetes, Sickle Cell</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Regular Medications</label>
                                    <textarea name="medications" class="form-control" rows="2" placeholder="List any regular medications"><?php echo htmlspecialchars($selected_student['medications'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Immunization Status</label>
                                    <textarea name="immunization_status" class="form-control" rows="2" placeholder="List completed immunizations"><?php echo htmlspecialchars($selected_student['immunization_status'] ?? ''); ?></textarea>
                                </div>
                                
                                <hr>
                                <h6><i class="fas fa-phone-alt"></i> Emergency Contact</h6>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Emergency Contact Name</label>
                                        <input type="text" name="emergency_contact_name" class="form-control" value="<?php echo htmlspecialchars($selected_student['emergency_contact_name'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Emergency Contact Phone</label>
                                        <input type="tel" name="emergency_contact_phone" class="form-control" value="<?php echo htmlspecialchars($selected_student['emergency_contact_phone'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="text-end mt-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Medical Records
                                    </button>
                                    <a href="visits.php?student_id=<?php echo $student_id; ?>" class="btn btn-info">
                                        <i class="fas fa-clinic-medical"></i> View Clinic Visits
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                            <p>Select a student from the list to view or update medical records.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>