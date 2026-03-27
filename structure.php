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

$error = '';
$success = '';

// Get classes for dropdown
$classes_sql = "SELECT class_id, class_name, class_level FROM classes WHERE is_active = 1 ORDER BY class_level, class_name";
$classes_result = $conn->query($classes_sql);

// Get campuses
$campuses_sql = "SELECT campus_id, campus_name FROM campuses WHERE is_active = 1";
$campuses_result = $conn->query($campuses_sql);

// Handle fee structure operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $class_id = (int)$_POST['class_id'];
        $campus_id = (int)$_POST['campus_id'];
        $term = (int)$_POST['term'];
        $academic_year = $_POST['academic_year'];
        $tuition_fee = (float)$_POST['tuition_fee'];
        $development_fee = (float)$_POST['development_fee'];
        $library_fee = (float)$_POST['library_fee'];
        $sports_fee = (float)$_POST['sports_fee'];
        $medical_fee = (float)$_POST['medical_fee'];
        $boarding_fee = (float)$_POST['boarding_fee'];
        $uniform_fee = (float)$_POST['uniform_fee'];
        $other_fees = (float)$_POST['other_fees'];
        $payment_deadline = $_POST['payment_deadline'];
        
        // Check if fee structure already exists
        $check_sql = "SELECT fee_id FROM fee_structures WHERE class_id = ? AND campus_id = ? AND term = ? AND academic_year = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iiis", $class_id, $campus_id, $term, $academic_year);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error = "Fee structure already exists for this class, campus, term, and academic year.";
        } else {
            $sql = "INSERT INTO fee_structures (class_id, campus_id, term, academic_year, tuition_fee, development_fee, 
                    library_fee, sports_fee, medical_fee, boarding_fee, uniform_fee, other_fees, payment_deadline, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iiisddddddddds", $class_id, $campus_id, $term, $academic_year, $tuition_fee, $development_fee,
                              $library_fee, $sports_fee, $medical_fee, $boarding_fee, $uniform_fee, $other_fees, $payment_deadline, $user['user_id']);
            
            if ($stmt->execute()) {
                $success = "Fee structure added successfully!";
                logActivity($user['user_id'], 'CREATE', 'fee_structures', $stmt->insert_id);
            } else {
                $error = "Failed to add fee structure: " . $conn->error;
            }
        }
    } elseif ($action == 'update') {
        $fee_id = (int)$_POST['fee_id'];
        $tuition_fee = (float)$_POST['tuition_fee'];
        $development_fee = (float)$_POST['development_fee'];
        $library_fee = (float)$_POST['library_fee'];
        $sports_fee = (float)$_POST['sports_fee'];
        $medical_fee = (float)$_POST['medical_fee'];
        $boarding_fee = (float)$_POST['boarding_fee'];
        $uniform_fee = (float)$_POST['uniform_fee'];
        $other_fees = (float)$_POST['other_fees'];
        $payment_deadline = $_POST['payment_deadline'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "UPDATE fee_structures SET tuition_fee = ?, development_fee = ?, library_fee = ?, sports_fee = ?, 
                medical_fee = ?, boarding_fee = ?, uniform_fee = ?, other_fees = ?, payment_deadline = ?, is_active = ? 
                WHERE fee_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("dddddddddii", $tuition_fee, $development_fee, $library_fee, $sports_fee, 
                          $medical_fee, $boarding_fee, $uniform_fee, $other_fees, $payment_deadline, $is_active, $fee_id);
        
        if ($stmt->execute()) {
            $success = "Fee structure updated successfully!";
            logActivity($user['user_id'], 'UPDATE', 'fee_structures', $fee_id);
        } else {
            $error = "Failed to update fee structure.";
        }
    }
}

// Get existing fee structures
$structures_sql = "SELECT fs.*, c.class_name, cp.campus_name 
                   FROM fee_structures fs
                   JOIN classes c ON fs.class_id = c.class_id
                   JOIN campuses cp ON fs.campus_id = cp.campus_id
                   ORDER BY fs.academic_year DESC, fs.term DESC, c.class_name";
$structures_result = $conn->query($structures_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure - <?php echo SITE_NAME; ?></title>
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
        .fee-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            overflow: hidden;
        }
        .fee-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .fee-body {
            padding: 15px;
        }
        .fee-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .total-fee {
            font-size: 20px;
            font-weight: bold;
            color: #27ae60;
        }
        .modal-header {
            background: #2c3e50;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-money-bill-wave"></i> Fee Structure Configuration</h4>
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                    <i class="fas fa-plus"></i> Add Fee Structure
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Fee Structures List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Current Fee Structures
            </div>
            <div class="card-body">
                <?php if ($structures_result->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($structure = $structures_result->fetch_assoc()): 
                            $total = $structure['tuition_fee'] + $structure['development_fee'] + $structure['library_fee'] + 
                                     $structure['sports_fee'] + $structure['medical_fee'] + $structure['boarding_fee'] + 
                                     $structure['uniform_fee'] + $structure['other_fees'];
                        ?>
                            <div class="col-md-6">
                                <div class="fee-card">
                                    <div class="fee-header">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($structure['class_name']); ?> - <?php echo $structure['campus_name']; ?></span>
                                            <span class="badge bg-<?php echo $structure['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $structure['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted"><?php echo $structure['academic_year']; ?> - Term <?php echo $structure['term']; ?></small>
                                    </div>
                                    <div class="fee-body">
                                        <div class="fee-row">
                                            <span>Tuition Fee:</span>
                                            <span><?php echo formatMoney($structure['tuition_fee']); ?></span>
                                        </div>
                                        <div class="fee-row">
                                            <span>Development Fee:</span>
                                            <span><?php echo formatMoney($structure['development_fee']); ?></span>
                                        </div>
                                        <div class="fee-row">
                                            <span>Library Fee:</span>
                                            <span><?php echo formatMoney($structure['library_fee']); ?></span>
                                        </div>
                                        <div class="fee-row">
                                            <span>Sports Fee:</span>
                                            <span><?php echo formatMoney($structure['sports_fee']); ?></span>
                                        </div>
                                        <div class="fee-row">
                                            <span>Medical Fee:</span>
                                            <span><?php echo formatMoney($structure['medical_fee']); ?></span>
                                        </div>
                                        <?php if ($structure['boarding_fee'] > 0): ?>
                                            <div class="fee-row">
                                                <span>Boarding Fee:</span>
                                                <span><?php echo formatMoney($structure['boarding_fee']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="fee-row">
                                            <span>Uniform Fee:</span>
                                            <span><?php echo formatMoney($structure['uniform_fee']); ?></span>
                                        </div>
                                        <div class="fee-row">
                                            <span>Other Fees:</span>
                                            <span><?php echo formatMoney($structure['other_fees']); ?></span>
                                        </div>
                                        <hr>
                                        <div class="fee-row">
                                            <strong>Total Fee:</strong>
                                            <strong class="total-fee"><?php echo formatMoney($total); ?></strong>
                                        </div>
                                        <div class="fee-row mt-2">
                                            <span>Payment Deadline:</span>
                                            <span><?php echo formatDate($structure['payment_deadline']); ?></span>
                                        </div>
                                        <?php if ($is_admin): ?>
                                            <div class="mt-3 text-end">
                                                <button class="btn btn-sm btn-warning" onclick="editFee(<?php echo htmlspecialchars(json_encode($structure)); ?>)">
                                                    <i class="fas fa-edit"></i> Edit
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                        <p>No fee structures configured yet.</p>
                        <?php if ($is_admin): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addFeeModal">
                                <i class="fas fa-plus"></i> Add First Fee Structure
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Fee Structure Modal -->
    <div class="modal fade" id="addFeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Fee Structure</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Class</label>
                                <select name="class_id" class="form-select" required>
                                    <option value="">Select Class</option>
                                    <?php 
                                    $classes_result2 = $conn->query($classes_sql);
                                    while ($class = $classes_result2->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $class['class_id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Campus</label>
                                <select name="campus_id" class="form-select" required>
                                    <option value="">Select Campus</option>
                                    <?php 
                                    $campuses_result2 = $conn->query($campuses_sql);
                                    while ($campus = $campuses_result2->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $campus['campus_id']; ?>">
                                            <?php echo htmlspecialchars($campus['campus_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Academic Year</label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo CURRENT_ACADEMIC_YEAR; ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Term</label>
                                <select name="term" class="form-select" required>
                                    <option value="1">Term 1</option>
                                    <option value="2">Term 2</option>
                                    <option value="3">Term 3</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tuition Fee</label>
                                <input type="number" name="tuition_fee" class="form-control" step="1000" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Development Fee</label>
                                <input type="number" name="development_fee" class="form-control" step="1000" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Library Fee</label>
                                <input type="number" name="library_fee" class="form-control" step="1000" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sports Fee</label>
                                <input type="number" name="sports_fee" class="form-control" step="1000" value="0">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Medical Fee</label>
                                <input type="number" name="medical_fee" class="form-control" step="1000" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Boarding Fee</label>
                                <input type="number" name="boarding_fee" class="form-control" step="1000" value="0">
                                <small class="text-muted">For boarding students only</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Uniform Fee</label>
                                <input type="number" name="uniform_fee" class="form-control" step="1000" value="0">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Other Fees</label>
                                <input type="number" name="other_fees" class="form-control" step="1000" value="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Deadline</label>
                                <input type="date" name="payment_deadline" class="form-control" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Fee Structure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Fee Structure Modal -->
    <div class="modal fade" id="editFeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Fee Structure</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editFeeForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="fee_id" id="edit_fee_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tuition Fee</label>
                                <input type="number" name="tuition_fee" id="edit_tuition_fee" class="form-control" step="1000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Development Fee</label>
                                <input type="number" name="development_fee" id="edit_development_fee" class="form-control" step="1000">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Library Fee</label>
                                <input type="number" name="library_fee" id="edit_library_fee" class="form-control" step="1000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Sports Fee</label>
                                <input type="number" name="sports_fee" id="edit_sports_fee" class="form-control" step="1000">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Medical Fee</label>
                                <input type="number" name="medical_fee" id="edit_medical_fee" class="form-control" step="1000">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Boarding Fee</label>
                                <input type="number" name="boarding_fee" id="edit_boarding_fee" class="form-control" step="1000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Uniform Fee</label>
                                <input type="number" name="uniform_fee" id="edit_uniform_fee" class="form-control" step="1000">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Other Fees</label>
                                <input type="number" name="other_fees" id="edit_other_fees" class="form-control" step="1000">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Payment Deadline</label>
                                <input type="date" name="payment_deadline" id="edit_payment_deadline" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" id="edit_is_active" class="form-check-input" value="1">
                                <label class="form-check-label">Active</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Fee Structure</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editFee(structure) {
            document.getElementById('edit_fee_id').value = structure.fee_id;
            document.getElementById('edit_tuition_fee').value = structure.tuition_fee;
            document.getElementById('edit_development_fee').value = structure.development_fee;
            document.getElementById('edit_library_fee').value = structure.library_fee;
            document.getElementById('edit_sports_fee').value = structure.sports_fee;
            document.getElementById('edit_medical_fee').value = structure.medical_fee;
            document.getElementById('edit_boarding_fee').value = structure.boarding_fee;
            document.getElementById('edit_uniform_fee').value = structure.uniform_fee;
            document.getElementById('edit_other_fees').value = structure.other_fees;
            document.getElementById('edit_payment_deadline').value = structure.payment_deadline;
            document.getElementById('edit_is_active').checked = structure.is_active == 1;
            new bootstrap.Modal(document.getElementById('editFeeModal')).show();
        }
    </script>
</body>
</html>