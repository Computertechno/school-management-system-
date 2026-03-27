<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

$is_admin = ($user['role_name'] == 'admin');
$is_teacher = ($user['role_name'] == 'teacher');

// Get class_id if provided
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$class_name = $class_id > 0 ? getClassName($class_id) : '';

// Get all classes for filter
$classes_sql = "SELECT class_id, class_name, class_level FROM classes WHERE is_active = 1 ORDER BY class_level, class_name";
$classes_result = $conn->query($classes_sql);

// Get all teachers for dropdown
$teachers_sql = "SELECT staff_id, first_name, last_name, department FROM staff WHERE is_active = 1 ORDER BY last_name";
$teachers_result = $conn->query($teachers_sql);

$error = '';
$success = '';

// Handle subject assignment
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    $action = $_POST['action'];
    $class_subject_id = isset($_POST['class_subject_id']) ? (int)$_POST['class_subject_id'] : 0;
    $subject_name = trim($_POST['subject_name']);
    $subject_code = trim($_POST['subject_code']);
    $subject_type = $_POST['subject_type'];
    $class_level = $_POST['class_level'];
    $assigned_class_id = (int)$_POST['class_id'];
    $teacher_id = (int)$_POST['teacher_id'];
    $academic_year = CURRENT_ACADEMIC_YEAR;
    $term = CURRENT_TERM;
    
    if (empty($subject_name) || empty($subject_code) || $assigned_class_id <= 0) {
        $error = "Please fill all required fields.";
    } else {
        if ($action == 'add') {
            // First, check if subject exists
            $check_sql = "SELECT subject_id FROM subjects WHERE subject_code = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $subject_code);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $subject = $check_result->fetch_assoc();
                $subject_id = $subject['subject_id'];
            } else {
                // Create new subject
                $subject_sql = "INSERT INTO subjects (subject_name, subject_code, subject_type, class_level) VALUES (?, ?, ?, ?)";
                $subject_stmt = $conn->prepare($subject_sql);
                $subject_stmt->bind_param("ssss", $subject_name, $subject_code, $subject_type, $class_level);
                $subject_stmt->execute();
                $subject_id = $subject_stmt->insert_id;
            }
            
            // Assign to class
            $assign_sql = "INSERT INTO class_subjects (class_id, subject_id, teacher_id, academic_year, term) 
                           VALUES (?, ?, ?, ?, ?)";
            $assign_stmt = $conn->prepare($assign_sql);
            $assign_stmt->bind_param("iiiss", $assigned_class_id, $subject_id, $teacher_id, $academic_year, $term);
            
            if ($assign_stmt->execute()) {
                $success = "Subject assigned to class successfully!";
                logActivity($user['user_id'], 'ASSIGN_SUBJECT', 'class_subjects', $assign_stmt->insert_id);
            } else {
                $error = "Failed to assign subject: " . $conn->error;
            }
        } elseif ($action == 'remove' && $class_subject_id > 0) {
            $delete_sql = "DELETE FROM class_subjects WHERE class_subject_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $class_subject_id);
            
            if ($delete_stmt->execute()) {
                $success = "Subject removed from class successfully!";
                logActivity($user['user_id'], 'REMOVE_SUBJECT', 'class_subjects', $class_subject_id);
            } else {
                $error = "Failed to remove subject.";
            }
        }
    }
}

// Get subjects for selected class
$subjects = [];
if ($class_id > 0) {
    $subjects_sql = "SELECT cs.class_subject_id, s.subject_id, s.subject_name, s.subject_code, s.subject_type,
                     CONCAT(st.first_name, ' ', st.last_name) as teacher_name, st.staff_id as teacher_id,
                     cs.academic_year, cs.term
                     FROM class_subjects cs
                     JOIN subjects s ON cs.subject_id = s.subject_id
                     LEFT JOIN staff st ON cs.teacher_id = st.staff_id
                     WHERE cs.class_id = ? AND cs.academic_year = ? AND cs.term = ?
                     ORDER BY s.subject_name";
    $subjects_stmt = $conn->prepare($subjects_sql);
    $subjects_stmt->bind_param("isi", $class_id, CURRENT_ACADEMIC_YEAR, CURRENT_TERM);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    $subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - <?php echo SITE_NAME; ?></title>
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
        .subject-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .subject-name {
            font-weight: 600;
            color: #2c3e50;
        }
        .subject-code {
            font-size: 12px;
            color: #7f8c8d;
            font-family: monospace;
        }
        .class-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-book"></i> Subject Management</h4>
            <?php if ($is_admin && $class_id > 0): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                    <i class="fas fa-plus"></i> Assign Subject
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Class Selector -->
        <div class="class-selector">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Select Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Select a Class --</option>
                        <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?> (<?php echo $class['class_level']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <?php if ($class_id > 0): ?>
                    <div class="col-md-6">
                        <label>&nbsp;</label>
                        <div>
                            <span class="badge bg-info p-2">
                                <i class="fas fa-calendar-alt"></i> <?php echo CURRENT_ACADEMIC_YEAR; ?> - Term <?php echo CURRENT_TERM; ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($class_id > 0): ?>
            <!-- Subjects List -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Subjects for <?php echo htmlspecialchars($class_name); ?>
                    <span class="badge bg-secondary float-end"><?php echo count($subjects); ?> subjects</span>
                </div>
                <div class="card-body">
                    <?php if (count($subjects) > 0): ?>
                        <?php foreach ($subjects as $subject): ?>
                            <div class="subject-item">
                                <div>
                                    <div class="subject-name">
                                        <?php echo htmlspecialchars($subject['subject_name']); ?>
                                        <span class="subject-code ms-2">(<?php echo $subject['subject_code']; ?>)</span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-chalkboard-user"></i> Teacher: <?php echo $subject['teacher_name'] ?: 'Not Assigned'; ?>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-tag"></i> Type: <?php echo $subject['subject_type']; ?>
                                    </div>
                                </div>
                                <?php if ($is_admin): ?>
                                    <button class="btn btn-sm btn-danger" onclick="removeSubject(<?php echo $subject['class_subject_id']; ?>)">
                                        <i class="fas fa-trash"></i> Remove
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <p>No subjects assigned to this class yet.</p>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                                    <i class="fas fa-plus"></i> Assign First Subject
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p>Select a class to view and manage subjects.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Add Subject Modal -->
    <div class="modal fade" id="addSubjectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Assign Subject to <?php echo htmlspecialchars($class_name); ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label required">Subject Name</label>
                            <input type="text" name="subject_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Subject Code</label>
                            <input type="text" name="subject_code" class="form-control" required>
                            <small class="text-muted">e.g., MATH, ENG, SCI</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Subject Type</label>
                            <select name="subject_type" class="form-select" required>
                                <option value="Core">Core</option>
                                <option value="Elective">Elective</option>
                                <option value="Co-curricular">Co-curricular</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Class Level</label>
                            <select name="class_level" class="form-select" required>
                                <option value="Nursery">Nursery</option>
                                <option value="Primary">Primary</option>
                                <option value="Secondary">Secondary</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Assigned Teacher</label>
                            <select name="teacher_id" class="form-select">
                                <option value="0">Not Assigned</option>
                                <?php 
                                $teachers_result2 = $conn->query($teachers_sql);
                                while ($teacher = $teachers_result2->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $teacher['staff_id']; ?>">
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                        (<?php echo $teacher['department']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Assign Subject</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function removeSubject(classSubjectId) {
            Swal.fire({
                title: 'Remove Subject?',
                text: "Are you sure you want to remove this subject from the class?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, remove it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    let actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'remove';
                    let idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'class_subject_id';
                    idInput.value = classSubjectId;
                    form.appendChild(actionInput);
                    form.appendChild(idInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    </script>
</body>
</html>