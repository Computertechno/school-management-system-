<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Redirect non-accountants
if ($user['role_name'] != 'accountant') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Get current term fee collection statistics
$fee_sql = "SELECT SUM(amount_due) as total_due, SUM(amount_paid) as total_paid 
            FROM invoices 
            WHERE academic_year = ? AND term = ?";
$fee_stmt = $conn->prepare($fee_sql);
$fee_stmt->bind_param("si", CURRENT_ACADEMIC_YEAR, CURRENT_TERM);
$fee_stmt->execute();
$fee_result = $fee_stmt->get_result();
$fee_stats = $fee_result->fetch_assoc();

$total_due = $fee_stats['total_due'] ?? 0;
$total_paid = $fee_stats['total_paid'] ?? 0;
$total_balance = $total_due - $total_paid;
$collection_percentage = $total_due > 0 ? round(($total_paid / $total_due) * 100, 1) : 0;

// Get monthly fee collection for chart
$monthly_sql = "SELECT MONTH(payment_date) as month, SUM(amount) as total 
                FROM payments 
                WHERE YEAR(payment_date) = YEAR(CURDATE())
                GROUP BY MONTH(payment_date)
                ORDER BY month";
$monthly_result = $conn->query($monthly_sql);
$months_data = [];
$collections_data = [];
for ($i = 1; $i <= 12; $i++) {
    $months_data[] = date('F', mktime(0, 0, 0, $i, 1));
    $collections_data[] = 0;
}
while ($row = $monthly_result->fetch_assoc()) {
    $collections_data[$row['month'] - 1] = $row['total'];
}

// Get pending payroll
$payroll_sql = "SELECT COUNT(*) as pending_count, SUM(net_salary) as total_amount 
                FROM salaries 
                WHERE status = 'Pending'";
$payroll_result = $conn->query($payroll_sql);
$payroll_stats = $payroll_result->fetch_assoc();

// Get overdue invoices
$overdue_sql = "SELECT COUNT(*) as count, SUM(balance) as total_balance 
                FROM invoices 
                WHERE status = 'Overdue' OR (status = 'Pending' AND due_date < CURDATE())";
$overdue_result = $conn->query($overdue_sql);
$overdue_stats = $overdue_result->fetch_assoc();

// Get recent transactions
$transactions_sql = "SELECT p.*, s.first_name, s.last_name, s.admission_no 
                     FROM payments p
                     JOIN students s ON p.student_id = s.student_id
                     ORDER BY p.payment_date DESC LIMIT 10";
$transactions_result = $conn->query($transactions_sql);

// Get class-wise fee collection
$class_sql = "SELECT c.class_name, 
              SUM(i.amount_due) as total_due,
              SUM(i.amount_paid) as total_paid
              FROM invoices i
              JOIN students s ON i.student_id = s.student_id
              JOIN classes c ON s.current_class_id = c.class_id
              WHERE i.academic_year = ? AND i.term = ?
              GROUP BY c.class_id
              ORDER BY c.class_name";
$class_stmt = $conn->prepare($class_sql);
$class_stmt->bind_param("si", CURRENT_ACADEMIC_YEAR, CURRENT_TERM);
$class_stmt->execute();
$class_stats = $class_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .sidebar {
            background: #2c3e50;
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            transition: all 0.3s;
        }
        .sidebar .brand {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #3e5a6f;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            width: 25px;
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .top-bar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }
        .stat-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        .collection-bar {
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .collection-fill {
            height: 100%;
            background: #28a745;
        }
        .transaction-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            border-left: 3px solid #3498db;
        }
        .class-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .quick-action {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
        }
        .quick-action:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h4>GAIMS</h4>
            <small>Finance Portal</small>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link active" href="accountant.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link" href="../fees/structure.php">
                <i class="fas fa-table"></i> Fee Structure
            </a>
            <a class="nav-link" href="../fees/invoices.php">
                <i class="fas fa-file-invoice"></i> Invoices
            </a>
            <a class="nav-link" href="../fees/payments.php">
                <i class="fas fa-credit-card"></i> Payments
            </a>
            <a class="nav-link" href="../payroll/index.php">
                <i class="fas fa-calculator"></i> Payroll
            </a>
            <a class="nav-link" href="../reports/financial.php">
                <i class="fas fa-chart-line"></i> Financial Reports
            </a>
            <hr class="my-2">
            <a class="nav-link" href="../auth/change_password.php">
                <i class="fas fa-key"></i> Change Password
            </a>
            <a class="nav-link text-danger" href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <div>
                <h4 class="mb-0">Accountant Dashboard</h4>
                <p class="text-muted mb-0">Term <?php echo CURRENT_TERM; ?> | <?php echo CURRENT_ACADEMIC_YEAR; ?></p>
            </div>
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($user['username']); ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="../auth/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number"><?php echo formatMoney($total_due); ?></div>
                            <div class="stat-label">Total Fees Due</div>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x text-muted"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-success"><?php echo formatMoney($total_paid); ?></div>
                            <div class="stat-label">Total Collected</div>
                        </div>
                        <i class="fas fa-check-circle fa-2x text-success"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-danger"><?php echo formatMoney($total_balance); ?></div>
                            <div class="stat-label">Outstanding Balance</div>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
                    </div>
                    <div class="collection-bar">
                        <div class="collection-fill" style="width: <?php echo $collection_percentage; ?>%"></div>
                    </div>
                    <small class="text-muted"><?php echo $collection_percentage; ?>% collection rate</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-number text-warning"><?php echo formatMoney($payroll_stats['total_amount'] ?? 0); ?></div>
                            <div class="stat-label">Pending Payroll</div>
                        </div>
                        <i class="fas fa-clock fa-2x text-warning"></i>
                    </div>
                    <div><?php echo $payroll_stats['pending_count'] ?? 0; ?> staff pending</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Collection Chart -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-chart-line"></i> Monthly Fee Collection (<?php echo date('Y'); ?>)
                    </div>
                    <div class="card-body">
                        <canvas id="collectionChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Overdue Summary -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-exclamation-circle"></i> Overdue Summary
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-3">
                            <div class="display-4 text-danger"><?php echo $overdue_stats['count'] ?? 0; ?></div>
                            <div class="text-muted">Overdue Invoices</div>
                        </div>
                        <div class="text-center">
                            <div class="h3"><?php echo formatMoney($overdue_stats['total_balance'] ?? 0); ?></div>
                            <div class="text-muted">Total Overdue Amount</div>
                        </div>
                        <div class="mt-3">
                            <a href="../fees/invoices.php?status=Overdue" class="btn btn-danger w-100">
                                <i class="fas fa-eye"></i> View Overdue Invoices
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Class-wise Collection -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-chalkboard"></i> Class-wise Fee Collection
                    </div>
                    <div class="card-body">
                        <?php foreach ($class_stats as $class): 
                            $class_paid = $class['total_paid'];
                            $class_due = $class['total_due'];
                            $class_percent = $class_due > 0 ? round(($class_paid / $class_due) * 100, 1) : 0;
                        ?>
                            <div class="class-item">
                                <div><strong><?php echo $class['class_name']; ?></strong></div>
                                <div><?php echo formatMoney($class_paid); ?> / <?php echo formatMoney($class_due); ?></div>
                                <div class="text-<?php echo $class_percent >= 80 ? 'success' : ($class_percent >= 50 ? 'warning' : 'danger'); ?>">
                                    <?php echo $class_percent; ?>%
                                </div>
                            </div>
                            <div class="collection-bar mb-2">
                                <div class="collection-fill" style="width: <?php echo $class_percent; ?>%"></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Transactions -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-history"></i> Recent Transactions
                        <a href="../fees/payments.php" class="btn btn-sm btn-primary float-end">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($transactions_result->num_rows > 0): ?>
                            <?php while ($trans = $transactions_result->fetch_assoc()): ?>
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong><?php echo htmlspecialchars($trans['first_name'] . ' ' . $trans['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo $trans['payment_no']; ?></small>
                                        </div>
                                        <div class="text-end">
                                            <span class="text-success fw-bold"><?php echo formatMoney($trans['amount']); ?></span>
                                            <br>
                                            <small class="text-muted"><?php echo formatDate($trans['payment_date']); ?></small>
                                        </div>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-info"><?php echo $trans['payment_method']; ?></span>
                                        <?php if ($trans['reference_no']): ?>
                                            <span class="badge bg-secondary">Ref: <?php echo $trans['reference_no']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-receipt fa-2x text-muted mb-2"></i>
                                <p>No recent transactions.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-bolt"></i> Quick Actions
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <a href="../fees/structure.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-table fa-2x text-primary mb-2"></i>
                                <div>Manage Fee Structure</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../fees/invoices.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-file-invoice fa-2x text-success mb-2"></i>
                                <div>Generate Invoices</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../fees/payments.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-credit-card fa-2x text-info mb-2"></i>
                                <div>Record Payment</div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="../payroll/index.php" class="text-decoration-none">
                            <div class="quick-action">
                                <i class="fas fa-calculator fa-2x text-warning mb-2"></i>
                                <div>Process Payroll</div>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ctx = document.getElementById('collectionChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($months_data); ?>,
                datasets: [{
                    label: 'Fee Collection (UGX)',
                    data: <?php echo json_encode($collections_data); ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.7)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'UGX ' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>