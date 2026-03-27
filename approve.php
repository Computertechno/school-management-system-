<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Only admin can approve
if ($user['role_name'] != 'admin') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$admission_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($admission_id <= 0) {
    redirect('index.php');
}

// Get application details
$sql = "SELECT * FROM admissions WHERE admission_id = ? AND status = 'Pending'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $admission_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    $_SESSION['error'] = "Application not found or already processed.";
    redirect('index.php');
}

$application = $result->fetch_assoc();

// Check class capacity
$capacity_sql = "SELECT class_id, class_name, capacity, 
                 (SELECT COUNT(*) FROM students WHERE current_class_id = classes.class_id AND enrollment_status = 'Active') as current_count
                 FROM classes 
                 WHERE class_id = ?";
$capacity_stmt = $conn->prepare($capacity_sql);
$capacity_stmt->bind_param("i", $application['applying_for_class_id']);
$capacity_stmt->execute();
$capacity_result = $capacity_stmt->get_result();
$class = $capacity_result->fetch_assoc();

$has_capacity = ($class['current_count'] < $class['capacity']);
$available_spots = $class['capacity'] - $class['current_count'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'approve') {
        // Update application status
        $update_sql = "UPDATE admissions SET status = 'Approved', approved_by = ?, approved_at = NOW() WHERE admission_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $user['user_id'], $admission_id);
        
        if ($update_stmt->execute()) {
            logActivity($user['user_id'], 'APPROVE_ADMISSION', 'admissions', $admission_id);
            
            // Send approval SMS
            if (SMS_ENABLED) {
                $message = "Dear " . $application['parent_name'] . ", your child " . $application['first_name'] . " " . $application['last_name'] . " has been admitted to Greenhill Academy. Please visit the school to complete enrollment. - Greenhill Academy";
                sendSMS($application['parent_phone'], $message);
            }
            
            $success = "Application approved successfully!";
        } else {
            $error = "Failed to approve application.";
        }
    } elseif ($action == 'waitlist') {
        // Get current waitlist count
        $waitlist_sql = "SELECT COUNT(*) as count FROM admissions WHERE applying_for_class_id = ? AND status = 'Waitlisted'";
        $waitlist_stmt = $conn->prepare($waitlist_sql);
        $waitlist_stmt->bind_param("i", $application['applying_for_class_id']);
        $waitlist_stmt->execute();
        $waitlist_result = $waitlist_stmt->get_result();
        $waitlist_count = $waitlist_result->fetch_assoc()['count'];
        
        $position = $waitlist_count + 1;
        
        $update_sql = "UPDATE admissions SET status = 'Waitlisted', waiting_list_position = ? WHERE admission_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ii", $position, $admission_id);
        
        if ($update_stmt->execute()) {
            logActivity($user['user_id'], 'WAITLIST_ADMISSION', 'admissions', $admission_id);
            
            // Send waitlist SMS
            if (SMS_ENABLED) {
                $message = "Dear " . $application['parent_name'] . ", your child " . $application['first_name'] . " " . $application['last_name'] . " has been placed on waitlist (Position: $position). We will contact you when a slot opens. - Greenhill Academy";
                sendSMS($application['parent_phone'], $message);
            }
            
            $success = "Application added to waitlist (Position: $position)";
        } else {
            $error = "Failed to add to waitlist.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Application - <?php echo SITE_NAME; ?></title>
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
        .info-row {
            display: flex;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-label {
            width: 180px;
            font-weight: 600;
            color: #2c3e50;
        }
        .info-value {
            flex: 1;
            color: #34495e;
        }
        .capacity-bar {
            background: #e9ecef;
            border-radius: 10px;
            height: 30px;
            overflow: hidden;
            margin: 15px 0;
        }
        .capacity-fill {
            background: #28a745;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-check-circle"></i> Review Application</h4>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Applications
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Application Details -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-graduate"></i> Application Details
                        <span class="badge bg-warning float-end">Application #<?php echo $application['application_no']; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Student Name:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Date of Birth:</div>
                                    <div class="info-value"><?php echo formatDate($application['date_of_birth']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Gender:</div>
                                    <div class="info-value"><?php echo $application['gender']; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Previous School:</div>
                                    <div class="info-value"><?php echo htmlspecialchars($application['previous_school'] ?: 'N/A'); ?></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-row">
                                    <div class="info-label">Applying For:</div>
                                    <div class="info-value"><?php echo getClassName($application['applying_for_class_id']); ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Campus:</div>
                                    <div class="info-value"><?php echo $application['applying_for_campus_id'] == 1 ? 'Kibuli' : 'Buwaate'; ?></div>
                                </div>
                                <div class="info-row">
                                    <div class="info-label">Application Date:</div>
                                    <div class="info-value"><?php echo formatDate($application['application_date']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Parent Information -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-users"></i> Parent/Guardian Information
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Parent Name:</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['parent_name']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Relationship:</div>
                            <div class="info-value"><?php echo $application['relationship']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Phone:</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['parent_phone']); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Email:</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['parent_email'] ?: 'N/A'); ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Address:</div>
                            <div class="info-value"><?php echo nl2br(htmlspecialchars($application['parent_address'] ?: 'N/A')); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Documents -->
                <?php if ($application['birth_certificate_path'] || $application['report_card_path'] || $application['passport_photo_path']): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-alt"></i> Uploaded Documents
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if ($application['birth_certificate_path']): ?>
                            <div class="col-md-4">
                                <a href="../../<?php echo $application['birth_certificate_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-pdf"></i> Birth Certificate
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($application['report_card_path']): ?>
                            <div class="col-md-4">
                                <a href="../../<?php echo $application['report_card_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-file-pdf"></i> Report Card
                                </a>
                            </div>
                            <?php endif; ?>
                            <?php if ($application['passport_photo_path']): ?>
                            <div class="col-md-4">
                                <a href="../../<?php echo $application['passport_photo_path']; ?>" target="_blank" class="btn btn-sm btn-info">
                                    <i class="fas fa-image"></i> Passport Photo
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-md-4">
                <!-- Class Capacity -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chalkboard"></i> Class Capacity
                    </div>
                    <div class="card-body">
                        <div class="info-row">
                            <div class="info-label">Class:</div>
                            <div class="info-value"><?php echo $class['class_name']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Capacity:</div>
                            <div class="info-value"><?php echo $class['capacity']; ?> students</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Current Students:</div>
                            <div class="info-value"><?php echo $class['current_count']; ?></div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Available Spots:</div>
                            <div class="info-value">
                                <strong><?php echo $available_spots; ?></strong>
                                <?php if ($available_spots <= 0): ?>
                                    <span class="text-danger">(Full)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="capacity-bar">
                            <div class="capacity-fill" style="width: <?php echo ($class['current_count'] / $class['capacity']) * 100; ?>%;">
                                <?php echo round(($class['current_count'] / $class['capacity']) * 100); ?>%
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-gavel"></i> Decision
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php if ($has_capacity): ?>
                                <button type="submit" name="action" value="approve" class="btn btn-success w-100 mb-2">
                                    <i class="fas fa-check"></i> Approve Application
                                </button>
                                <button type="submit" name="action" value="waitlist" class="btn btn-warning w-100">
                                    <i class="fas fa-clock"></i> Add to Waitlist
                                </button>
                            <?php else: ?>
                                <button type="submit" name="action" value="waitlist" class="btn btn-warning w-100 mb-2">
                                    <i class="fas fa-clock"></i> Add to Waitlist
                                </button>
                                <button type="button" class="btn btn-secondary w-100" disabled>
                                    <i class="fas fa-ban"></i> Class Full - Cannot Approve
                                </button>
                            <?php endif; ?>
                        </form>
                        
                        <hr>
                        
                        <a href="reject.php?id=<?php echo $admission_id; ?>" class="btn btn-danger w-100" onclick="return confirm('Are you sure you want to reject this application?')">
                            <i class="fas fa-times"></i> Reject Application
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>