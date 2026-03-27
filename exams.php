<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_teacher = ($user['role_name'] == 'teacher');

$error = '';
$success = '';

// Handle exam CRUD operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $is_admin) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $exam_name = trim($_POST['exam_name']);
        $exam_type = $_POST['exam_type'];
        $term = (int)$_POST['term'];
        $academic_year = $_POST['academic_year'];
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $max_marks = (int)$_POST['max_marks'];
        $passing_marks = (int)$_POST['passing_marks'];
        
        if (empty($exam_name) || empty($start_date) || empty($end_date)) {
            $error = "Please fill all required fields.";
        } else {
            $sql = "INSERT INTO exams (exam_name, exam_type, term, academic_year, start_date, end_date, max_marks, passing_marks, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssisssiii", $exam_name, $exam_type, $term, $academic_year, $start_date, $end_date, $max_marks, $passing_marks, $user['user_id']);
            
            if ($stmt->execute()) {
                $success = "Exam created successfully!";
                logActivity($user['user_id'], 'CREATE', 'exams', $stmt->insert_id);
            } else {
                $error = "Failed to create exam: " . $conn->error;
            }
        }
    } elseif ($action == 'delete') {
        $exam_id = (int)$_POST['exam_id'];
        $sql = "DELETE FROM exams WHERE exam_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $exam_id);
        
        if ($stmt->execute()) {
            $success = "Exam deleted successfully!";
            logActivity($user['user_id'], 'DELETE', 'exams', $exam_id);
        } else {
            $error = "Failed to delete exam.";
        }
    }
}

// Get all exams
$exams_sql = "SELECT e.*, 
              (SELECT COUNT(*) FROM results WHERE exam_id = e.exam_id) as results_count
              FROM exams e 
              ORDER BY e.academic_year DESC, e.term DESC, e.start_date DESC";
$exams_result = $conn->query($exams_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Management - <?php echo SITE_NAME; ?></title>
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
        .exam-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #3498db;
            transition: transform 0.2s;
        }
        .exam-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .exam-name {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .status-upcoming { background: #fff3cd; color: #856404; }
        .status-ongoing { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }
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
            <h4><i class="fas fa-file-alt"></i> Exam Management</h4>
            <?php if ($is_admin): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                    <i class="fas fa-plus"></i> Create Exam
                </button>
            <?php endif; ?>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Exams Grid -->
        <div class="row">
            <?php if ($exams_result->num_rows > 0): ?>
                <?php while ($exam = $exams_result->fetch_assoc()): 
                    $today = date('Y-m-d');
                    $status = '';
                    if ($exam['start_date'] > $today) {
                        $status = 'Upcoming';
                    } elseif ($exam['end_date'] < $today) {
                        $status = 'Completed';
                    } else {
                        $status = 'Ongoing';
                    }
                ?>
                    <div class="col-md-6">
                        <div class="exam-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="exam-name"><?php echo htmlspecialchars($exam['exam_name']); ?></div>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-calendar-alt"></i> <?php echo formatDate($exam['start_date']); ?> - <?php echo formatDate($exam['end_date']); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <i class="fas fa-graduation-cap"></i> <?php echo $exam['academic_year']; ?> - Term <?php echo $exam['term']; ?>
                                        <span class="mx-2">|</span>
                                        <i class="fas fa-star"></i> Max: <?php echo $exam['max_marks']; ?> | Pass: <?php echo $exam['passing_marks']; ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge status-<?php echo strtolower($status); ?>"><?php echo $status; ?></span>
                                </div>
                            </div>
                            <div class="mt-3 d-flex justify-content-between">
                                <a href="grade_entry.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i> Enter Grades
                                </a>
                                <a href="exam_results.php?exam_id=<?php echo $exam['exam_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-chart-line"></i> View Results
                                </a>
                                <?php if ($is_admin): ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteExam(<?php echo $exam['exam_id']; ?>, '<?php echo addslashes($exam['exam_name']); ?>')">
                                        <i class="fas fa-trash"></i> Delete
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
                            <i class="fas fa-file-alt fa-3x text-muted mb-3"></i>
                            <p>No exams created yet.</p>
                            <?php if ($is_admin): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                                    <i class="fas fa-plus"></i> Create First Exam
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Exam Modal -->
    <div class="modal fade" id="addExamModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Create New Exam</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label required">Exam Name</label>
                            <input type="text" name="exam_name" class="form-control" required>
                            <small class="text-muted">e.g., End of Term 1, Mid Term 2, Mock Exam</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Exam Type</label>
                            <select name="exam_type" class="form-select" required>
                                <option value="Mid Term">Mid Term</option>
                                <option value="End of Term">End of Term</option>
                                <option value="Mock">Mock</option>
                                <option value="Continuous Assessment">Continuous Assessment</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Term</label>
                                <select name="term" class="form-select" required>
                                    <option value="1">Term 1</option>
                                    <option value="2">Term 2</option>
                                    <option value="3">Term 3</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Academic Year</label>
                                <input type="text" name="academic_year" class="form-control" value="<?php echo CURRENT_ACADEMIC_YEAR; ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Maximum Marks</label>
                                <input type="number" name="max_marks" class="form-control" value="100" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Passing Marks</label>
                                <input type="number" name="passing_marks" class="form-control" value="50" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Exam</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function deleteExam(examId, examName) {
            Swal.fire({
                title: 'Delete Exam?',
                html: `Are you sure you want to delete "<strong>${examName}</strong>"?<br>All grades entered for this exam will also be deleted.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    let form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '';
                    let actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'delete';
                    let idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = 'exam_id';
                    idInput.value = examId;
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