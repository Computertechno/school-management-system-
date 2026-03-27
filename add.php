<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Only admin can add staff
if ($user['role_name'] != 'admin') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Get campuses for dropdown
$campuses_sql = "SELECT campus_id, campus_name FROM campuses WHERE is_active = 1";
$campuses_result = $conn->query($campuses_sql);

// Get departments (common list)
$departments = ['Administration', 'Teaching - Nursery', 'Teaching - Primary', 'Teaching - Secondary', 
                'Finance', 'Medical', 'Library', 'IT', 'Maintenance', 'Transport', 'Security', 'Catering'];

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $middle_name = trim($_POST['middle_name'] ?? '');
    $gender = $_POST['gender'];
    $date_of_birth = $_POST['date_of_birth'];
    $national_id = trim($_POST['national_id'] ?? '');
    $tin_number = trim($_POST['tin_number'] ?? '');
    $nssf_number = trim($_POST['nssf_number'] ?? '');
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $hire_date = $_POST['hire_date'];
    $campus_id = (int)$_POST['campus_id'];
    $department = $_POST['department'];
    $position = trim($_POST['position']);
    $qualification = trim($_POST['qualification'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $years_experience = (int)($_POST['years_experience'] ?? 0);
    $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
    $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
    
    // Validate
    if (empty($first_name) || empty($last_name) || empty($gender) || empty($phone) || empty($hire_date) || $campus_id <= 0) {
        $error = "Please fill all required fields.";
    } else {
        // Check if email already exists
        if (!empty($email)) {
            $check_sql = "SELECT staff_id FROM staff WHERE email = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $email);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $error = "Email already exists in the system.";
            }
        }
        
        if (!$error) {
            // Generate staff number
            $staff_no = generateCode('STF', 'staff', 'staff_no');
            
            // Insert staff
            $sql = "INSERT INTO staff (staff_no, first_name, last_name, middle_name, gender, date_of_birth,
                    national_id, tin_number, nssf_number, phone, email, address, hire_date,
                    campus_id, department, position, qualification, specialization, years_experience,
                    emergency_contact_name, emergency_contact_phone, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssssssssssissssisss", 
                $staff_no, $first_name, $last_name, $middle_name, $gender, $date_of_birth,
                $national_id, $tin_number, $nssf_number, $phone, $email, $address, $hire_date,
                $campus_id, $department, $position, $qualification, $specialization, $years_experience,
                $emergency_contact_name, $emergency_contact_phone, $user['user_id']
            );
            
            if ($stmt->execute()) {
                $staff_id = $stmt->insert_id;
                
                // Create user account for staff
                $username = strtolower($first_name[0] . $last_name);
                $base_username = $username;
                $counter = 1;
                while (true) {
                    $check_user_sql = "SELECT user_id FROM users WHERE username = ?";
                    $check_user_stmt = $conn->prepare($check_user_sql);
                    $check_user_stmt->bind_param("s", $username);
                    $check_user_stmt->execute();
                    $check_user_result = $check_user_stmt->get_result();
                    if ($check_user_result->num_rows == 0) {
                        break;
                    }
                    $username = $base_username . $counter;
                    $counter++;
                }
                
                $default_password = password_hash('password123', PASSWORD_BCRYPT);
                $role_id = 2; // Teacher role by default
                
                $user_sql = "INSERT INTO users (username, password, email, phone, role_id, is_active) 
                             VALUES (?, ?, ?, ?, ?, 1)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("ssssi", $username, $default_password, $email, $phone, $role_id);
                $user_stmt->execute();
                $user_id = $user_stmt->insert_id;
                
                // Link staff to user
                $link_sql = "INSERT INTO staff_users (staff_id, user_id) VALUES (?, ?)";
                $link_stmt = $conn->prepare($link_sql);
                $link_stmt->bind_param("ii", $staff_id, $user_id);
                $link_stmt->execute();
                
                // Create initial employment record
                $contract_sql = "INSERT INTO employment_records (staff_id, contract_type, contract_start, 
                                job_title, department, is_current) 
                                VALUES (?, 'Contract', ?, ?, ?, 1)";
                $contract_stmt = $conn->prepare($contract_sql);
                $contract_stmt->bind_param("isss", $staff_id, $hire_date, $position, $department);
                $contract_stmt->execute();
                
                // Log activity
                logActivity($user['user_id'], 'CREATE', 'staff', $staff_id);
                
                $success = "Staff added successfully! Staff Number: " . $staff_no . "<br>
                            Username: $username<br>
                            Default Password: password123<br>
                            <small class='text-muted'>Please remind the staff to change their password on first login.</small>";
                
                // Clear form
                $_POST = array();
            } else {
                $error = "Failed to add staff: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff - <?php echo SITE_NAME; ?></title>
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
        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .form-card-header {
            background: white;
            border-bottom: 1px solid #eee;
            padding: 15px 20px;
            font-weight: 600;
        }
        .form-card-body {
            padding: 20px;
        }
        .required:after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-user-plus"></i> Add New Staff Member</h4>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Staff
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- Personal Information -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="fas fa-user-circle"></i> Personal Information
                </div>
                <div class="form-card-body">
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
                            <label class="form-label required">Gender</label>
                            <select name="gender" class="form-select" required>
                                <option value="">Select</option>
                                <option value="Male" <?php echo (($_POST['gender'] ?? '') == 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['gender'] ?? '') == 'Female') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label required">Date of Birth</label>
                            <input type="date" name="date_of_birth" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['date_of_birth'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">National ID</label>
                            <input type="text" name="national_id" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['national_id'] ?? ''); ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Years Experience</label>
                            <input type="number" name="years_experience" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['years_experience'] ?? 0); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Employment Information -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="fas fa-briefcase"></i> Employment Information
                </div>
                <div class="form-card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Hire Date</label>
                            <input type="date" name="hire_date" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['hire_date'] ?? date('Y-m-d')); ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Campus</label>
                            <select name="campus_id" class="form-select" required>
                                <option value="">Select Campus</option>
                                <?php while ($campus = $campuses_result->fetch_assoc()): ?>
                                    <option value="<?php echo $campus['campus_id']; ?>" 
                                        <?php echo (($_POST['campus_id'] ?? '') == $campus['campus_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($campus['campus_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label required">Department</label>
                            <select name="department" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept; ?>" 
                                        <?php echo (($_POST['department'] ?? '') == $dept) ? 'selected' : ''; ?>>
                                        <?php echo $dept; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Position/Job Title</label>
                            <input type="text" name="position" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['position'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qualification</label>
                            <input type="text" name="qualification" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['qualification'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Specialization</label>
                            <input type="text" name="specialization" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['specialization'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tax & NSSF Information -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="fas fa-file-invoice-dollar"></i> Tax & NSSF Information
                </div>
                <div class="form-card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">TIN Number (Tax ID)</label>
                            <input type="text" name="tin_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['tin_number'] ?? ''); ?>">
                            <small class="text-muted">For PAYE tax calculations</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">NSSF Number</label>
                            <input type="text" name="nssf_number" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['nssf_number'] ?? ''); ?>">
                            <small class="text-muted">For NSSF contributions</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Emergency Contact -->
            <div class="form-card">
                <div class="form-card-header">
                    <i class="fas fa-phone-alt"></i> Emergency Contact
                </div>
                <div class="form-card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_contact_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Emergency Contact Phone</label>
                            <input type="tel" name="emergency_contact_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($_POST['emergency_contact_phone'] ?? ''); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end mb-4">
                <button type="reset" class="btn btn-secondary me-2">
                    <i class="fas fa-undo"></i> Reset
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Staff Member
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>