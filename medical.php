<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$page_title = 'Medical Reports';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$condition_type = isset($_GET['condition_type']) ? $_GET['condition_type'] : 'all';

// Get health alerts summary
$alerts_sql = "SELECT COUNT(*) as total,
               SUM(CASE WHEN allergies IS NOT NULL AND allergies != '' THEN 1 ELSE 0 END) as allergies_count,
               SUM(CASE WHEN chronic_conditions IS NOT NULL AND chronic_conditions != '' THEN 1 ELSE 0 END) as chronic_count
               FROM medical_records";
$alerts_result = $conn->query($alerts_sql);
$alerts = $alerts_result->fetch_assoc();

// Get students with health alerts
$where = "WHERE (m.allergies IS NOT NULL AND m.allergies != '' OR m.chronic_conditions IS NOT NULL AND m.chronic_conditions != '')";
$params = [];
$types = "";

if ($class_id > 0) {
    $where .= " AND s.current_class_id = ?";
    $params[] = $class_id;
    $types .= "i";
}
if ($condition_type == 'allergies') {
    $where .= " AND m.allergies IS NOT NULL AND m.allergies != ''";
} elseif ($condition_type == 'chronic') {
    $where .= " AND m.chronic_conditions IS NOT NULL AND m.chronic_conditions != ''";
}

$students_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name, s.current_class_id, c.class_name,
                 m.allergies, m.chronic_conditions, m.medications, m.blood_group, m.genotype,
                 m.emergency_contact_name, m.emergency_contact_phone
                 FROM students s
                 JOIN medical_records m ON s.student_id = m.student_id
                 LEFT JOIN classes c ON s.current_class_id = c.class_id
                 $where
                 ORDER BY s.last_name";

$stmt = $conn->prepare($students_sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$students_result = $stmt->get_result();

// Get clinic visits summary
$visits_sql = "SELECT COUNT(*) as total_visits,
               DATE_FORMAT(visit_date, '%Y-%m') as month,
               COUNT(*) as monthly_visits
               FROM clinic_visits
               WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
               GROUP BY DATE_FORMAT(visit_date, '%Y-%m')
               ORDER BY month ASC";
$visits_result = $conn->query($visits_sql)->fetch_all(MYSQLI_ASSOC);

// Get classes for filter
$classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
$classes_result = $conn->query($classes_sql);
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
        .stat-box { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: bold; }
        .alert-card { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin-bottom: 15px; border-radius: 8px; }
        .alert-card.critical { background: #f8d7da; border-left-color: #dc3545; }
        .health-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; }
        .allergy-badge { background: #ffe5d0; color: #e67e22; }
        .chronic-badge { background: #f8d7da; color: #dc3545; }
        .btn-export { background: #2e7d32; color: white; padding: 8px 20px; border-radius: 8px; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-heartbeat me-2"></i> Medical Reports</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        
        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Class</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Classes</option>
                        <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_id == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo $class['class_name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Condition Type</label>
                    <select name="condition_type" class="form-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $condition_type == 'all' ? 'selected' : ''; ?>>All Conditions</option>
                        <option value="allergies" <?php echo $condition_type == 'allergies' ? 'selected' : ''; ?>>Allergies Only</option>
                        <option value="chronic" <?php echo $condition_type == 'chronic' ? 'selected' : ''; ?>>Chronic Conditions Only</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i> Filter</button>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4"><div class="stat-box"><div class="stat-number text-warning"><?php echo $alerts['allergies_count'] ?? 0; ?></div><div>Students with Allergies</div></div></div>
            <div class="col-md-4"><div class="stat-box"><div class="stat-number text-danger"><?php echo $alerts['chronic_count'] ?? 0; ?></div><div>Chronic Conditions</div></div></div>
            <div class="col-md-4"><div class="stat-box"><div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) FROM clinic_visits WHERE visit_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_row()[0]; ?></div><div>Clinic Visits (30 days)</div></div></div>
        </div>
        
        <!-- Students with Health Alerts -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i> Students with Health Alerts
                <span class="badge bg-warning float-end"><?php echo $students_result->num_rows; ?> students</span>
            </div>
            <div class="card-body">
                <?php if ($students_result->num_rows > 0): ?>
                    <?php while ($student = $students_result->fetch_assoc()): 
                        $is_critical = strpos(strtolower($student['chronic_conditions'] ?? ''), 'asthma') !== false ||
                                      strpos(strtolower($student['chronic_conditions'] ?? ''), 'diabetes') !== false ||
                                      strpos(strtolower($student['chronic_conditions'] ?? ''), 'epilepsy') !== false;
                    ?>
                        <div class="alert-card <?php echo $is_critical ? 'critical' : ''; ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                    <span class="badge bg-secondary ms-2"><?php echo $student['class_name']; ?></span>
                                    <span class="badge bg-secondary ms-1"><?php echo $student['admission_no']; ?></span>
                                </div>
                                <div>
                                    <?php if (!empty($student['allergies'])): ?>
                                        <span class="health-badge allergy-badge"><i class="fas fa-allergies"></i> Allergies</span>
                                    <?php endif; ?>
                                    <?php if (!empty($student['chronic_conditions'])): ?>
                                        <span class="health-badge chronic-badge"><i class="fas fa-heartbeat"></i> Chronic</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($student['allergies'])): ?>
                                <div class="mt-2"><strong>Allergies:</strong> <?php echo htmlspecialchars($student['allergies']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($student['chronic_conditions'])): ?>
                                <div class="mt-1"><strong>Chronic Conditions:</strong> <?php echo htmlspecialchars($student['chronic_conditions']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($student['medications'])): ?>
                                <div class="mt-1"><strong>Medications:</strong> <?php echo htmlspecialchars($student['medications']); ?></div>
                            <?php endif; ?>
                            <div class="mt-2 pt-2 border-top">
                                <i class="fas fa-tint"></i> Blood: <?php echo $student['blood_group']; ?> | 
                                <i class="fas fa-dna"></i> Genotype: <?php echo $student['genotype']; ?> |
                                <i class="fas fa-phone-alt"></i> Emergency: <?php echo $student['emergency_contact_name'] ?: 'Not set'; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-success text-center">
                        <i class="fas fa-check-circle fa-2x mb-2"></i>
                        <p>No health alerts found for the selected criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Clinic Visits Trend -->
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line"></i> Clinic Visits Trend (Last 6 Months)</div>
            <div class="card-body"><canvas id="visitsChart" height="250"></canvas></div>
        </div>
        
        <script>
            new Chart(document.getElementById('visitsChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($visits_result, 'month')); ?>,
                    datasets: [{ label: 'Clinic Visits', data: <?php echo json_encode(array_column($visits_result, 'monthly_visits')); ?>, borderColor: '#dc3545', fill: false, tension: 0.4 }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        </script>
    </div>
</body>
</html>