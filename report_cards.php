<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php'; // DOMPDF autoload

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();
$user = getCurrentUser();
$is_teacher = ($user['role_name'] == 'teacher');
$is_admin = ($user['role_name'] == 'admin');

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$term = isset($_GET['term']) ? (int)$_GET['term'] : CURRENT_TERM;
$academic_year = isset($_GET['academic_year']) ? $_GET['academic_year'] : CURRENT_ACADEMIC_YEAR;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$error = '';
$success = '';

// Get classes for teacher
if ($is_teacher) {
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
    $all_classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
    $all_classes_result = $conn->query($all_classes_sql);
    $class_options = [];
    while ($row = $all_classes_result->fetch_assoc()) {
        $class_options[$row['class_id']] = $row['class_name'];
    }
}

// Get students for selected class
$students = [];
if ($class_id > 0) {
    $students_sql = "SELECT student_id, admission_no, first_name, last_name 
                     FROM students 
                     WHERE current_class_id = ? AND enrollment_status = 'Active'
                     ORDER BY last_name, first_name";
    $students_stmt = $conn->prepare($students_sql);
    $students_stmt->bind_param("i", $class_id);
    $students_stmt->execute();
    $students_result = $students_stmt->get_result();
    $students = $students_result->fetch_all(MYSQLI_ASSOC);
}

// Generate report card function
function generateReportCard($conn, $student_id, $term, $academic_year, $class_id) {
    // Get student details
    $student_sql = "SELECT s.*, c.class_name, c.class_level, cp.campus_name 
                    FROM students s 
                    JOIN classes c ON s.current_class_id = c.class_id 
                    JOIN campuses cp ON s.campus_id = cp.campus_id 
                    WHERE s.student_id = ?";
    $student_stmt = $conn->prepare($student_sql);
    $student_stmt->bind_param("i", $student_id);
    $student_stmt->execute();
    $student = $student_stmt->get_result()->fetch_assoc();
    
    if (!$student) return null;
    
    // Get subjects and grades for this student
    $grades_sql = "SELECT s.subject_id, s.subject_name, s.subject_code, 
                   r.marks_obtained, r.percentage, r.grade, r.remarks,
                   e.exam_name, e.max_marks
                   FROM results r
                   JOIN subjects s ON r.subject_id = s.subject_id
                   JOIN exams e ON r.exam_id = e.exam_id
                   WHERE r.student_id = ? AND e.term = ? AND e.academic_year = ?
                   ORDER BY s.subject_name";
    $grades_stmt = $conn->prepare($grades_sql);
    $grades_stmt->bind_param("iis", $student_id, $term, $academic_year);
    $grades_stmt->execute();
    $grades_result = $grades_stmt->get_result();
    $grades = $grades_result->fetch_all(MYSQLI_ASSOC);
    
    // Calculate statistics
    $total_marks = 0;
    $total_possible = 0;
    $subject_count = count($grades);
    foreach ($grades as $grade) {
        $total_marks += $grade['marks_obtained'];
        $total_possible += $grade['max_marks'];
    }
    $average_percentage = $subject_count > 0 ? ($total_marks / $total_possible) * 100 : 0;
    
    // Get class ranking
    $rank_sql = "SELECT s.student_id, 
                 AVG(r.percentage) as avg_percentage
                 FROM students s
                 JOIN results r ON s.student_id = r.student_id
                 JOIN exams e ON r.exam_id = e.exam_id
                 WHERE s.current_class_id = ? AND e.term = ? AND e.academic_year = ?
                 GROUP BY s.student_id
                 ORDER BY avg_percentage DESC";
    $rank_stmt = $conn->prepare($rank_sql);
    $rank_stmt->bind_param("iis", $class_id, $term, $academic_year);
    $rank_stmt->execute();
    $rank_result = $rank_stmt->get_result();
    $rankings = [];
    $position = 1;
    while ($row = $rank_result->fetch_assoc()) {
        $rankings[$row['student_id']] = $position++;
    }
    $class_rank = $rankings[$student_id] ?? 0;
    $total_students = count($rankings);
    
    // Get attendance for the term
    $attendance_sql = "SELECT COUNT(*) as total_days, 
                       SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
                       FROM student_attendance 
                       WHERE student_id = ? AND MONTH(attendance_date) BETWEEN ? AND ?";
    // Simplified attendance calculation
    $present_days = 0;
    $total_days = 0;
    
    // Get teacher comments
    $comment_sql = "SELECT teacher_comment, head_teacher_comment 
                    FROM report_cards 
                    WHERE student_id = ? AND term = ? AND academic_year = ?";
    $comment_stmt = $conn->prepare($comment_sql);
    $comment_stmt->bind_param("iis", $student_id, $term, $academic_year);
    $comment_stmt->execute();
    $comment_result = $comment_stmt->get_result();
    $comments = $comment_result->fetch_assoc();
    
    return [
        'student' => $student,
        'grades' => $grades,
        'average_percentage' => $average_percentage,
        'class_rank' => $class_rank,
        'total_students' => $total_students,
        'present_days' => $present_days,
        'total_days' => $total_days,
        'teacher_comment' => $comments['teacher_comment'] ?? '',
        'head_teacher_comment' => $comments['head_teacher_comment'] ?? ''
    ];
}

// Handle single report card generation
if (isset($_GET['generate_single']) && $student_id > 0) {
    $report_data = generateReportCard($conn, $student_id, $term, $academic_year, $class_id);
    
    if ($report_data) {
        // Generate PDF
        $options = new Options();
        $options->set('defaultFont', 'Courier');
        $dompdf = new Dompdf($options);
        
        $html = getReportCardHTML($report_data);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Output PDF
        $filename = "ReportCard_" . $report_data['student']['admission_no'] . "_T" . $term . "_" . $academic_year . ".pdf";
        $dompdf->stream($filename, array("Attachment" => false));
        exit;
    } else {
        $error = "Failed to generate report card.";
    }
}

// Handle batch report card generation
if (isset($_POST['generate_batch']) && $class_id > 0) {
    $zip = new ZipArchive();
    $zip_filename = "ReportCards_Class_" . $class_id . "_T" . $term . ".zip";
    $zip_path = sys_get_temp_dir() . '/' . $zip_filename;
    
    if ($zip->open($zip_path, ZipArchive::CREATE) !== TRUE) {
        $error = "Could not create zip file.";
    } else {
        $generated = 0;
        foreach ($students as $student) {
            $report_data = generateReportCard($conn, $student['student_id'], $term, $academic_year, $class_id);
            if ($report_data) {
                $options = new Options();
                $options->set('defaultFont', 'Courier');
                $dompdf = new Dompdf($options);
                $html = getReportCardHTML($report_data);
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                $pdf_content = $dompdf->output();
                $filename = $report_data['student']['admission_no'] . "_" . $report_data['student']['first_name'] . ".pdf";
                $zip->addFromString($filename, $pdf_content);
                $generated++;
            }
        }
        $zip->close();
        
        if ($generated > 0) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_filename . '"');
            header('Content-Length: ' . filesize($zip_path));
            readfile($zip_path);
            unlink($zip_path);
            exit;
        } else {
            $error = "No report cards generated.";
        }
    }
}

// Handle saving comments
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_comments'])) {
    $student_id = (int)$_POST['student_id'];
    $teacher_comment = trim($_POST['teacher_comment']);
    $head_teacher_comment = trim($_POST['head_teacher_comment']);
    
    $check_sql = "SELECT report_id FROM report_cards WHERE student_id = ? AND term = ? AND academic_year = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("iis", $student_id, $term, $academic_year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $update_sql = "UPDATE report_cards SET teacher_comment = ?, head_teacher_comment = ? 
                       WHERE student_id = ? AND term = ? AND academic_year = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ssiis", $teacher_comment, $head_teacher_comment, $student_id, $term, $academic_year);
        $update_stmt->execute();
    } else {
        $insert_sql = "INSERT INTO report_cards (student_id, term, academic_year, class_id, teacher_comment, head_teacher_comment) 
                       VALUES (?, ?, ?, ?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iisiss", $student_id, $term, $academic_year, $class_id, $teacher_comment, $head_teacher_comment);
        $insert_stmt->execute();
    }
    
    $success = "Comments saved successfully!";
}

function getReportCardHTML($data) {
    $student = $data['student'];
    $grades = $data['grades'];
    $average = $data['average_percentage'];
    $rank = $data['class_rank'];
    $total_students = $data['total_students'];
    $teacher_comment = $data['teacher_comment'];
    $head_teacher_comment = $data['head_teacher_comment'];
    
    $grade_class = '';
    if ($average >= 80) $grade_class = 'excellent';
    elseif ($average >= 70) $grade_class = 'very-good';
    elseif ($average >= 60) $grade_class = 'good';
    elseif ($average >= 50) $grade_class = 'average';
    else $grade_class = 'poor';
    
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report Card - {$student['first_name']} {$student['last_name']}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .report-card {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #2c3e50;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            opacity: 0.8;
        }
        .school-info {
            text-align: center;
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .student-info {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #ddd;
        }
        .student-info table {
            width: 100%;
        }
        .student-info td {
            padding: 5px;
        }
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .grades-table th, .grades-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        .grades-table th {
            background: #3498db;
            color: white;
        }
        .average {
            padding: 15px;
            text-align: center;
            background: #ecf0f1;
        }
        .average-score {
            font-size: 36px;
            font-weight: bold;
            color: #2c3e50;
        }
        .average-label {
            font-size: 14px;
            color: #7f8c8d;
        }
        .rank {
            margin-top: 10px;
            font-size: 14px;
        }
        .comments {
            padding: 15px;
            border-top: 1px solid #ddd;
        }
        .signatures {
            padding: 15px;
            display: flex;
            justify-content: space-between;
            border-top: 1px solid #ddd;
        }
        .signature-line {
            text-align: center;
            width: 45%;
        }
        .signature-line .line {
            border-top: 1px solid #000;
            margin-top: 30px;
            padding-top: 5px;
        }
        .footer {
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #ddd;
        }
        .excellent { color: #27ae60; }
        .very-good { color: #2980b9; }
        .good { color: #f39c12; }
        .average { color: #e67e22; }
        .poor { color: #e74c3c; }
        .grade-A { background: #d4edda; }
        .grade-B { background: #cce5ff; }
        .grade-C { background: #fff3cd; }
        .grade-D { background: #ffe5d0; }
        .grade-E { background: #f8d7da; }
        .grade-F { background: #f5c6cb; }
    </style>
</head>
<body>
    <div class="report-card">
        <div class="header">
            <h1>GREENHILL ACADEMY</h1>
            <p>Kampala, Uganda | Kibuli & Buwaate Campuses</p>
        </div>
        
        <div class="school-info">
            <strong>END OF TERM REPORT CARD</strong>
            <p>Term {$GLOBALS['term']}, {$GLOBALS['academic_year']}</p>
        </div>
        
        <div class="student-info">
            <table>
                <tr>
                    <td width="50%"><strong>Student Name:</strong> {$student['first_name']} {$student['last_name']}</td>
                    <td><strong>Admission No:</strong> {$student['admission_no']}</td>
                </tr>
                <tr>
                    <td><strong>Class:</strong> {$student['class_name']}</td>
                    <td><strong>Campus:</strong> {$student['campus_name']}</td>
                </tr>
                <tr>
                    <td><strong>Gender:</strong> {$student['gender']}</td>
                    <td><strong>Date of Birth:</strong> " . date('d M Y', strtotime($student['date_of_birth'])) . "</td>
                </tr>
            </table>
        </div>
        
        <div style="padding: 0 15px;">
            <h4>Academic Performance</h4>
            <table class="grades-table">
                <thead>
                    <tr>
                        <th>Subject</th>
                        <th>Marks Obtained</th>
                        <th>Max Marks</th>
                        <th>Percentage</th>
                        <th>Grade</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
HTML;
    
    foreach ($grades as $grade) {
        $percentage = number_format($grade['percentage'], 1);
        $grade_class = '';
        if ($grade['grade'] == 'A') $grade_class = 'grade-A';
        elseif ($grade['grade'] == 'B') $grade_class = 'grade-B';
        elseif ($grade['grade'] == 'C') $grade_class = 'grade-C';
        elseif ($grade['grade'] == 'D') $grade_class = 'grade-D';
        elseif ($grade['grade'] == 'E') $grade_class = 'grade-E';
        else $grade_class = 'grade-F';
        
        $html .= <<<HTML
                    <tr>
                        <td>{$grade['subject_name']}</td>
                        <td>{$grade['marks_obtained']}</td>
                        <td>{$grade['max_marks']}</td>
                        <td>{$percentage}%</td>
                        <td class="{$grade_class}"><strong>{$grade['grade']}</strong></td>
                        <td>{$grade['remarks']}</td>
                    </tr>
HTML;
    }
    
    $html .= <<<HTML
                </tbody>
            </table>
        </div>
        
        <div class="average">
            <div class="average-score {$grade_class}">" . number_format($average, 1) . "%</div>
            <div class="average-label">Overall Average</div>
            <div class="rank">
                Class Rank: {$rank} / {$total_students}
            </div>
        </div>
        
        <div class="comments">
            <strong>Class Teacher's Comment:</strong><br>
            <p>" . ($teacher_comment ?: '_________________________________________') . "</p>
            <br>
            <strong>Head Teacher's Comment:</strong><br>
            <p>" . ($head_teacher_comment ?: '_________________________________________') . "</p>
        </div>
        
        <div class="signatures">
            <div class="signature-line">
                <div class="line"></div>
                <small>Class Teacher's Signature</small>
            </div>
            <div class="signature-line">
                <div class="line"></div>
                <small>Head Teacher's Signature</small>
            </div>
        </div>
        
        <div class="footer">
            This report card is electronically generated and does not require a physical signature.<br>
            Greenhill Academy - Excellence in Education
        </div>
    </div>
</body>
</html>
HTML;
    
    return $html;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Cards - <?php echo SITE_NAME; ?></title>
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
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .student-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-generate {
            background: #27ae60;
            color: white;
        }
        .btn-generate:hover {
            background: #219a52;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-file-pdf"></i> Report Card Generator</h4>
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
                <div class="col-md-3">
                    <label class="form-label">Academic Year</label>
                    <select name="academic_year" class="form-select">
                        <option value="2024/2025" <?php echo $academic_year == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024" <?php echo $academic_year == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Term</label>
                    <select name="term" class="form-select">
                        <option value="1" <?php echo $term == 1 ? 'selected' : ''; ?>>Term 1</option>
                        <option value="2" <?php echo $term == 2 ? 'selected' : ''; ?>>Term 2</option>
                        <option value="3" <?php echo $term == 3 ? 'selected' : ''; ?>>Term 3</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">-- Select Class --</option>
                        <?php foreach ($class_options as $cid => $cname): ?>
                            <option value="<?php echo $cid; ?>" <?php echo ($class_id == $cid) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cname); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <?php if ($class_id > 0 && count($students) > 0): ?>
                            <form method="POST" action="" style="display: inline;">
                                <input type="hidden" name="generate_batch" value="1">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-file-archive"></i> Generate All (ZIP)
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <?php if ($class_id > 0): ?>
            <!-- Students List for Individual Report Cards -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Students in Class
                    <span class="badge bg-secondary float-end"><?php echo count($students); ?> students</span>
                </div>
                <div class="card-body">
                    <?php if (count($students) > 0): ?>
                        <?php foreach ($students as $student): 
                            // Get preview data
                            $preview_data = generateReportCard($conn, $student['student_id'], $term, $academic_year, $class_id);
                            $has_grades = $preview_data && count($preview_data['grades']) > 0;
                        ?>
                            <div class="student-item">
                                <div>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <br>
                                    <small class="text-muted">Admission: <?php echo $student['admission_no']; ?></small>
                                    <?php if ($preview_data && $preview_data['average_percentage'] > 0): ?>
                                        <span class="badge bg-info ms-2">Avg: <?php echo number_format($preview_data['average_percentage'], 1); ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-sm btn-info me-1" data-bs-toggle="modal" data-bs-target="#commentModal<?php echo $student['student_id']; ?>">
                                        <i class="fas fa-comment"></i> Comments
                                    </button>
                                    <a href="?generate_single=1&student_id=<?php echo $student['student_id']; ?>&class_id=<?php echo $class_id; ?>&term=<?php echo $term; ?>&academic_year=<?php echo $academic_year; ?>" 
                                       class="btn btn-sm btn-generate <?php echo !$has_grades ? 'disabled' : ''; ?>"
                                       <?php echo !$has_grades ? 'onclick="return false;"' : ''; ?>>
                                        <i class="fas fa-file-pdf"></i> Generate Report Card
                                    </a>
                                </div>
                            </div>
                            
                            <!-- Comment Modal -->
                            <div class="modal fade" id="commentModal<?php echo $student['student_id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Comments for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST" action="">
                                            <div class="modal-body">
                                                <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                                <input type="hidden" name="save_comments" value="1">
                                                <div class="mb-3">
                                                    <label class="form-label">Class Teacher's Comment</label>
                                                    <textarea name="teacher_comment" class="form-control" rows="3"><?php echo htmlspecialchars($preview_data['teacher_comment'] ?? ''); ?></textarea>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Head Teacher's Comment</label>
                                                    <textarea name="head_teacher_comment" class="form-control" rows="3"><?php echo htmlspecialchars($preview_data['head_teacher_comment'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Comments</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p>No students found in this class.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-chalkboard fa-3x text-muted mb-3"></i>
                    <p>Please select a class to generate report cards.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Instructions -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Instructions
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Ensure grades have been entered for all subjects before generating report cards.</li>
                    <li>Click "Generate Report Card" to download a single PDF.</li>
                    <li>Click "Generate All (ZIP)" to download all report cards as a ZIP file.</li>
                    <li>Use the "Comments" button to add teacher comments before generating.</li>
                    <li>Report cards include digital signatures and do not require physical signing.</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>