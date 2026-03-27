<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_librarian = ($user['role_name'] == 'librarian');
$is_admin = ($user['role_name'] == 'admin');

if (!$is_librarian && !$is_admin) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$error = '';
$success = '';

// Handle book return
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['return_book'])) {
    $borrowing_id = (int)$_POST['borrowing_id'];
    
    // Get borrowing details
    $borrow_sql = "SELECT b.*, bk.book_id FROM borrowings b 
                   JOIN books bk ON b.book_id = bk.book_id 
                   WHERE b.borrowing_id = ? AND b.status = 'Borrowed'";
    $borrow_stmt = $conn->prepare($borrow_sql);
    $borrow_stmt->bind_param("i", $borrowing_id);
    $borrow_stmt->execute();
    $borrow = $borrow_stmt->get_result()->fetch_assoc();
    
    if ($borrow) {
        // Calculate fine if overdue
        $fine = 0;
        $due_date = new DateTime($borrow['due_date']);
        $today = new DateTime();
        if ($today > $due_date) {
            $days_overdue = $today->diff($due_date)->days;
            $fine = $days_overdue * 1000; // UGX 1000 per day
        }
        
        // Update borrowing record
        $update_sql = "UPDATE borrowings SET return_date = CURDATE(), status = 'Returned', fine_amount = ? WHERE borrowing_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("di", $fine, $borrowing_id);
        $update_stmt->execute();
        
        // Update book available copies
        $book_sql = "UPDATE books SET available_copies = available_copies + 1,
                     status = 'Available' WHERE book_id = ?";
        $book_stmt = $conn->prepare($book_sql);
        $book_stmt->bind_param("i", $borrow['book_id']);
        $book_stmt->execute();
        
        $success = "Book returned successfully!";
        if ($fine > 0) {
            $success .= " Fine amount: " . formatMoney($fine) . ". Please collect from the borrower.";
        }
        logActivity($user['user_id'], 'RETURN_BOOK', 'borrowings', $borrowing_id);
    } else {
        $error = "Borrowing record not found or already returned.";
    }
}

// Get current borrowings
$borrowings_sql = "SELECT b.*, bk.title, bk.author, bk.isbn,
                   CONCAT(s.first_name, ' ', s.last_name) as student_name,
                   s.admission_no,
                   CONCAT(st.first_name, ' ', st.last_name) as staff_name,
                   st.staff_no,
                   u.username as issued_by_name
                   FROM borrowings b
                   LEFT JOIN books bk ON b.book_id = bk.book_id
                   LEFT JOIN students s ON b.student_id = s.student_id
                   LEFT JOIN staff st ON b.staff_id = st.staff_id
                   LEFT JOIN users u ON b.issued_by = u.user_id
                   WHERE b.status = 'Borrowed'
                   ORDER BY b.due_date ASC";
$borrowings_result = $conn->query($borrowings_sql);

// Get overdue count
$today = date('Y-m-d');
$overdue_sql = "SELECT COUNT(*) as count FROM borrowings WHERE due_date < CURDATE() AND status = 'Borrowed'";
$overdue_result = $conn->query($overdue_sql);
$overdue_count = $overdue_result->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrowings - <?php echo SITE_NAME; ?></title>
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
        .borrowing-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .borrowing-item.overdue {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .due-date {
            font-size: 12px;
        }
        .due-date.overdue {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-hand-holding-heart"></i> Current Borrowings</h4>
            <div>
                <?php if ($overdue_count > 0): ?>
                    <span class="badge bg-danger me-2"><?php echo $overdue_count; ?> Overdue</span>
                <?php endif; ?>
                <a href="books.php" class="btn btn-primary">
                    <i class="fas fa-book"></i> Browse Books
                </a>
            </div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i> Currently Borrowed Books
            </div>
            <div class="card-body">
                <?php if ($borrowings_result->num_rows > 0): ?>
                    <?php while ($borrow = $borrowings_result->fetch_assoc()): 
                        $is_overdue = strtotime($borrow['due_date']) < strtotime(date('Y-m-d'));
                        $borrower_name = $borrow['student_name'] ?: $borrow['staff_name'];
                        $borrower_id = $borrow['admission_no'] ?: $borrow['staff_no'];
                    ?>
                        <div class="borrowing-item <?php echo $is_overdue ? 'overdue' : ''; ?>">
                            <div class="row">
                                <div class="col-md-5">
                                    <div><strong><?php echo htmlspecialchars($borrow['title']); ?></strong></div>
                                    <div class="text-muted small">by <?php echo htmlspecialchars($borrow['author']); ?></div>
                                </div>
                                <div class="col-md-3">
                                    <div><i class="fas fa-user"></i> <?php echo htmlspecialchars($borrower_name); ?></div>
                                    <div class="text-muted small">ID: <?php echo $borrower_id; ?></div>
                                </div>
                                <div class="col-md-2">
                                    <div class="due-date <?php echo $is_overdue ? 'overdue' : ''; ?>">
                                        <i class="fas fa-calendar-alt"></i> Due: <?php echo formatDate($borrow['due_date']); ?>
                                        <?php if ($is_overdue): ?>
                                            <br><span class="badge bg-danger">OVERDUE</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <form method="POST" action="" style="display: inline;">
                                        <input type="hidden" name="borrowing_id" value="<?php echo $borrow['borrowing_id']; ?>">
                                        <button type="submit" name="return_book" class="btn btn-sm btn-success">
                                            <i class="fas fa-undo-alt"></i> Return
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-info" onclick="viewBorrowingDetails(<?php echo htmlspecialchars(json_encode($borrow)); ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-hand-holding-heart fa-3x text-muted mb-3"></i>
                        <p>No books currently borrowed.</p>
                        <a href="books.php" class="btn btn-primary">Browse Books to Borrow</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Borrowing History -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Borrowing History
                <button class="btn btn-sm btn-secondary float-end" onclick="loadHistory()">Refresh</button>
            </div>
            <div class="card-body" id="historyContainer">
                <div class="text-center py-3">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Borrowing Details Modal -->
    <div class="modal fade" id="borrowingDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Borrowing Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="borrowingDetailsContent">
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewBorrowingDetails(borrow) {
            let borrower = borrow.student_name || borrow.staff_name;
            let borrowerId = borrow.admission_no || borrow.staff_no;
            let borrowerType = borrow.student_id ? 'Student' : 'Staff';
            
            let html = `
                <p><strong>Book:</strong> ${borrow.title}</p>
                <p><strong>Author:</strong> ${borrow.author}</p>
                <p><strong>ISBN:</strong> ${borrow.isbn || 'N/A'}</p>
                <hr>
                <p><strong>Borrower:</strong> ${borrower} (${borrowerType})</p>
                <p><strong>ID:</strong> ${borrowerId}</p>
                <p><strong>Borrow Date:</strong> ${borrow.borrow_date}</p>
                <p><strong>Due Date:</strong> ${borrow.due_date}</p>
                <p><strong>Issued By:</strong> ${borrow.issued_by_name}</p>
                <p><strong>Status:</strong> ${borrow.status}</p>
            `;
            document.getElementById('borrowingDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('borrowingDetailsModal')).show();
        }
        
        function loadHistory() {
            fetch('borrowings_history.php')
                .then(response => response.text())
                .then(data => {
                    document.getElementById('historyContainer').innerHTML = data;
                });
        }
        
        // Load history on page load
        loadHistory();
    </script>
</body>
</html>