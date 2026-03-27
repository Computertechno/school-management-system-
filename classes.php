<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Admin can manage all, teachers can view their classes
$is_admin = ($user['role_name'] == 'admin');
$is_teacher = ($user['role_name'] == 'teacher');

if (!$is_admin && !$is_teacher) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Get campuses for filter
$campuses_sql = "SELECT campus_id, campus_name FROM campuses WHERE is_active = 1";
$campuses_result = $conn->query($campuses_sql);

// Get level filter
$level_filter = isset($_GET['level']) ? $_GET['level'] : '';
$campus_filter = isset($_GET['campus']) ? (int)$_GET['campus'] : 0;

// Build WHERE clause
$where = "WHERE c.is_active = 1";
$params = [];
$types = "";

if (!empty($level_filter)) {
    $where .= " AND c.class_level = ?";
    $params[] = $level_filter;
    $types .= "s";
}

if ($campus_filter > 0) {
    $where .= " AND c.campus_id = ?";
    $params[] = $campus_filter;
    $types .= "i";
}

// Get classes
$sql = "SELECT c.*, cp.campus_name, 
        CONCAT(s.first_name, ' ', s.last_name) as teacher_name,
        (SELECT COUNT(*) FROM students WHERE current_class_id = c.class_id AND enrollment_status = 'Active') as student_count
        FROM classes c
        LEFT JOIN campuses cp ON c.campus_id = cp.campus_id
        LEFT JOIN staff s ON c.class_teacher_id = s.staff_id
        $where
        ORDER BY c.class_level, c.class_name";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$classes_result = $stmt->get_result();

// Get all teachers for dropdown
$teachers_sql = "SELECT staff_id, first_name, last_name FROM staff WHERE is_active = 1 AND department LIKE '%Teaching%' ORDER BY last_name";
$teachers_result = $conn->query($teachers_sql);

$error = '';
$success = '';

// Handle form submission for adding/editing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    $action = $_POST['action'];
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $class_name = trim($_POST['class_name']);
    $class_code = trim($_POST['class_code']);
    $class_level = $_POST['class_level'];
    $campus_id = (int)$_POST['campus_id'];
    $class_teacher_id = (int)$_POST['class_teacher_id'];
    $capacity = (int)$_POST['capacity'];
    
    if (empty($class_name) || empty($class_code) || empty($class_level) || $campus_id <= 0) {
        $error = "Please fill all required fields.";
    } else {
        if ($action == 'add') {
            $sql = "INSERT INTO classes (class_name, class_code, class_level, campus_id, class_teacher_id, capacity) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiii", $class_name, $class_code, $class_level, $campus_id, $class_teacher_id, $capacity);
            
            if ($stmt->execute()) {
                $success = "Class added successfully!";
                logActivity($user['user_id'], 'CREATE', 'classes', $stmt->insert_id);
            } else {
                $error = "Failed to add class: " . $conn->error;
            }
        } elseif ($action == 'edit' && $class_id > 0) {
            $sql = "UPDATE classes SET class_name = ?, class_code = ?, class_level = ?, campus_id = ?, 
                    class_teacher_id = ?, capacity = ? WHERE class_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssiiii", $class_name, $class_code, $class_level, $campus_id, $class_teacher_id, $capacity, $class_id);
            
            if ($stmt->execute()) {
                $success = "Class updated successfully!";
                logActivity($user['user_id'], 'UPDATE', 'classes', $class_id);
            } else {
                $error = "Failed to update class: " . $conn->error;
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
    <title>Class Management - <?php echo SITE_NAME; ?></title>
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
        .class-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
            border-left: 4px solid #3498db;
        }
        .class-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .class-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .class-stats {
            font-size: 12px;
            color: #7f8c8d;
        }
        .level-badge {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 500;
        }
        .level-Nursery { background: #e8f5e9; color: #2e7d32; }
        .level-Primary { background: #e3f2fd; color: #1565c0; }
        .level-Secondary { background: #fff3e0; color: #ef6c00; }
        .search-filters {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
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
            <h4><i class="fas fa-chalkboard"></i> Class Management</h4>
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                    <i class="fas fa-plus"></i> Add New Class
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="search-filters">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <select name="level" class="form-select" onchange="this.form.submit()">
                        <option value="">All Levels</option>
                        <option value="Nursery" <?php echo $level_filter == 'Nursery' ? 'selected' : ''; ?>>Nursery</option>
                        <option value="Primary" <?php echo $level_filter == 'Primary' ? 'selected' : ''; ?>>Primary</option>
                        <option value="Secondary" <?php echo $level_filter == 'Secondary' ? 'selected' : ''; ?>>Secondary</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <select name="campus" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Campuses</option>
                        <?php while ($campus = $campuses_result->fetch_assoc()): ?>
                            <option value="<?php echo $campus['campus_id']; ?>" 
                                <?php echo ($campus_filter == $campus['campus_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($campus['campus_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <?php if ($level_filter || $campus_filter): ?>
                        <a href="classes.php" class="btn btn-secondary w-100">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Classes Grid -->
        <div class="row">
            <?php if ($classes_result->num_rows > 0): ?>
                <?php while ($class = $classes_result->fetch_assoc()): ?>
                    <div class="col-md-4">
                        <div class="class-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                                    <div class="class-stats">
                                        <i class="fas fa-code"></i> <?php echo $class['class_code']; ?>
                                        <span class="mx-1">|</span>
                                        <i class="fas fa-map-marker-alt"></i> <?php echo $class['campus_name']; ?>
                                    </div>
                                </div>
                                <span class="level-badge level-<?php echo $class['class_level']; ?>">
                                    <?php echo $class['class_level']; ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $class['student_count']; ?></div>
                                        <small class="text-muted">Students</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $class['capacity']; ?></div>
                                        <small class="text-muted">Capacity</small>
                                    </div>
                                    <div class="col-4">
                                        <div class="fw-bold"><?php echo $class['teacher_name'] ?: 'Not Assigned'; ?></div>
                                        <small class="text-muted">Class Teacher</small>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo ($class['student_count'] / $class['capacity']) * 100; ?>%"></div>
                                </div>
                            </div>
                            <div class="mt-3 d-flex justify-content-between">
                                <a href="class_view.php?id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="subjects.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-book"></i> Subjects
                                </a>
                                <?php if ($is_admin): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editClass(<?php echo $class['class_id']; ?>, '<?php echo addslashes($class['class_name']); ?>', '<?php echo $class['class_code']; ?>', '<?php echo $class['class_level']; ?>', <?php echo $class['campus_id']; ?>, <?php echo $class['class_teacher_id'] ?? 0; ?>, <?php echo $class['capacity']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                            <p>No classes found.</p>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addClassModal">
                                    <i class="fas fa-plus"></i> Add First Class
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add/Edit Class Modal -->
    <div class="modal fade" id="addClassModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Class</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="form_action" value="add">
                        <input type="hidden" name="class_id" id="class_id" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label required">Class Name</label>
                            <input type="text" name="class_name" id="class_name" class="form-control" required>
                            <small class="text-muted">e.g., Nursery 1, P.1 A, S.1</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Class Code</label>
                            <input type="text" name="class_code" id="class_code" class="form-control" required>
                            <small class="text-muted">e.g., N1, P1A, S1</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Class Level</label>
                            <select name="class_level" id="class_level" class="form-select" required>
                                <option value="">Select Level</option>
                                <option value="Nursery">Nursery</option>
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Campus</label>
                            <select name="campus_id" id="campus_id" class="form-select" required>
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
                        <div class="mb-3">
                            <label class="form-label">Class Teacher</label>
                            <select name="class_teacher_id" id="class_teacher_id" class="form-select">
                                <option value="0">Not Assigned</option>
                                <?php 
                                $teachers_result2 = $conn->query($teachers_sql);
                                while ($teacher = $teachers_result2->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $teacher['staff_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Capacity</label>
                            <input type="number" name="capacity" id="capacity" class="form-control" value="50" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Class</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editClass(id, name, code, level, campusId, teacherId, capacity) {
            document.getElementById('form_action').value = 'edit';
            document.getElementById('class_id').value = id;
            document.getElementById('class_name').value = name;
            document.getElementById('class_code').value = code;
            document.getElementById('class_level').value = level;
            document.getElementById('campus_id').value = campusId;
            document.getElementById('class_teacher_id').value = teacherId;
            document.getElementById('capacity').value = capacity;
            
            document.querySelector('#addClassModal .modal-title').innerHTML = '<i class="fas fa-edit"></i> Edit Class';
            new bootstrap.Modal(document.getElementById('addClassModal')).show();
        }
        
        // Reset modal on close
        document.getElementById('addClassModal').addEventListener('hidden.bs.modal', function () {
            document.getElementById('form_action').value = 'add';
            document.getElementById('class_id').value = 0;
            document.getElementById('class_name').value = '';
            document.getElementById('class_code').value = '';
            document.getElementById('class_level').value = '';
            document.getElementById('campus_id').value = '';
            document.getElementById('class_teacher_id').value = '0';
            document.getElementById('capacity').value = '50';
            document.querySelector('#addClassModal .modal-title').innerHTML = '<i class="fas fa-plus-circle"></i> Add New Class';
        });
    </script>
</body>
</html>