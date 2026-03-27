<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';

// Get classes for dropdown
$classes_sql = "SELECT class_id, class_name, class_level FROM classes WHERE is_active = 1 ORDER BY class_level, class_name";
$classes_result = $conn->query($classes_sql);

// Get campuses for dropdown
$campuses_sql = "SELECT campus_id, campus_name FROM campuses WHERE is_active = 1";
$campuses_result = $conn->query($campuses_sql);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $nationality = $_POST['nationality'] ?? 'Ugandan';
    $religion = $_POST['religion'] ?? '';
    $previous_school = trim($_POST['previous_school'] ?? '');
    $previous_school_address = trim($_POST['previous_school_address'] ?? '');
    $previous_class = trim($_POST['previous_class'] ?? '');
    $applying_for_class_id = (int)$_POST['applying_for_class_id'];
    $applying_for_campus_id = (int)$_POST['applying_for_campus_id'];
    $parent_name = trim($_POST['parent_name']);
    $parent_phone = trim($_POST['parent_phone']);
    $parent_email = trim($_POST['parent_email'] ?? '');
    $parent_address = trim($_POST['parent_address'] ?? '');
    $relationship = $_POST['relationship'];
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($date_of_birth) || empty($gender) || 
        empty($applying_for_class_id) || empty($applying_for_campus_id) || empty($parent_name) || empty($parent_phone)) {
        $error = "Please fill all required fields.";
    } else {
        // Generate application number
        $application_no = generateCode('APP', 'admissions', 'application_no');
        
        // Handle file uploads
        $birth_certificate_path = '';
        $report_card_path = '';
        $passport_photo_path = '';
        
        if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] == 0) {
            $upload_dir = UPLOAD_PATH . 'documents/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $ext = pathinfo($_FILES['birth_certificate']['name'], PATHINFO_EXTENSION);
            $filename = $application_no . '_birth_cert.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['birth_certificate']['tmp_name'], $target)) {
                $birth_certificate_path = 'uploads/documents/' . $filename;
            }
        }
        
        if (isset($_FILES['report_card']) && $_FILES['report_card']['error'] == 0) {
            $upload_dir = UPLOAD_PATH . 'documents/';
            $ext = pathinfo($_FILES['report_card']['name'], PATHINFO_EXTENSION);
            $filename = $application_no . '_report_card.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['report_card']['tmp_name'], $target)) {
                $report_card_path = 'uploads/documents/' . $filename;
            }
        }
        
        if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] == 0) {
            $upload_dir = UPLOAD_PATH . 'documents/';
            $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
            $filename = $application_no . '_photo.' . $ext;
            $target = $upload_dir . $filename;
            if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $target)) {
                $passport_photo_path = 'uploads/documents/' . $filename;
            }
        }
        
        // Insert application
        $sql = "INSERT INTO admissions (application_no, first_name, last_name, middle_name, date_of_birth, gender,
                nationality, religion, previous_school, previous_school_address, previous_class,
                applying_for_class_id, applying_for_campus_id, parent_name, parent_phone, parent_email,
                parent_address, relationship, application_date, birth_certificate_path, report_card_path,
                passport_photo_path, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), ?, ?, ?, 'Pending')";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssiisssssssss", 
            $application_no, $first_name, $last_name, $middle_name, $date_of_birth, $gender,
            $nationality, $religion, $previous_school, $previous_school_address, $previous_class,
            $applying_for_class_id, $applying_for_campus_id, $parent_name, $parent_phone, $parent_email,
            $parent_address, $relationship, $birth_certificate_path, $report_card_path, $passport_photo_path
        );
        
        if ($stmt->execute()) {
            $admission_id = $stmt->insert_id;
            $success = "Application submitted successfully!<br>
                        Application Number: <strong>$application_no</strong><br>
                        Please save this number for future reference.";
            
            // Send confirmation SMS
            if (SMS_ENABLED) {
                $message = "Dear $parent_name, your application for $first_name $last_name has been received. Application No: $application_no. We will contact you soon. - Greenhill Academy";
                sendSMS($parent_phone, $message);
            }
            
            // Clear form
            $_POST = array();
        } else {
            $error = "Failed to submit application: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Admission - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }
        .application-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .form-section {
            padding: 30px;
        }
        .form-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .form-card h5 {
            color: #2c3e50;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #3498db;
        }
        .required:after {
            content: " *";
            color: red;
        }
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            font-size: 16px;
            font-weight: 600;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="application-container">
        <div class="header">
            <img src="../../assets/images/logo.png" alt="Greenhill Academy" style="max-width: 80px; margin-bottom: 15px;" onerror="this.style.display='none'">
            <h2>Greenhill Academy</h2>
            <p>Admission Application Form</p>
        </div>
        
        <div class="form-section">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="" enctype="multipart/form-data">
                <!-- Student Information -->
                <div class="form-card">
                    <h5><i class="fas fa-child"></i> Student Information</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">First Name</label>
                            <input type="text" name="first_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Last Name</label>
                            <input type="text" name="last_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middle_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label required">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male" <?php echo (($_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Nationality</label>
                            <input type="text" name="nationality" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['nationality'] ?? 'Ugandan'); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Religion</label>
                            <input type="text" name="religion" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['religion'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Previous School Information -->
                <div class="form-card">
                    <h5><i class="fas fa-school"></i> Previous School Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Previous School</label>
                            <input type="text" name="previous_school" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['previous_school'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Previous Class</label>
                            <input type="text" name="previous_class" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['previous_class'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Previous School Address</label>
                            <textarea name="previous_school_address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['previous_school_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Applying For -->
                <div class="form-card">
                    <h5><i class="fas fa-graduation-cap"></i> Applying For</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Class Applying For</label>
                            <select name="applying_for_class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                    <option value="<?php echo $class['class_id']; ?>" 
                                        <?php echo (($_POST['applying_for_class_id'] ?? '') == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Campus</label>
                            <select name="applying_for_campus_id" class="form-select" required>
                                <option value="">Select Campus</option>
                                <?php while ($campus = $campuses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $campus['campus_id']; ?>" 
                                        <?php echo (($_POST['applying_for_campus_id'] ?? '') == $campus['campus_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campus['campus_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Parent/Guardian Information -->
                <div class="form-card">
                    <h5><i class="fas fa-users"></i> Parent/Guardian Information</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Parent/Guardian Name</label>
                            <input type="text" name="parent_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['parent_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Relationship</label>
                            <select name="relationship" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Father" <?php echo (($_POST['relationship'] ?? '') == 'Father') ? 'selected' : ''; ?>>Father</option>
                                <option value="Mother" <?php echo (($_POST['relationship'] ?? '') == 'Mother') ? 'selected' : ''; ?>>Mother</option>
                                <option value="Guardian" <?php echo (($_POST['relationship'] ?? '') == 'Guardian') ? 'selected' : ''; ?>>Guardian</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="parent_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['parent_phone'] ?? ''); ?>" required>
                            <small class="text-muted">Format: 07XXXXXXXX</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="parent_email" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['parent_email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Residential Address</label>
                            <textarea name="parent_address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['parent_address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Documents -->
                <div class="form-card">
                    <h5><i class="fas fa-file-upload"></i> Required Documents</h5>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Birth Certificate</label>
                            <input type="file" name="birth_certificate" class="form-control" accept=".pdf,.jpg,.png">
                            <small class="text-muted">PDF or Image (Max 5MB)</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Report Card</label>
                            <input type="file" name="report_card" class="form-control" accept=".pdf,.jpg,.png">
                            <small class="text-muted">PDF or Image (Max 5MB)</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Passport Photo</label>
                            <input type="file" name="passport_photo" class="form-control" accept=".jpg,.png">
                            <small class="text-muted">JPG or PNG (Max 2MB)</small>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="reset" class="btn btn-secondary me-2">
                        <i class="fas fa-undo"></i> Clear Form
                    </button>
                    <button type="submit" class="btn btn-primary btn-submit">
                        <i class="fas fa-paper-plane"></i> Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>