<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

// Only admin can manage staff
if ($user['role_name'] != 'admin') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$department_filter = isset($_GET['department']) ? trim($_GET['department']) : '';
$status_filter = isset($_GET['status']) ? (int)$_GET['status'] : 1;
$limit = ITEMS_PER_PAGE;
$offset = ($page - 1) * $limit;

// Build WHERE clause
$where = "WHERE s.is_active = ?";
$params = [$status_filter];
$types = "i";

if (!empty($search)) {
    $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.staff_no LIKE ? OR s.email LIKE ? OR s.phone LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sssss";
}

if (!empty($department_filter)) {
    $where .= " AND s.department = ?";
    $params[] = $department_filter;
    $types .= "s";
}

// Get total count
$count_sql = "SELECT COUNT(*) as total FROM staff s $where";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_staff = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_staff / $limit);

// Get staff
$sql = "SELECT s.*, cp.campus_name,
        (SELECT COUNT(*) FROM employment_records er WHERE er.staff_id = s.staff_id AND er.is_current = 1) as has_current_contract
        FROM staff s
        LEFT JOIN campuses cp ON s.campus_id = cp.campus_id
        $where
        ORDER BY s.last_name ASC
        LIMIT ? OFFSET ?";

$params[] = $limit;
$params[] = $offset;
$types .= "ii";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$staff_result = $stmt->get_result();

// Get departments for filter
$dept_sql = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != '' ORDER BY department";
$dept_result = $conn->query($dept_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - <?php echo SITE_NAME; ?></title>
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
        .staff-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #27ae60;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .search-filters {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .status-badge-active {
            background: #d4edda;
            color: #155724;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-badge-inactive {
            background: #f8d7da;
            color: #721c24;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .btn-action {
            padding: 4px 8px;
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-chalkboard-user"></i> Staff Management</h4>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add New Staff
            </a>
        </div>
        
        <!-- Search and Filters -->
        <div class="search-filters">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, staff no, email, phone..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="department" class="form-select" onchange="this.form.submit()">
                        <option value="">All Departments</option>
                        <?php while ($dept = $dept_result->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($dept['department']); ?>" 
                                <?php echo ($department_filter == $dept['department']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="1" <?php echo ($status_filter == 1) ? 'selected' : ''; ?>>Active Staff</option>
                        <option value="0" <?php echo ($status_filter == 0) ? 'selected' : ''; ?>>Inactive Staff</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <?php if ($search || $department_filter): ?>
                        <a href="index.php?status=<?php echo $status_filter; ?>" class="btn btn-secondary w-100">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Staff Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Staff List
                <span class="badge bg-secondary float-end">Total: <?php echo $total_staff; ?> staff members</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Staff No</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Campus</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($staff_result->num_rows > 0): ?>
                                <?php $counter = $offset + 1; ?>
                                <?php while ($staff = $staff_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $counter++; ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($staff['staff_no']); ?></strong>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="staff-avatar me-2">
                                                    <?php echo strtoupper(substr($staff['first_name'], 0, 1) . substr($staff['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                                    <?php if ($staff['middle_name']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($staff['middle_name']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($staff['position'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['department'] ?? 'Not Assigned'); ?></td>
                                        <td><?php echo htmlspecialchars($staff['campus_name']); ?></td>
                                        <td>
                                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($staff['phone']); ?><br>
                                            <?php if ($staff['email']): ?>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($staff['email']); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($staff['is_active']): ?>
                                                <span class="status-badge-active">
                                                    <i class="fas fa-check-circle"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="status-badge-inactive">
                                                    <i class="fas fa-ban"></i> Inactive
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-info btn-action" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-warning btn-action" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="contract.php?id=<?php echo $staff['staff_id']; ?>" class="btn btn-sm btn-success btn-action" title="Manage Contract">
                                                <i class="fas fa-file-signature"></i>
                                            </a>
                                            <button onclick="toggleStatus(<?php echo $staff['staff_id']; ?>, <?php echo $staff['is_active']; ?>)" class="btn btn-sm btn-secondary btn-action" title="<?php echo $staff['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $staff['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-users fa-2x text-muted mb-2 d-block"></i>
                                        No staff members found.
                                        <a href="add.php" class="btn btn-sm btn-primary mt-2">Add New Staff</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white">
                    <nav>
                        <ul class="pagination justify-content-center mb-0">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo $status_filter; ?>">
                                        Previous
                                    </a>
                                </li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo $status_filter; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>&department=<?php echo urlencode($department_filter); ?>&status=<?php echo $status_filter; ?>">
                                        Next
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function toggleStatus(staffId, currentStatus) {
            const action = currentStatus ? 'deactivate' : 'activate';
            Swal.fire({
                title: 'Are you sure?',
                text: `You are about to ${action} this staff member.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: currentStatus ? '#d33' : '#28a745',
                cancelButtonColor: '#3085d6',
                confirmButtonText: `Yes, ${action} it!`
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `toggle_status.php?id=${staffId}&status=${currentStatus ? 0 : 1}`;
                }
            });
        }
    </script>
</body>
</html>