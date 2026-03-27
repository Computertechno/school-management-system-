<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_accountant = ($user['role_name'] == 'accountant');

if (!$is_admin && !$is_accountant) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$error = '';
$success = '';

// Handle manual invoice generation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_invoices'])) {
    $class_id = (int)$_POST['class_id'];
    $term = (int)$_POST['term'];
    $academic_year = $_POST['academic_year'];
    
    // Get fee structure
    $fee_sql = "SELECT * FROM fee_structures WHERE class_id = ? AND term = ? AND academic_year = ? AND is_active = 1";
    $fee_stmt = $conn->prepare($fee_sql);
    $fee_stmt->bind_param("iis", $class_id, $term, $academic_year);
    $fee_stmt->execute();
    $fee_result = $fee_stmt->get_result();
    $fee_structure = $fee_result->fetch_assoc();
    
    if (!$fee_structure) {
        $error = "No active fee structure found for this class, term, and academic year.";
    } else {
        // Get students in class
        $students_sql = "SELECT student_id FROM students WHERE current_class_id = ? AND enrollment_status = 'Active'";
        $students_stmt = $conn->prepare($students_sql);
        $students_stmt->bind_param("i", $class_id);
        $students_stmt->execute();
        $students_result = $students_stmt->get_result();
        
        $generated = 0;
        while ($student = $students_result->fetch_assoc()) {
            // Check if invoice already exists
            $check_sql = "SELECT invoice_id FROM invoices WHERE student_id = ? AND term = ? AND academic_year = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("iis", $student['student_id'], $term, $academic_year);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows == 0) {
                $invoice_no = generateCode('INV', 'invoices', 'invoice_no');
                $amount_due = $fee_structure['total_fee'];
                $due_date = $fee_structure['payment_deadline'];
                
                $insert_sql = "INSERT INTO invoices (invoice_no, student_id, term, academic_year, amount_due, due_date, generated_by) 
                               VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("siisdsi", $invoice_no, $student['student_id'], $term, $academic_year, $amount_due, $due_date, $user['user_id']);
                
                if ($insert_stmt->execute()) {
                    $generated++;
                }
            }
        }
        
        $success = "$generated invoice(s) generated successfully!";
        logActivity($user['user_id'], 'GENERATE_INVOICES', 'invoices', $class_id);
    }
}

// Get filters
$class_filter = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($class_filter > 0) {
    $where .= " AND s.current_class_id = ?";
    $params[] = $class_filter;
    $types .= "i";
}

if (!empty($status_filter)) {
    $where .= " AND i.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $where .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.admission_no LIKE ? OR i.invoice_no LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Get invoices
$sql = "SELECT i.*, s.first_name, s.last_name, s.admission_no, c.class_name
        FROM invoices i
        JOIN students s ON i.student_id = s.student_id
        JOIN classes c ON s.current_class_id = c.class_id
        $where
        ORDER BY i.status ASC, i.due_date ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$invoices_result = $stmt->get_result();

// Get classes for filter
$classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
$classes_result = $conn->query($classes_sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices - <?php echo SITE_NAME; ?></title>
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
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
        }
        .status-Pending { background: #fff3cd; color: #856404; }
        .status-Partial { background: #cce5ff; color: #004085; }
        .status-Paid { background: #d4edda; color: #155724; }
        .status-Overdue { background: #f8d7da; color: #721c24; }
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .btn-generate {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-file-invoice"></i> Fee Invoices</h4>
            <button type="button" class="btn btn-generate" data-bs-toggle="modal" data-bs-target="#generateModal">
                <i class="fas fa-plus-circle"></i> Generate Invoices
            </button>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filter-bar">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="0">All Classes</option>
                        <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" <?php echo ($class_filter == $class['class_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $status_filter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Partial" <?php echo $status_filter == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="Paid" <?php echo $status_filter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Overdue" <?php echo $status_filter == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by student name or invoice number..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <?php if ($class_filter > 0 || $status_filter || $search): ?>
                        <a href="invoices.php" class="btn btn-secondary w-100">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Invoices Table -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Invoice List
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Invoice No</th>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Amount Due</th>
                                <th>Amount Paid</th>
                                <th>Balance</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($invoices_result->num_rows > 0): ?>
                                <?php while ($invoice = $invoices_result->fetch_assoc()): 
                                    $is_overdue = ($invoice['status'] == 'Pending' && strtotime($invoice['due_date']) < strtotime(date('Y-m-d')));
                                    if ($is_overdue && $invoice['status'] != 'Overdue') {
                                        // Update status to overdue
                                        $update_sql = "UPDATE invoices SET status = 'Overdue' WHERE invoice_id = ?";
                                        $update_stmt = $conn->prepare($update_sql);
                                        $update_stmt->bind_param("i", $invoice['invoice_id']);
                                        $update_stmt->execute();
                                        $invoice['status'] = 'Overdue';
                                    }
                                ?>
                                     <tr>
                                        <td><strong><?php echo $invoice['invoice_no']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?><br><small class="text-muted"><?php echo $invoice['admission_no']; ?></small></td>
                                        <td><?php echo $invoice['class_name']; ?></td>
                                        <td><?php echo formatMoney($invoice['amount_due']); ?></td>
                                        <td><?php echo formatMoney($invoice['amount_paid']); ?></td>
                                        <td><strong class="text-<?php echo $invoice['balance'] > 0 ? 'danger' : 'success'; ?>"><?php echo formatMoney($invoice['balance']); ?></strong></td>
                                        <td><?php echo formatDate($invoice['due_date']); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $invoice['status']; ?>">
                                                <?php echo $invoice['status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="payments.php?invoice_id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-success">
                                                <i class="fas fa-credit-card"></i> Pay
                                            </a>
                                            <a href="invoice_view.php?id=<?php echo $invoice['invoice_id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-print"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="fas fa-file-invoice fa-2x text-muted mb-2 d-block"></i>
                                        No invoices found.
                                        <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#generateModal">
                                            Generate Invoices
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Generate Invoices Modal -->
    <div class="modal fade" id="generateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Generate Invoices</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label required">Class</label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php 
                                $classes_result2 = $conn->query($classes_sql);
                                while ($class = $classes_result2->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $class['class_id']; ?>">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Academic Year</label>
                            <input type="text" name="academic_year" class="form-control" value="<?php echo CURRENT_ACADEMIC_YEAR; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label required">Term</label>
                            <select name="term" class="form-select" required>
                                <option value="1">Term 1</option>
                                <option value="2">Term 2</option>
                                <option value="3">Term 3</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> This will generate invoices for all active students in the selected class. Existing invoices will not be duplicated.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="generate_invoices" class="btn btn-primary">Generate Invoices</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>