<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$is_admin = ($user['role_name'] == 'admin');

if (!$is_admin) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$error = '';
$success = '';

// Handle grade scale updates
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    
    if ($action == 'update') {
        $grade_id = (int)$_POST['grade_id'];
        $min_percentage = (float)$_POST['min_percentage'];
        $max_percentage = (float)$_POST['max_percentage'];
        $grade_point = (int)$_POST['grade_point'];
        $description = trim($_POST['description']);
        
        $sql = "UPDATE grade_scales SET min_percentage = ?, max_percentage = ?, grade_point = ?, description = ? WHERE grade_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ddiss", $min_percentage, $max_percentage, $grade_point, $description, $grade_id);
        
        if ($stmt->execute()) {
            $success = "Grade scale updated successfully!";
            logActivity($user['user_id'], 'UPDATE', 'grade_scales', $grade_id);
        } else {
            $error = "Failed to update grade scale.";
        }
    } elseif ($action == 'add') {
        $grade = $_POST['grade'];
        $min_percentage = (float)$_POST['min_percentage'];
        $max_percentage = (float)$_POST['max_percentage'];
        $grade_point = (int)$_POST['grade_point'];
        $description = trim($_POST['description']);
        $class_level = $_POST['class_level'];
        
        $sql = "INSERT INTO grade_scales (grade, min_percentage, max_percentage, grade_point, description, class_level) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sddiss", $grade, $min_percentage, $max_percentage, $grade_point, $description, $class_level);
        
        if ($stmt->execute()) {
            $success = "Grade scale added successfully!";
            logActivity($user['user_id'], 'CREATE', 'grade_scales', $stmt->insert_id);
        } else {
            $error = "Failed to add grade scale.";
        }
    }
}

// Get grade scales
$primary_scales_sql = "SELECT * FROM grade_scales WHERE class_level = 'Primary' ORDER BY min_percentage DESC";
$secondary_scales_sql = "SELECT * FROM grade_scales WHERE class_level = 'Secondary' ORDER BY min_percentage DESC";
$primary_scales = $conn->query($primary_scales_sql);
$secondary_scales = $conn->query($secondary_scales_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Scales - <?php echo SITE_NAME; ?></title>
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
        .grade-table th {
            background: #f8f9fa;
        }
        .grade-badge {
            display: inline-block;
            width: 40px;
            height: 40px;
            line-height: 40px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
            font-size: 18px;
        }
        .grade-A { background: #d4edda; color: #155724; }
        .grade-B { background: #cce5ff; color: #004085; }
        .grade-C { background: #fff3cd; color: #856404; }
        .grade-D { background: #ffe5d0; color: #e67e22; }
        .grade-E { background: #f8d7da; color: #721c24; }
        .grade-F { background: #f5c6cb; color: #721c24; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-chart-line"></i> Grade Scales Configuration</h4>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addGradeModal">
                <i class="fas fa-plus"></i> Add Grade Scale
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Primary Section Grades -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-school"></i> Primary School Grade Scale
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered grade-table">
                        <thead>
                            <tr>
                                <th>Grade</th>
                                <th>Min %</th>
                                <th>Max %</th>
                                <th>Grade Point</th>
                                <th>Description</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($scale = $primary_scales->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="grade-badge grade-<?php echo $scale['grade']; ?>"><?php echo $scale['grade']; ?></span>
                                    </td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="grade_id" value="<?php echo $scale['grade_id']; ?>">
                                        <td><input type="number" name="min_percentage" class="form-control form-control-sm" value="<?php echo $scale['min_percentage']; ?>" step="0.5" style="width: 80px;"></td>
                                        <td><input type="number" name="max_percentage" class="form-control form-control-sm" value="<?php echo $scale['max_percentage']; ?>" step="0.5" style="width: 80px;"></td>
                                        <td><input type="number" name="grade_point" class="form-control form-control-sm" value="<?php echo $scale['grade_point']; ?>" style="width: 70px;"></td>
                                        <td><input type="text" name="description" class="form-control form-control-sm" value="<?php echo htmlspecialchars($scale['description']); ?>"></td>
                                        <td>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Secondary Section Grades -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-graduation-cap"></i> Secondary School Grade Scale
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered grade-table">
                        <thead>
                             oxymorph
                                <th>Grade</th>
                                <th>Min %</th>
                                <th>Max %</th>
                                <th>Grade Point</th>
                                <th>Description</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($scale = $secondary_scales->fetch_assoc()): ?>
                                <tr>
                                    <td class="text-center">
                                        <span class="grade-badge grade-<?php echo $scale['grade']; ?>"><?php echo $scale['grade']; ?></span>
                                    </td>
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="grade_id" value="<?php echo $scale['grade_id']; ?>">
                                        <td><input type="number" name="min_percentage" class="form-control form-control-sm" value="<?php echo $scale['min_percentage']; ?>" step="0.5" style="width: 80px;"></td>
                                        <td><input type="number" name="max_percentage" class="form-control form-control-sm" value="<?php echo $scale['max_percentage']; ?>" step="0.5" style="width: 80px;"></td>
                                        <td><input type="number" name="grade_point" class="form-control form-control-sm" value="<?php echo $scale['grade_point']; ?>" style="width: 70px;"></td>
                                        <td><input type="text" name="description" class="form-control form-control-sm" value="<?php echo htmlspecialchars($scale['description']); ?>"></td>
                                        <td>
                                            <button type="submit" class="btn btn-sm btn-primary">
                                                <i class="fas fa-save"></i> Save
                                            </button>
                                        </td>
                                    </form>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Grade Modal -->
    <div class="modal fade" id="addGradeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add Grade Scale</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label required">Class Level</label>
                            <select name="class_level" class="form-select" required>
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Grade Letter</label>
                            <input type="text" name="grade" class="form-control" maxlength="2" required>
                            <small class="text-muted">e.g., A, B+, C-</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Min Percentage</label>
                                <input type="number" name="min_percentage" class="form-control" step="0.5" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Max Percentage</label>
                                <input type="number" name="max_percentage" class="form-control" step="0.5" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Grade Point</label>
                                <input type="number" name="grade_point" class="form-control" required>
                                <small class="text-muted">1-6 scale (1 = highest)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" class="form-control">
                                <small class="text-muted">e.g., Excellent, Very Good</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Grade</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>