<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Get students with health alerts (allergies or chronic conditions)
$alerts_sql = "SELECT s.student_id, s.admission_no, s.first_name, s.last_name, s.current_class_id, c.class_name,
               m.allergies, m.chronic_conditions, m.medications, m.emergency_contact_name, m.emergency_contact_phone
               FROM students s
               LEFT JOIN classes c ON s.current_class_id = c.class_id
               JOIN medical_records m ON s.student_id = m.student_id
               WHERE s.enrollment_status = 'Active' 
               AND (m.allergies IS NOT NULL AND m.allergies != '' 
                    OR m.chronic_conditions IS NOT NULL AND m.chronic_conditions != '')
               ORDER BY s.last_name";
$alerts_result = $conn->query($alerts_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Alerts - <?php echo SITE_NAME; ?></title>
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
        .alert-card {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            margin-bottom: 15px;
            border-radius: 8px;
            padding: 15px;
        }
        .alert-card.critical {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .alert-card.warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .student-name {
            font-size: 18px;
            font-weight: bold;
        }
        .alert-badge {
            background: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-bell"></i> Health Alerts</h4>
            <a href="records.php" class="btn btn-primary">
                <i class="fas fa-notes-medical"></i> Manage Medical Records
            </a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle"></i> Students with Health Conditions
                <span class="badge bg-warning float-end"><?php echo $alerts_result->num_rows; ?> alerts</span>
            </div>
            <div class="card-body">
                <?php if ($alerts_result->num_rows > 0): ?>
                    <?php while ($alert = $alerts_result->fetch_assoc()): 
                        $has_allergies = !empty($alert['allergies']);
                        $has_chronic = !empty($alert['chronic_conditions']);
                        $is_critical = $has_chronic && strpos(strtolower($alert['chronic_conditions']), 'asthma') !== false ||
                                      strpos(strtolower($alert['chronic_conditions']), 'diabetes') !== false ||
                                      strpos(strtolower($alert['chronic_conditions']), 'epilepsy') !== false;
                    ?>
                        <div class="alert-card <?php echo $is_critical ? 'critical' : 'warning'; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="student-name">
                                        <?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo $alert['class_name']; ?></span>
                                        <?php if ($is_critical): ?>
                                            <span class="alert-badge ms-2"><i class="fas fa-exclamation-circle"></i> CRITICAL</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-muted small">Admission: <?php echo $alert['admission_no']; ?></div>
                                </div>
                                <div>
                                    <a href="records.php?student_id=<?php echo $alert['student_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i> View Full Record
                                    </a>
                                </div>
                            </div>
                            
                            <?php if ($has_allergies): ?>
                                <div class="mt-2">
                                    <strong><i class="fas fa-allergies"></i> Allergies:</strong>
                                    <span class="text-danger"><?php echo htmlspecialchars($alert['allergies']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($has_chronic): ?>
                                <div class="mt-1">
                                    <strong><i class="fas fa-heartbeat"></i> Chronic Conditions:</strong>
                                    <span class="text-warning"><?php echo htmlspecialchars($alert['chronic_conditions']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($alert['medications'])): ?>
                                <div class="mt-1">
                                    <strong><i class="fas fa-capsules"></i> Regular Medications:</strong>
                                    <?php echo htmlspecialchars($alert['medications']); ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-2 pt-2 border-top">
                                <i class="fas fa-phone-alt"></i> Emergency Contact: 
                                <strong><?php echo htmlspecialchars($alert['emergency_contact_name'] ?: 'Not specified'); ?></strong>
                                <?php if ($alert['emergency_contact_phone']): ?>
                                    | Tel: <?php echo $alert['emergency_contact_phone']; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                        <p>No health alerts at this time.</p>
                        <p class="text-muted">All students have complete medical records with no allergies or chronic conditions.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle"></i> Important Instructions
            </div>
            <div class="card-body">
                <ul class="mb-0">
                    <li>Staff should be aware of students with allergies and chronic conditions.</li>
                    <li>In case of a medical emergency, contact the school clinic immediately.</li>
                    <li>Always check student medical records before administering any medication.</li>
                    <li>For critical conditions (asthma, diabetes, epilepsy), ensure emergency protocols are followed.</li>
                    <li>Parents are notified automatically when a clinic visit is recorded with the "Notify Parent" option.</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>