<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

$where = "WHERE n.created_at BETWEEN ? AND ?";
$params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
$types = "ss";

if (!empty($category_filter)) {
    $where .= " AND n.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

$sql = "SELECT n.*, u.username as sent_by_name 
        FROM notifications n
        LEFT JOIN users u ON n.sent_by = u.user_id
        $where
        ORDER BY n.created_at DESC
        LIMIT 100";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications_result = $stmt->get_result();

// Get categories for filter
$cat_sql = "SELECT DISTINCT category FROM notifications";
$cat_result = $conn->query($cat_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History - <?php echo SITE_NAME; ?></title>
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
        .notification-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #3498db;
        }
        .notification-title {
            font-weight: bold;
            font-size: 16px;
        }
        .notification-meta {
            font-size: 12px;
            color: #7f8c8d;
        }
        .status-sent { color: #28a745; }
        .status-failed { color: #dc3545; }
        .status-pending { color: #ffc107; }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-history"></i> Notification History</h4>
            <a href="notifications.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Send New Notification
            </a>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php while ($cat = $cat_result->fetch_assoc()): ?>
                            <option value="<?php echo $cat['category']; ?>" <?php echo ($category_filter == $cat['category']) ? 'selected' : ''; ?>>
                                <?php echo $cat['category']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Notifications List -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Notification History
                <span class="badge bg-secondary float-end"><?php echo $notifications_result->num_rows; ?> records</span>
            </div>
            <div class="card-body">
                <?php if ($notifications_result->num_rows > 0): ?>
                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                        <div class="notification-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <span class="badge bg-secondary ms-2"><?php echo $notification['category']; ?></span>
                                        <span class="badge bg-info ms-1"><?php echo $notification['notification_type']; ?></span>
                                    </div>
                                    <div class="notification-meta">
                                        <i class="fas fa-user"></i> Sent by: <?php echo $notification['sent_by_name']; ?> |
                                        <i class="fas fa-clock"></i> <?php echo formatDate($notification['created_at']); ?> |
                                        <i class="fas fa-users"></i> Recipients: <?php echo ucfirst(str_replace('_', ' ', $notification['recipient_type'])); ?>
                                    </div>
                                    <div class="mt-2">
                                        <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                    </div>
                                </div>
                                <div>
                                    <span class="status-<?php echo strtolower($notification['sent_status']); ?>">
                                        <i class="fas fa-<?php echo $notification['sent_status'] == 'Sent' ? 'check-circle' : ($notification['sent_status'] == 'Failed' ? 'exclamation-circle' : 'clock'); ?>"></i>
                                        <?php echo $notification['sent_status']; ?>
                                    </span>
                                </div>
                            </div>
                            <?php if ($notification['sent_at']): ?>
                                <div class="notification-meta mt-2">
                                    <i class="fas fa-paper-plane"></i> Sent at: <?php echo formatDate($notification['sent_at']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                        <p>No notifications found for the selected period.</p>
                        <a href="notifications.php" class="btn btn-primary">Send Your First Notification</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>