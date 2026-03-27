<?php
// PHP code remains the same
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/master.css">
    <style>
        /* Dashboard Header with School Image */
        .dashboard-header {
            background: linear-gradient(135deg, rgba(26,44,62,0.85) 0%, rgba(46,125,50,0.85) 100%),
                        url('../../assets/images/admin-bg.jpg') center/cover no-repeat;
            border-radius: var(--radius-lg);
            padding: 40px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .dashboard-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('../../assets/images/pattern-overlay.png') repeat;
            opacity: 0.1;
        }
        
        .dashboard-header h1 {
            color: white;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-header p {
            color: rgba(255,255,255,0.9);
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, var(--primary-green) 0%, var(--primary-green-dark) 100%);
            border-radius: var(--radius-lg);
            padding: 25px;
            color: white;
            margin-bottom: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::after {
            content: '🎓';
            position: absolute;
            bottom: -20px;
            right: -20px;
            font-size: 100px;
            opacity: 0.1;
            pointer-events: none;
        }
        
        .welcome-card h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .welcome-card p {
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        /* Stat Cards with Icons */
        .stat-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 25px;
            transition: all var(--transition-normal);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.05);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: transform var(--transition-normal);
        }
        
        .stat-card:hover::before {
            transform: scaleX(1);
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(46,125,50,0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .stat-icon i {
            font-size: 30px;
            color: var(--primary-green);
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: 800;
            color: var(--gray-dark);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--gray);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* Activity Timeline */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 8px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: var(--gradient-primary);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 25px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 0;
            width: 12px;
            height: 12px;
            background: var(--primary-green);
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--primary-green);
        }
        
        .timeline-date {
            font-size: 12px;
            color: var(--gray);
            margin-bottom: 5px;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 12px 15px;
            border-radius: var(--radius-md);
        }
        
        /* Quick Actions Grid */
        .action-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .action-item {
            background: white;
            border-radius: var(--radius-md);
            padding: 20px;
            text-align: center;
            transition: all var(--transition-normal);
            cursor: pointer;
            text-decoration: none;
            display: block;
        }
        
        .action-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .action-item i {
            font-size: 32px;
            color: var(--primary-green);
            margin-bottom: 10px;
            display: block;
        }
        
        .action-item span {
            font-weight: 600;
            color: var(--gray-dark);
        }
        
        /* Recent Activities */
        .activity-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-lighter);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: rgba(46,125,50,0.1);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .activity-icon i {
            font-size: 18px;
            color: var(--primary-green);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-text {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .activity-time {
            font-size: 11px;
            color: var(--gray);
        }
    </style>
</head>
<body>
    <!-- Sidebar included -->
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Dashboard Header with School Image -->
        <div class="dashboard-header">
            <h1>Welcome to GAIMS</h1>
            <p>Greenhill Academy Integrated Management System</p>
        </div>
        
        <!-- Welcome Card -->
        <div class="welcome-card">
            <h3>Hello, <?php echo htmlspecialchars($user['username']); ?>!</h3>
            <p>Here's what's happening at Greenhill Academy today.</p>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_students); ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chalkboard-user"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_staff); ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_parents); ?></div>
                    <div class="stat-label">Total Parents</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-number"><?php echo $pending_admissions; ?></div>
                    <div class="stat-label">Pending Admissions</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Charts Section with School Theme -->
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-chart-line text-primary me-2"></i> Students by Class
                    </div>
                    <div class="card-body">
                        <canvas id="classChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Recent Activities Timeline -->
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-history text-primary me-2"></i> Recent Activities
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php while ($activity = $activities_result->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date"><?php echo formatDate($activity['created_at']); ?></div>
                                    <div class="timeline-content">
                                        <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                        <div class="small text-muted"><?php echo $activity['entity']; ?> #<?php echo $activity['entity_id']; ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions Grid -->
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-bolt text-primary me-2"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="action-grid">
                    <a href="../students/add.php" class="action-item">
                        <i class="fas fa-user-plus"></i>
                        <span>Add Student</span>
                    </a>
                    <a href="../staff/add.php" class="action-item">
                        <i class="fas fa-user-tie"></i>
                        <span>Add Staff</span>
                    </a>
                    <a href="../admissions/index.php" class="action-item">
                        <i class="fas fa-door-open"></i>
                        <span>Admissions</span>
                    </a>
                    <a href="../fees/invoices.php" class="action-item">
                        <i class="fas fa-file-invoice"></i>
                        <span>Invoices</span>
                    </a>
                    <a href="../payroll/process.php" class="action-item">
                        <i class="fas fa-calculator"></i>
                        <span>Process Payroll</span>
                    </a>
                    <a href="../reports/index.php" class="action-item">
                        <i class="fas fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('classChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($class_names); ?>,
                datasets: [{
                    label: 'Number of Students',
                    data: <?php echo json_encode($class_counts); ?>,
                    backgroundColor: 'rgba(46, 125, 50, 0.7)',
                    borderColor: 'rgba(46, 125, 50, 1)',
                    borderWidth: 1,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>