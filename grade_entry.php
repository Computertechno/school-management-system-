<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_teacher = ($user['role_name'] == 'teacher');
$is_admin = ($user['role_name'] == 'admin');

$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

if ($exam_id <= 0) {
    redirect('exams.php');
}

// Get exam details
$exam_sql = "SELECT * FROM exams WHERE exam_id = ?";
$exam_stmt = $conn->prepare($exam_sql);
$exam_stmt->bind_param("i", $exam_id);
$exam_stmt->execute();
$exam_result = $exam_stmt->get_result();
$exam = $exam_result->fetch_assoc();

if (!$exam) {
    redirect('exams.php');
}

// Get classes for teacher
if ($is_teacher) {
    // Get classes assigned to this teacher
    $teacher_sql = "SELECT DISTINCT c.class_id, c.class_name 
                    FROM staff s 
                    JOIN class_subjects cs ON cs.teacher_id = s.staff_id 
                    JOIN classes c ON cs.class_id = c.class_id 
                    WHERE s.user_id = ?";
    $teacher_stmt = $conn->prepare($teacher_sql);
    $teacher_stmt->bind_param("i", $user['user_id']);
    $teacher_stmt->execute();
    $teacher_classes = $teacher_stmt->get_result();
    $class_options = [];
    while ($row = $teacher_classes->fetch_assoc()) {
        $class_options[$row['class_id']] = $row['class_name'];
    }
} else {
    // Admin can see all classes
    $all_classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
    $all_classes_result = $conn->query($all_classes_sql);
    $class_options = [];
    while ($row = $all_classes_result->fetch_assoc()) {
        $class_options[$row['class_id']] = $row['class_name'];
    }
}

// If class not selected, show class selection
if ($class_id == 0 && count($class_options) > 0) {
    $class_id = key($class_options);
}

// Get subjects for selected class
$subjects = [];
if ($class_id > 0) {
    $subjects_sql = "SELECT cs.class_subject_id, s.subject_id, s.subject_name, s.subject_code 
                     FROM class_subjects cs 
                     JOIN subjects s ON cs.subject_id = s.subject_id 
                     WHERE cs.class_id = ? AND cs.academic_year = ? AND cs.term = ?";
    $subjects_stmt = $conn->prepare($subjects_sql);
    $subjects_stmt->bind_param("isi", $class_id, CURRENT_ACADEMIC_YEAR, CURRENT_TERM);
    $subjects_stmt->execute();
    $subjects_result = $subjects_stmt->get_result();
    $subjects = $subjects_result->fetch_all(MYSQLI_ASSOC);
}

// If subject not selected and there are subjects, select first
if ($subject_id == 0 && count($subjects) > 0) {
    $subject_id = $subjects[0]['subject_id'];
}

// Get students for grade entry
$students = [];
if ($class_id > 0 && $subject_id > 0) {
    $students_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name,
                     r.result_id, r.marks_obtained, r.grade, r.remarks
                     FROM students s
                     LEFT JOIN results r ON s.student_id = r.student_id 
                        AND r.subject_id = ? AND r.exam_id = ?
                     WHERE s.current_class_id = ? AND s.enrollment_status = 'Active'
                     ORDER BY s.last_name, s.first_name";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("iii", $subject_id, $exam_id, $class_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    $students = $students_result->fetch_all(MYSQLI_ASSOC);
}

// Handle grade submission
$error = '';
$success = '';
$saved_count = 0;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grades'])) {
    $marks = $_POST['marks'];
    $remarks = $_POST['remarks'];
    
    // Get class level for grade calculation
    $class_level_sql = "SELECT class_level FROM classes WHERE class_id = ?";
    $class_level_stmt = $conn->prepare($class_level_sql);
    $class_level_stmt->bind_param("i", $class_id);
    $class_level_stmt->execute();
    $class_level_result = $class_level_stmt->get_result();
    $class_level = $class_level_result->fetch_assoc()['class_level'];
    
    foreach ($marks as $student_id => $mark) {
        if ($mark === '' || $mark === null) continue;
        
        $mark = (float)$mark;
        $percentage = ($mark / $exam['max_marks']) * 100;
        $grade = calculateGrade($percentage, $class_level);
        $remark_text = $remarks[$student_id] ?? '';
        
        // Check if result exists
        $check_sql = "SELECT result_id FROM results WHERE student_id = ? AND subject_id = ? AND exam_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("iii", $student_id, $subject_id, $exam_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing
            $row = $check_result->fetch_assoc();
            $result_id = $row['result_id'];
            $update_sql = "UPDATE results SET marks_obtained = ?, percentage = ?, grade = ?, remarks = ?, entered_by = ? WHERE result_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ddssii", $mark, $percentage, $grade, $remark_text, $user['user_id'], $result_id);
            $update_stmt->execute();
        } else {
            // Insert new
            $insert_sql = "INSERT INTO results (student_id, subject_id, exam_id, marks_obtained, percentage, grade, remarks, entered_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_sql);
            $insert_stmt->bind_param("iiiddssi", $student_id, $subject_id, $exam_id, $mark, $percentage, $grade, $remark_text, $user['user_id']);
            $insert_stmt->execute();
        }
        $saved_count++;
    }
    
    if ($saved_count > 0) {
        $success = "$saved_count grade(s) saved successfully!";
        logActivity($user['user_id'], 'ENTER_GRADES', 'results', $exam_id);
        
        // Refresh student list
        $students_stmt->execute();
        $students = $students_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "No grades were saved.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grade Entry - <?php echo htmlspecialchars($exam['exam_name']); ?> - <?php echo SITE_NAME; ?></title>
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
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .grade-input {
            width: 80px;
            text-align: center;
        }
        .grade-table th {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }
        .grade-table {
            max-height: 500px;
            overflow-y: auto;
        }
        .grade-badge {
            display: inline-block;
            width: 35px;
            height: 35px;
            line-height: 35px;
            text-align: center;
            border-radius: 50%;
            font-weight: bold;
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
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4><i class="fas fa-edit"></i> Grade Entry</h4>
                <p class="text-muted mb-0"><?php echo htmlspecialchars($exam['exam_name']); ?> - <?php echo $exam['academic_year']; ?> Term <?php echo $exam['term']; ?></p>
            </div>
            <a href="exams.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Exams
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                <div class="col-md-4">
                    <label class="form-label">Select Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Select Class --</option>
                        <?php foreach ($class_options as $cid => $cname): ?>
                            <option value="<?php echo $cid; ?>" <?php echo ($class_id == $cid) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Select Subject</label>
                    <select name="subject_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Select Subject --</option>
                        <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" <?php echo ($subject_id == $subject['subject_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?> (<?php echo $subject['subject_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <div>
                        <span class="badge bg-info p-2">
                            <i class="fas fa-star"></i> Max Marks: <?php echo $exam['max_marks']; ?> | Passing: <?php echo $exam['passing_marks']; ?>
                        </span>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Grade Entry Form -->
        <?php if ($class_id > 0 && $subject_id > 0 && count($students) > 0): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> Grade Entry - 
                    <?php echo htmlspecialchars($class_options[$class_id] ?? 'Class'); ?> - 
                    <?php echo htmlspecialchars($subjects[array_search($subject_id, array_column($subjects, 'subject_id'))]['subject_name'] ?? 'Subject'); ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="grade-table">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th width="50">#</th>
                                        <th>Admission No</th>
                                        <th>Student Name</th>
                                        <th width="100">Marks (0-<?php echo $exam['max_marks']; ?>)</th>
                                        <th width="80">%</th>
                                        <th width="80">Grade</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $index => $student): 
                                        $marks = $student['marks_obtained'] ?? '';
                                        $percentage = $marks ? ($marks / $exam['max_marks'] * 100) : 0;
                                        $grade = $student['grade'] ?? '';
                                    ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($student['admission_no']); ?></td>
                                            <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                            <td>
                                                <input type="number" name="marks[<?php echo $student['student_id']; ?>]" 
                                                       class="form-control grade-input" 
                                                       value="<?php echo $marks; ?>"
                                                       min="0" max="<?php echo $exam['max_marks']; ?>"
                                                       step="0.5"
                                                       onchange="calculateGrade(this, <?php echo $exam['max_marks']; ?>, '<?php echo $student['student_id']; ?>')">
                                            </td>
                                            <td class="percentage-<?php echo $student['student_id']; ?>">
                                                <?php echo $percentage ? number_format($percentage, 1) . '%' : '-'; ?>
                                            </td>
                                            <td class="grade-<?php echo $student['student_id']; ?>">
                                                <?php if ($grade): ?>
                                                    <span class="grade-badge grade-<?php echo $grade; ?>"><?php echo $grade; ?></span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[<?php echo $student['student_id']; ?>]" 
                                                       class="form-control" 
                                                       value="<?php echo htmlspecialchars($student['remarks'] ?? ''); ?>"
                                                       placeholder="Optional comment">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-3 text-end">
                            <button type="submit" name="save_grades" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Save All Grades
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                function calculateGrade(input, maxMarks, studentId) {
                    let marks = parseFloat(input.value);
                    if (isNaN(marks)) {
                        marks = 0;
                    }
                    let percentage = (marks / maxMarks) * 100;
                    
                    // Update percentage display
                    let percentageCell = document.querySelector(`.percentage-${studentId}`);
                    percentageCell.innerHTML = percentage.toFixed(1) + '%';
                    
                    // Calculate grade
                    let grade = '';
                    if (percentage >= 80) grade = 'A';
                    else if (percentage >= 70) grade = 'B';
                    else if (percentage >= 60) grade = 'C';
                    else if (percentage >= 50) grade = 'D';
                    else if (percentage >= 40) grade = 'E';
                    else grade = 'F';
                    
                    // Update grade display
                    let gradeCell = document.querySelector(`.grade-${studentId}`);
                    gradeCell.innerHTML = `<span class="grade-badge grade-${grade}">${grade}</span>`;
                }
            </script>
        <?php elseif ($class_id > 0 && $subject_id > 0 && count($students) == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <p>No students found in this class.</p>
                </div>
            </div>
        <?php elseif ($class_id > 0 && $subject_id == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p>Please select a subject to enter grades.</p>
                </div>
            </div>
        <?php elseif ($class_id == 0): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                    <p>Please select a class to begin grade entry.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>