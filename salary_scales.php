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

// Handle salary scale operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $scale_name = trim($_POST['scale_name']);
        $scale_code = trim($_POST['scale_code']);
        $basic_salary = (float)$_POST['basic_salary'];
        $housing_allowance = (float)$_POST['housing_allowance'];
        $transport_allowance = (float)$_POST['transport_allowance'];
        $medical_allowance = (float)$_POST['medical_allowance'];
        $other_allowances = (float)$_POST['other_allowances'];
        $effective_from = $_POST['effective_from'];
        
        $sql = "INSERT INTO salary_scales (scale_name, scale_code, basic_salary, housing_allowance, transport_allowance, 
                medical_allowance, other_allowances, effective_from) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdddddds", $scale_name, $scale_code, $basic_salary, $housing_allowance, 
                          $transport_allowance, $medical_allowance, $other_allowances, $effective_from);
        
        if ($stmt->execute()) {
            $success = "Salary scale added successfully!";
            logActivity($user['user_id'], 'CREATE', 'salary_scales', $stmt->insert_id);
        } else {
            $error = "Failed to add salary scale: " . $conn->error;
        }
    } elseif ($action == 'update') {
        $scale_id = (int)$_POST['scale_id'];
        $basic_salary = (float)$_POST['basic_salary'];
        $housing_allowance = (float)$_POST['housing_allowance'];
        $transport_allowance = (float)$_POST['transport_allowance'];
        $medical_allowance = (float)$_POST['medical_allowance'];
        $other_allowances = (float)$_POST['other_allowances'];
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        $sql = "UPDATE salary_scales SET basic_salary = ?, housing_allowance = ?, transport_allowance = ?, 
                medical_allowance = ?, other_allowances = ?, is_active = ? WHERE scale_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddddddi", $basic_salary, $housing_allowance, $transport_allowance, 
                          $medical_allowance, $other_allowances, $is_active, $scale_id);
        
        if ($stmt->execute()) {
            $success = "Salary scale updated successfully!";
            logActivity($user['user_id'], 'UPDATE', 'salary_scales', $scale_id);
        } else {
            $error = "Failed to update salary scale.";
        }
    }
}

// Get salary scales
$scales_sql = "SELECT * FROM salary_scales ORDER BY basic_salary ASC";
$scales_result = $conn->query($scales_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Scales - <?php echo SITE_NAME; ?></title>
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
        .scale-card {
            background: white;
            border-radius: 10px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            overflow: hidden;
        }
        .scale-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            font-weight: 600;
        }
        .scale-body {
            padding: 15px;
        }
        .total-salary {
            font-size: 24px;
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
            <h4><i class="fas fa-chart-line"></i> Salary Scales Configuration</h4>
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScaleModal">
                    <i class="fas fa-plus"></i> Add Salary Scale
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Salary Scales List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-table"></i> Salary Scales
            </div>
            <div class="card-body">
                <?php if ($scales_result->num_rows > 0): ?>
                    <div class="row">
                        <?php while ($scale = $scales_result->fetch_assoc()): 
                            $total = $scale['basic_salary'] + $scale['housing_allowance'] + $scale['transport_allowance'] + 
                                     $scale['medical_allowance'] + $scale['other_allowances'];
                        ?>
                            <div class="col-md-6">
                                <div class="scale-card">
                                    <div class="scale-header">
                                        <div class="d-flex justify-content-between">
                                            <span><?php echo htmlspecialchars($scale['scale_name']); ?> (<?php echo $scale['scale_code']; ?>)</span>
                                            <span class="badge bg-<?php echo $scale['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $scale['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">Effective from: <?php echo formatDate($scale['effective_from']); ?></small>
                                    </div>
                                    <div class="scale-body">
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Basic Salary:</span>
                                                    <span><?php echo formatMoney($scale['basic_salary']); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Housing Allowance:</span>
                                                    <span><?php echo formatMoney($scale['housing_allowance']); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Transport Allowance:</span>
                                                    <span><?php echo formatMoney($scale['transport_allowance']); ?></span>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Medical Allowance:</span>
                                                    <span><?php echo formatMoney($scale['medical_allowance']); ?></span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-1">
                                                    <span>Other Allowances:</span>
                                                    <span><?php echo formatMoney($scale['other_allowances']); ?></span>
                                                </div>
                                                <hr>
                                                <div class="d-flex justify-content-between fw-bold">
                                                    <span>Total Salary:</span>
                                                    <span class="total-salary"><?php echo formatMoney($total); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php if ($is_admin): ?>
                                            <div class="mt-3 text-end">
                                                <button class="btn btn-sm btn-warning" onclick="editScale(<?php echo htmlspecialchars(json_encode($scale)); ?>)">
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
                        <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                        <p>No salary scales configured yet.</p>
                        <?php if ($is_admin): ?>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScaleModal">
                                <i class="fas fa-plus"></i> Add First Scale
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tax Information Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-calculator"></i> PAYE Tax Rates (Uganda)
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Annual Taxable Income (UGX)</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td>0 - 2,820,000</td><td>0%</td></tr>
                            <tr><td>2,820,001 - 4,020,000</td><td>10%</td></tr>
                            <tr><td>4,020,001 - 4,920,000</td><td>20%</td></tr>
                            <tr><td>4,920,001 - 7,860,000</td><td>30%</td></tr>
                            <tr><td>Above 7,860,000</td><td>40%</td></tr>
                        </tbody>
                    </table>
                    <p class="text-muted mt-2"><small>NSSF Employee Contribution: 5% of gross salary | NSSF Employer Contribution: 10% of gross salary</small></p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Salary Scale Modal -->
    <div class="modal fade" id="addScaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Salary Scale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label class="form-label required">Scale Name</label>
                            <input type="text" name="scale_name" class="form-control" required>
                            <small class="text-muted">e.g., Scale A, Scale B, Management</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Scale Code</label>
                            <input type="text" name="scale_code" class="form-control" required>
                            <small class="text-muted">e.g., SCA, SCB, MGT</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Basic Salary (UGX)</label>
                            <input type="number" name="basic_salary" class="form-control" step="10000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Housing Allowance</label>
                            <input type="number" name="housing_allowance" class="form-control" step="10000" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transport Allowance</label>
                            <input type="number" name="transport_allowance" class="form-control" step="10000" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medical Allowance</label>
                            <input type="number" name="medical_allowance" class="form-control" step="10000" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Other Allowances</label>
                            <input type="number" name="other_allowances" class="form-control" step="10000" value="0">
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Effective From</label>
                            <input type="date" name="effective_from" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Scale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Salary Scale Modal -->
    <div class="modal fade" id="editScaleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Salary Scale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="" id="editScaleForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="scale_id" id="edit_scale_id">
                        <div class="mb-3">
                            <label class="form-label">Basic Salary (UGX)</label>
                            <input type="number" name="basic_salary" id="edit_basic_salary" class="form-control" step="10000" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Housing Allowance</label>
                            <input type="number" name="housing_allowance" id="edit_housing_allowance" class="form-control" step="10000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Transport Allowance</label>
                            <input type="number" name="transport_allowance" id="edit_transport_allowance" class="form-control" step="10000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Medical Allowance</label>
                            <input type="number" name="medical_allowance" id="edit_medical_allowance" class="form-control" step="10000">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Other Allowances</label>
                            <input type="number" name="other_allowances" id="edit_other_allowances" class="form-control" step="10000">
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
                        <button type="submit" class="btn btn-primary">Update Scale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editScale(scale) {
            document.getElementById('edit_scale_id').value = scale.scale_id;
            document.getElementById('edit_basic_salary').value = scale.basic_salary;
            document.getElementById('edit_housing_allowance').value = scale.housing_allowance;
            document.getElementById('edit_transport_allowance').value = scale.transport_allowance;
            document.getElementById('edit_medical_allowance').value = scale.medical_allowance;
            document.getElementById('edit_other_allowances').value = scale.other_allowances;
            document.getElementById('edit_is_active').checked = scale.is_active == 1;
            new bootstrap.Modal(document.getElementById('editScaleModal')).show();
        }
    </script>
</body>
</html>