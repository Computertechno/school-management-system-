<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$page_title = 'Academic Reports';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$exam_id = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Get classes for filter
$classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
$classes_result = $conn->query($classes_sql);

// Get exams for filter
$exams_sql = "SELECT exam_id, exam_name, term, academic_year FROM exams ORDER BY academic_year DESC, term DESC";
$exams_result = $conn->query($exams_sql);

// Get class performance data
$class_performance = [];
if ($class_id > 0 && $exam_id > 0) {
    $performance_sql = "SELECT s.student_id, s.first_name, s.last_name, s.admission_no,
                        AVG(r.percentage) as avg_percentage,
                        MAX(r.grade) as overall_grade
                        FROM students s
                        JOIN results r ON s.student_id = r.student_id
                        WHERE s.current_class_id = ? AND r.exam_id = ?
                        GROUP BY s.student_id
                        ORDER BY avg_percentage DESC";
    $performance_stmt = $conn->prepare($performance_sql);
    $performance_stmt->bind_param("ii", $class_id, $exam_id);
    $performance_stmt->execute();
    $class_performance = $performance_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get subject-wise performance
$subject_performance = [];
if ($class_id > 0 && $exam_id > 0) {
    $subject_sql = "SELECT sub.subject_name, AVG(r.percentage) as avg_score,
                    MIN(r.percentage) as min_score, MAX(r.percentage) as max_score
                    FROM results r
                    JOIN subjects sub ON r.subject_id = sub.subject_id
                    WHERE r.exam_id = ? AND r.student_id IN (SELECT student_id FROM students WHERE current_class_id = ?)
                    GROUP BY sub.subject_id
                    ORDER BY sub.subject_name";
    $subject_stmt = $conn->prepare($subject_sql);
    $subject_stmt->bind_param("ii", $exam_id, $class_id);
    $subject_stmt->execute();
    $subject_performance = $subject_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #f4f6f9; }
        .main-content { margin-left: 250px; padding: 20px; }
        .card { border: none; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .card-header { background: white; border-bottom: 1px solid #eee; padding: 15px 20px; font-weight: 600; }
        .filter-bar { background: white; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
        .btn-export { background: #2e7d32; color: white; padding: 8px 20px; border-radius: 8px; }
        .grade-A { background: #d4edda; color: #155724; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
        .grade-B { background: #cce5ff; color: #004085; padding: 4px 10px; border-radius: 20px; }
        .grade-C { background: #fff3cd; color: #856404; padding: 4px 10px; border-radius: 20px; }
        .grade-D { background: #ffe5d0; color: #e67e22; padding: 4px 10px; border-radius: 20px; }
        .grade-E { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-chart-line me-2"></i> Academic Reports</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        
        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Select Class</label>
                    <select name="class_id" class="form-select" required>
                        <option value="0">-- Select Class --</option>
                        <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo $class['class_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Select Exam</label>
                    <select name="exam_id" class="form-select" required>
                        <option value="0">-- Select Exam --</option>
                        <?php while ($exam = $exams_result->fetch_assoc()): ?>
                            <option value="<?php echo $exam['exam_id']; ?>" <?php echo ($exam_id == $exam['exam_id']) ? 'selected' : ''; ?>>
                                <?php echo $exam['exam_name']; ?> (<?php echo $exam['academic_year']; ?> Term <?php echo $exam['term']; ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Generate Report</button>
                </div>
            </form>
        </div>
        
        <?php if ($class_id > 0 && $exam_id > 0 && count($class_performance) > 0): ?>
            <!-- Summary Statistics -->
            <?php
            $total_students = count($class_performance);
            $total_avg = array_sum(array_column($class_performance, 'avg_percentage')) / $total_students;
            $pass_count = count(array_filter($class_performance, function($s) { return $s['avg_percentage'] >= 50; }));
            $pass_rate = ($pass_count / $total_students) * 100;
            $highest = max(array_column($class_performance, 'avg_percentage'));
            $lowest = min(array_column($class_performance, 'avg_percentage'));
            ?>
            <div class="row">
                <div class="col-md-3"><div class="card text-center p-3"><h3><?php echo round($total_avg, 1); ?>%</h3><p class="text-muted">Class Average</p></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h3><?php echo round($pass_rate, 1); ?>%</h3><p class="text-muted">Pass Rate</p></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h3><?php echo round($highest, 1); ?>%</h3><p class="text-muted">Highest Score</p></div></div>
                <div class="col-md-3"><div class="card text-center p-3"><h3><?php echo round($lowest, 1); ?>%</h3><p class="text-muted">Lowest Score</p></div></div>
            </div>
            
            <!-- Subject Performance Chart -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-bar"></i> Subject-wise Performance
                    <button class="btn btn-sm btn-export float-end" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                </div>
                <div class="card-body">
                    <canvas id="subjectChart" height="300"></canvas>
                    <div class="table-responsive mt-3">
                        <table class="table table-bordered">
                            <thead><tr><th>Subject</th><th>Average Score</th><th>Highest</th><th>Lowest</th></tr></thead>
                            <tbody>
                                <?php foreach ($subject_performance as $sub): ?>
                                <tr>
                                    <td><?php echo $sub['subject_name']; ?></td>
                                    <td><?php echo round($sub['avg_score'], 1); ?>%</td>
                                    <td><?php echo round($sub['max_score'], 1); ?>%</td>
                                    <td><?php echo round($sub['min_score'], 1); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Student Performance Table -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-users"></i> Student Performance
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead><tr><th>Rank</th><th>Admission No</th><th>Student Name</th><th>Average %</th><th>Grade</th></tr></thead>
                            <tbody>
                                <?php $rank = 1; foreach ($class_performance as $student): ?>
                                <tr>
                                    <td><?php echo $rank++; ?></td>
                                    <td><?php echo $student['admission_no']; ?></td>
                                    <td><?php echo $student['first_name'] . ' ' . $student['last_name']; ?></td>
                                    <td><?php echo round($student['avg_percentage'], 1); ?>%</td>
                                    <td><span class="grade-<?php echo $student['overall_grade']; ?>"><?php echo $student['overall_grade']; ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <script>
                new Chart(document.getElementById('subjectChart'), {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode(array_column($subject_performance, 'subject_name')); ?>,
                        datasets: [{
                            label: 'Average Score (%)',
                            data: <?php echo json_encode(array_map(function($s) { return round($s['avg_score'], 1); }, $subject_performance)); ?>,
                            backgroundColor: '#2e7d32',
                            borderRadius: 8
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: true, scales: { y: { beginAtZero: true, max: 100 } } }
                });
            </script>
        <?php elseif ($class_id > 0 && $exam_id > 0 && count($class_performance) == 0): ?>
            <div class="alert alert-info">No results found for the selected class and exam.</div>
        <?php elseif ($class_id > 0 && $exam_id == 0): ?>
            <div class="alert alert-warning">Please select an exam to view results.</div>
        <?php elseif ($class_id == 0): ?>
            <div class="alert alert-info">Select a class and exam to generate academic report.</div>
        <?php endif; ?>
    </div>
</body>
</html>