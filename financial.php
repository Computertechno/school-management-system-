<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$page_title = 'Financial Reports';
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get fee collection summary
$fee_summary_sql = "SELECT SUM(amount_due) as total_due, SUM(amount_paid) as total_paid, 
                    SUM(balance) as total_balance
                    FROM invoices WHERE academic_year LIKE ?";
$fee_stmt = $conn->prepare($fee_summary_sql);
$year_param = "%$year%";
$fee_stmt->bind_param("s", $year_param);
$fee_stmt->execute();
$fee_summary = $fee_stmt->get_result()->fetch_assoc();

// Monthly collection data
$monthly_sql = "SELECT MONTH(payment_date) as month, SUM(amount) as total
                FROM payments WHERE YEAR(payment_date) = ?
                GROUP BY MONTH(payment_date) ORDER BY month";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->bind_param("i", $year);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();

$months_data = [];
$collections_data = [];
for ($i = 1; $i <= 12; $i++) {
    $months_data[] = date('F', mktime(0,0,0,$i,1));
    $collections_data[] = 0;
}
while ($row = $monthly_result->fetch_assoc()) {
    $collections_data[$row['month'] - 1] = $row['total'];
}

// Class-wise collection
$class_fee_sql = "SELECT c.class_name, SUM(i.amount_paid) as collected, SUM(i.amount_due) as due
                  FROM invoices i
                  JOIN students s ON i.student_id = s.student_id
                  JOIN classes c ON s.current_class_id = c.class_id
                  WHERE i.academic_year LIKE ?
                  GROUP BY c.class_id";
$class_fee_stmt = $conn->prepare($class_fee_sql);
$class_fee_stmt->bind_param("s", $year_param);
$class_fee_stmt->execute();
$class_fee_result = $class_fee_stmt->get_result();
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
        .stat-number { font-size: 28px; font-weight: bold; color: #2e7d32; }
        .btn-export { background: #2e7d32; color: white; padding: 8px 20px; border-radius: 8px; }
        .collection-bar { height: 8px; background: #e9ecef; border-radius: 4px; overflow: hidden; margin-top: 8px; }
        .collection-fill { height: 100%; background: #28a745; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-money-bill-wave me-2"></i> Financial Reports</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        
        <!-- Filter -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Academic Year</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        <option value="2024/2025" <?php echo $year == '2024/2025' ? 'selected' : ''; ?>>2024/2025</option>
                        <option value="2023/2024" <?php echo $year == '2023/2024' ? 'selected' : ''; ?>>2023/2024</option>
                    </select>
                </div>
            </form>
        </div>
        
        <!-- Summary Statistics -->
        <div class="row mb-4">
            <div class="col-md-4"><div class="stat-box"><div class="stat-number"><?php echo formatMoney($fee_summary['total_due'] ?? 0); ?></div><div>Total Fees Due</div></div></div>
            <div class="col-md-4"><div class="stat-box"><div class="stat-number text-success"><?php echo formatMoney($fee_summary['total_paid'] ?? 0); ?></div><div>Total Collected</div></div></div>
            <div class="col-md-4"><div class="stat-box"><div class="stat-number text-danger"><?php echo formatMoney($fee_summary['total_balance'] ?? 0); ?></div><div>Outstanding Balance</div></div></div>
        </div>
        
        <!-- Collection Chart -->
        <div class="card">
            <div class="card-header">Monthly Fee Collection (<?php echo $year; ?>)</div>
            <div class="card-body">
                <canvas id="collectionChart" height="300"></canvas>
            </div>
        </div>
        
        <!-- Class-wise Collection -->
        <div class="card">
            <div class="card-header">Class-wise Fee Collection</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead><tr><th>Class</th><th>Total Due</th><th>Collected</th><th>Balance</th><th>Collection %</th></tr></thead>
                        <tbody>
                            <?php while ($class = $class_fee_result->fetch_assoc()): 
                                $collected = $class['collected'];
                                $due = $class['due'];
                                $percent = $due > 0 ? round(($collected / $due) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td><?php echo $class['class_name']; ?></td>
                                <td><?php echo formatMoney($due); ?></td>
                                <td><?php echo formatMoney($collected); ?></td>
                                <td><?php echo formatMoney($due - $collected); ?></td>
                                <td>
                                    <?php echo $percent; ?>%
                                    <div class="collection-bar"><div class="collection-fill" style="width: <?php echo $percent; ?>%"></div></div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <script>
            new Chart(document.getElementById('collectionChart'), {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($months_data); ?>,
                    datasets: [{
                        label: 'Fee Collection (UGX)',
                        data: <?php echo json_encode($collections_data); ?>,
                        backgroundColor: '#2e7d32',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    scales: { y: { beginAtZero: true, ticks: { callback: function(v) { return 'UGX ' + (v/1000000).toFixed(1) + 'M'; } } } }
                }
            });
        </script>
    </div>
</body>
</html>