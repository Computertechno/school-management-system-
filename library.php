<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$page_title = 'Library Reports';
$report_type = isset($_GET['type']) ? $_GET['type'] : 'circulation';
$month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get popular books
$popular_books_sql = "SELECT b.title, b.author, COUNT(br.borrowing_id) as borrow_count
                      FROM books b
                      LEFT JOIN borrowings br ON b.book_id = br.book_id
                      GROUP BY b.book_id
                      ORDER BY borrow_count DESC
                      LIMIT 10";
$popular_books = $conn->query($popular_books_sql)->fetch_all(MYSQLI_ASSOC);

// Get overdue books
$overdue_sql = "SELECT br.*, b.title, b.author,
                CONCAT(s.first_name, ' ', s.last_name) as student_name,
                s.admission_no
                FROM borrowings br
                JOIN books b ON br.book_id = b.book_id
                LEFT JOIN students s ON br.student_id = s.student_id
                WHERE br.due_date < CURDATE() AND br.status = 'Borrowed'
                ORDER BY br.due_date ASC";
$overdue_books = $conn->query($overdue_sql)->fetch_all(MYSQLI_ASSOC);

// Get category distribution
$category_sql = "SELECT category, COUNT(*) as count FROM books GROUP BY category ORDER BY count DESC";
$category_dist = $conn->query($category_sql)->fetch_all(MYSQLI_ASSOC);

// Get monthly borrowing trend
$monthly_sql = "SELECT DATE_FORMAT(borrow_date, '%Y-%m') as month, COUNT(*) as count
                FROM borrowings
                WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                GROUP BY DATE_FORMAT(borrow_date, '%Y-%m')
                ORDER BY month ASC";
$monthly_trend = $conn->query($monthly_sql)->fetch_all(MYSQLI_ASSOC);
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
        .stat-box { background: white; border-radius: 12px; padding: 20px; text-align: center; }
        .stat-number { font-size: 28px; font-weight: bold; color: #2e7d32; }
        .overdue-badge { background: #f8d7da; color: #721c24; padding: 4px 10px; border-radius: 20px; font-size: 12px; }
        .popular-book-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #eee; }
        .borrow-count { background: #2e7d32; color: white; padding: 2px 8px; border-radius: 20px; font-size: 12px; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-book me-2"></i> Library Reports</h4>
            <a href="index.php" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3"><div class="stat-box"><div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0]; ?></div><div>Total Books</div></div></div>
            <div class="col-md-3"><div class="stat-box"><div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) FROM borrowings WHERE status='Borrowed'")->fetch_row()[0]; ?></div><div>Currently Borrowed</div></div></div>
            <div class="col-md-3"><div class="stat-box"><div class="stat-number"><?php echo count($overdue_books); ?></div><div>Overdue Books</div></div></div>
            <div class="col-md-3"><div class="stat-box"><div class="stat-number"><?php echo $conn->query("SELECT COUNT(*) FROM borrowings")->fetch_row()[0]; ?></div><div>Total Borrowings</div></div></div>
        </div>
        
        <div class="row">
            <!-- Popular Books -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-star"></i> Most Popular Books</div>
                    <div class="card-body">
                        <?php foreach ($popular_books as $book): ?>
                        <div class="popular-book-item">
                            <div><strong><?php echo htmlspecialchars($book['title']); ?></strong><br><small class="text-muted">by <?php echo $book['author']; ?></small></div>
                            <span class="borrow-count"><?php echo $book['borrow_count']; ?> borrows</span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Category Distribution Chart -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-pie"></i> Books by Category</div>
                    <div class="card-body"><canvas id="categoryChart" height="250"></canvas></div>
                </div>
            </div>
        </div>
        
        <!-- Overdue Books -->
        <div class="card">
            <div class="card-header"><i class="fas fa-exclamation-triangle text-danger"></i> Overdue Books</div>
            <div class="card-body">
                <?php if (count($overdue_books) > 0): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead> <th>Book Title</th><th>Author</th><th>Borrower</th><th>Due Date</th><th>Days Overdue</th> </thead>
                        <tbody>
                            <?php foreach ($overdue_books as $book): 
                                $days = (new DateTime($book['due_date']))->diff(new DateTime())->days;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo $book['author']; ?></td>
                                <td><?php echo $book['student_name'] ?: 'Staff'; ?></td>
                                <td><?php echo formatDate($book['due_date']); ?></td>
                                <td><span class="overdue-badge"><?php echo $days; ?> days</span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-success">No overdue books! All books are returned on time.</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Monthly Borrowing Trend -->
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line"></i> Monthly Borrowing Trend</div>
            <div class="card-body"><canvas id="trendChart" height="250"></canvas></div>
        </div>
        
        <script>
            // Category Chart
            new Chart(document.getElementById('categoryChart'), {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_column($category_dist, 'category')); ?>,
                    datasets: [{ data: <?php echo json_encode(array_column($category_dist, 'count')); ?>, backgroundColor: ['#2e7d32', '#ffc107', '#17a2b8', '#dc3545', '#6c757d'] }]
                },
                options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
            });
            
            // Trend Chart
            new Chart(document.getElementById('trendChart'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_column($monthly_trend, 'month')); ?>,
                    datasets: [{ label: 'Borrowings', data: <?php echo json_encode(array_column($monthly_trend, 'count')); ?>, borderColor: '#2e7d32', fill: false, tension: 0.4 }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        </script>
    </div>
</body>
</html>