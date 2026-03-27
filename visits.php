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
$visit_id = isset($_GET['visit_id']) ? (int)$_GET['visit_id'] : 0;
$error = '';
$success = '';

// Get student name
$student_name = '';
if ($student_id > 0) {
    $student_name = getStudentName($student_id);
}

// Handle clinic visit recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_visit'])) {
    $student_id = (int)$_POST['student_id'];
    $visit_date = $_POST['visit_date'] . ' ' . $_POST['visit_time'];
    $symptoms = trim($_POST['symptoms']);
    $temperature = (float)$_POST['temperature'];
    $blood_pressure = trim($_POST['blood_pressure']);
    $weight = (float)$_POST['weight'];
    $height = (float)$_POST['height'];
    $diagnosis = trim($_POST['diagnosis']);
    $treatment = trim($_POST['treatment']);
    $medication_given = trim($_POST['medication_given']);
    $referred_to = trim($_POST['referred_to']);
    $follow_up_date = !empty($_POST['follow_up_date']) ? $_POST['follow_up_date'] : null;
    $notified_parent = isset($_POST['notified_parent']) ? 1 : 0;
    
    $sql = "INSERT INTO clinic_visits (student_id, visit_date, symptoms, temperature, blood_pressure, 
            weight, height, diagnosis, treatment, medication_given, referred_to, follow_up_date, 
            notified_parent, attended_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isssdddsssssii", $student_id, $visit_date, $symptoms, $temperature, $blood_pressure,
                      $weight, $height, $diagnosis, $treatment, $medication_given, $referred_to, 
                      $follow_up_date, $notified_parent, $user['user_id']);
    
    if ($stmt->execute()) {
        $success = "Clinic visit recorded successfully!";
        logActivity($user['user_id'], 'CREATE', 'clinic_visits', $stmt->insert_id);
        
        // Send SMS to parent if notified
        if ($notified_parent && SMS_ENABLED) {
            $parent_sql = "SELECT p.phone FROM parents p 
                           JOIN student_parents sp ON p.parent_id = sp.parent_id 
                           WHERE sp.student_id = ? LIMIT 1";
            $parent_stmt = $conn->prepare($parent_sql);
            $parent_stmt->bind_param("i", $student_id);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
            if ($parent = $parent_result->fetch_assoc()) {
                $message = "Greenhill Academy: $student_name visited the clinic on " . date('d M Y', strtotime($visit_date)) . ". Diagnosis: $diagnosis. Follow-up: " . ($follow_up_date ? date('d M Y', strtotime($follow_up_date)) : 'None') . ".";
                sendSMS($parent['phone'], $message);
            }
        }
    } else {
        $error = "Failed to record clinic visit.";
    }
}

// Get visit history
$visits = [];
if ($student_id > 0) {
    $visits_sql = "SELECT v.*, CONCAT(s.first_name, ' ', s.last_name) as staff_name
                   FROM clinic_visits v
                   LEFT JOIN staff s ON v.attended_by = s.staff_id
                   WHERE v.student_id = ?
                   ORDER BY v.visit_date DESC";
    $visits_stmt = $conn->prepare($visits_sql);
    $visits_stmt->bind_param("i", $student_id);
    $visits_stmt->execute();
    $visits_result = $visits_stmt->get_result();
    $visits = $visits_result->fetch_all(MYSQLI_ASSOC);
}

// Get single visit details
$single_visit = null;
if ($visit_id > 0) {
    $single_sql = "SELECT v.*, s.first_name, s.last_name, s.admission_no,
                   CONCAT(st.first_name, ' ', st.last_name) as attended_by_name
                   FROM clinic_visits v
                   JOIN students s ON v.student_id = s.student_id
                   LEFT JOIN staff st ON v.attended_by = st.staff_id
                   WHERE v.visit_id = ?";
    $single_stmt = $conn->prepare($single_sql);
    $single_stmt->bind_param("i", $visit_id);
    $single_stmt->execute();
    $single_visit = $single_stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Visits - <?php echo SITE_NAME; ?></title>
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
        .visit-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .visit-date {
            font-weight: bold;
            color: #2c3e50;
        }
        .print-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-clinic-medical"></i> Clinic Visits</h4>
            <?php if ($student_id > 0): ?>
                <a href="records.php?student_id=<?php echo $student_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Medical Records
                </a>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($single_visit): ?>
            <!-- Print Single Visit View -->
            <div class="card" id="print-area">
                <div class="card-header">
                    <i class="fas fa-print"></i> Clinic Visit Record
                    <button class="btn btn-sm btn-primary float-end" onclick="window.print()">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h3>GREENHILL ACADEMY</h3>
                        <p>Kampala, Uganda | Kibuli & Buwaate Campuses</p>
                        <h5>Clinic Visit Record</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Student Name:</strong> <?php echo htmlspecialchars($single_visit['first_name'] . ' ' . $single_visit['last_name']); ?></p>
                            <p><strong>Admission No:</strong> <?php echo $single_visit['admission_no']; ?></p>
                            <p><strong>Visit Date:</strong> <?php echo formatDate($single_visit['visit_date']); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Attended By:</strong> <?php echo $single_visit['attended_by_name']; ?></p>
                            <p><strong>Follow-up Date:</strong> <?php echo $single_visit['follow_up_date'] ? formatDate($single_visit['follow_up_date']) : 'None'; ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Symptoms:</strong> <?php echo nl2br(htmlspecialchars($single_visit['symptoms'])); ?></p>
                            <p><strong>Temperature:</strong> <?php echo $single_visit['temperature']; ?> °C</p>
                            <p><strong>Blood Pressure:</strong> <?php echo $single_visit['blood_pressure']; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Weight:</strong> <?php echo $single_visit['weight']; ?> kg</p>
                            <p><strong>Height:</strong> <?php echo $single_visit['height']; ?> cm</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <p><strong>Diagnosis:</strong> <?php echo nl2br(htmlspecialchars($single_visit['diagnosis'])); ?></p>
                            <p><strong>Treatment Given:</strong> <?php echo nl2br(htmlspecialchars($single_visit['treatment'])); ?></p>
                            <p><strong>Medication Given:</strong> <?php echo nl2br(htmlspecialchars($single_visit['medication_given'])); ?></p>
                            <p><strong>Referred To:</strong> <?php echo $single_visit['referred_to'] ?: 'None'; ?></p>
                        </div>
                    </div>
                    <hr>
                    <div class="signatures" style="display: flex; justify-content: space-between; margin-top: 50px;">
                        <div style="text-align: center; width: 45%;">
                            <div style="border-top: 1px solid #000; padding-top: 5px;">Medical Staff Signature</div>
                        </div>
                        <div style="text-align: center; width: 45%;">
                            <div style="border-top: 1px solid #000; padding-top: 5px;">Parent/Guardian Signature</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif ($student_id > 0): ?>
            <!-- Record New Visit Form -->
            <div class="row">
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-plus-circle"></i> Record New Clinic Visit
                            <span class="float-end">Student: <?php echo htmlspecialchars($student_name); ?></span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                                <input type="hidden" name="record_visit" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Visit Date</label>
                                        <input type="date" name="visit_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Visit Time</label>
                                        <input type="time" name="visit_time" class="form-control" value="<?php echo date('H:i'); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label required">Symptoms</label>
                                    <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe symptoms" required></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Temperature (°C)</label>
                                        <input type="number" name="temperature" class="form-control" step="0.1">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Blood Pressure</label>
                                        <input type="text" name="blood_pressure" class="form-control" placeholder="e.g., 120/80">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Weight (kg)</label>
                                        <input type="number" name="weight" class="form-control" step="0.1">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label">Height (cm)</label>
                                        <input type="number" name="height" class="form-control" step="0.1">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Diagnosis</label>
                                    <textarea name="diagnosis" class="form-control" rows="2" placeholder="Diagnosis"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Treatment Given</label>
                                    <textarea name="treatment" class="form-control" rows="2" placeholder="Treatment provided"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Medication Given</label>
                                    <textarea name="medication_given" class="form-control" rows="2" placeholder="Medication dispensed"></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Referred To</label>
                                        <input type="text" name="referred_to" class="form-control" placeholder="Hospital or specialist">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Follow-up Date</label>
                                        <input type="date" name="follow_up_date" class="form-control">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input type="checkbox" name="notified_parent" class="form-check-input" id="notified_parent">
                                        <label class="form-check-label" for="notified_parent">
                                            Notify parent via SMS
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Record Visit
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-history"></i> Visit History
                        </div>
                        <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                            <?php if (count($visits) > 0): ?>
                                <?php foreach ($visits as $visit): ?>
                                    <div class="visit-item">
                                        <div class="visit-date"><?php echo formatDate($visit['visit_date']); ?></div>
                                        <div><strong>Diagnosis:</strong> <?php echo htmlspecialchars($visit['diagnosis']); ?></div>
                                        <div><strong>Treatment:</strong> <?php echo htmlspecialchars($visit['treatment']); ?></div>
                                        <div class="mt-2">
                                            <a href="?visit_id=<?php echo $visit['visit_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View Details
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center">No previous visits recorded.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-user-md fa-3x text-muted mb-3"></i>
                    <p>Please select a student from the Medical Records page to record clinic visits.</p>
                    <a href="records.php" class="btn btn-primary">Go to Medical Records</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <style>
        @media print {
            .sidebar, .main-content > .d-flex, .btn, .card-header .btn, .footer {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
        }
    </style>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>