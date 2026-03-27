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

$book_id = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$staff_id = isset($_GET['staff_id']) ? (int)$_GET['staff_id'] : 0;
$borrower_type = isset($_GET['type']) ? $_GET['type'] : 'student';

$error = '';
$success = '';

// Get book details
$book = null;
if ($book_id > 0) {
    $book_sql = "SELECT * FROM books WHERE book_id = ? AND available_copies > 0";
    $book_stmt = $conn->prepare($book_sql);
    $book_stmt->bind_param("i", $book_id);
    $book_stmt->execute();
    $book_result = $book_stmt->get_result();
    $book = $book_result->fetch_assoc();
}

// Search students/staff
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_results = [];
if (!empty($search)) {
    if ($borrower_type == 'student') {
        $search_sql = "SELECT student_id as id, admission_no as code, first_name, last_name, 'student' as type, 
                       current_class_id, (SELECT class_name FROM classes WHERE class_id = students.current_class_id) as class_name
                       FROM students 
                       WHERE (first_name LIKE ? OR last_name LIKE ? OR admission_no LIKE ?) AND enrollment_status = 'Active'
                       LIMIT 10";
        $search_stmt = $conn->prepare($search_sql);
        $search_term = "%$search%";
        $search_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    } else {
        $search_sql = "SELECT staff_id as id, staff_no as code, first_name, last_name, 'staff' as type, 
                       position, department
                       FROM staff 
                       WHERE (first_name LIKE ? OR last_name LIKE ? OR staff_no LIKE ?) AND is_active = 1
                       LIMIT 10";
        $search_stmt = $conn->prepare($search_sql);
        $search_term = "%$search%";
        $search_stmt->bind_param("sss", $search_term, $search_term, $search_term);
    }
    $search_stmt->execute();
    $search_results = $search_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle borrowing
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['borrow_book'])) {
    $book_id = (int)$_POST['book_id'];
    $borrower_id = (int)$_POST['borrower_id'];
    $borrower_type = $_POST['borrower_type'];
    $due_date = $_POST['due_date'];
    
    // Check book availability
    $check_sql = "SELECT available_copies FROM books WHERE book_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("i", $book_id);
    $check_stmt->execute();
    $book_check = $check_stmt->get_result()->fetch_assoc();
    
    if ($book_check['available_copies'] <= 0) {
        $error = "This book is currently unavailable for borrowing.";
    } else {
        $borrowing_no = generateCode('BRW', 'borrowings', 'borrowing_no');
        
        $sql = "INSERT INTO borrowings (borrowing_no, ";
        if ($borrower_type == 'student') {
            $sql .= "student_id, ";
        } else {
            $sql .= "staff_id, ";
        }
        $sql .= "book_id, borrow_date, due_date, issued_by) 
                 VALUES (?, ?, ?, CURDATE(), ?, ?)";
        
        $stmt = $conn->prepare($sql);
        if ($borrower_type == 'student') {
            $stmt->bind_param("siiii", $borrowing_no, $borrower_id, $book_id, $due_date, $user['user_id']);
        } else {
            $stmt->bind_param("siiii", $borrowing_no, $borrower_id, $book_id, $due_date, $user['user_id']);
        }
        
        if ($stmt->execute()) {
            // Update book available copies
            $update_sql = "UPDATE books SET available_copies = available_copies - 1,
                           status = CASE WHEN available_copies - 1 <= 0 THEN 'Borrowed' ELSE 'Available' END 
                           WHERE book_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("i", $book_id);
            $update_stmt->execute();
            
            $success = "Book borrowed successfully!";
            logActivity($user['user_id'], 'BORROW_BOOK', 'borrowings', $stmt->insert_id);
            
            // Redirect to borrowings list
            header("Location: borrowings.php");
            exit;
        } else {
            $error = "Failed to borrow book.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Borrow Book - <?php echo SITE_NAME; ?></title>
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
        .borrower-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .borrower-item:hover {
            background: #e9ecef;
        }
        .borrower-item.selected {
            background: #d4edda;
            border-left: 3px solid #28a745;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-hand-holding-heart"></i> Borrow Book</h4>
            <a href="books.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Catalog
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($book): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-book"></i> Book Details
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Title:</strong> <?php echo htmlspecialchars($book['title']); ?></p>
                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                            <p><strong>ISBN:</strong> <?php echo $book['isbn'] ?: 'N/A'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Category:</strong> <?php echo $book['category']; ?></p>
                            <p><strong>Available Copies:</strong> <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?></p>
                            <p><strong>Location:</strong> <?php echo $book['location'] ?: 'Not specified'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user"></i> Select Borrower
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="btn-group">
                            <a href="?book_id=<?php echo $book_id; ?>&type=student" class="btn <?php echo $borrower_type == 'student' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-user-graduate"></i> Student
                            </a>
                            <a href="?book_id=<?php echo $book_id; ?>&type=staff" class="btn <?php echo $borrower_type == 'staff' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                <i class="fas fa-chalkboard-user"></i> Staff
                            </a>
                        </div>
                    </div>
                    
                    <form method="GET" action="" class="mb-4">
                        <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                        <input type="hidden" name="type" value="<?php echo $borrower_type; ?>">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search by name or ID..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>
                    
                    <?php if (!empty($search)): ?>
                        <h6>Search Results:</h6>
                        <div class="borrower-list">
                            <?php if (count($search_results) > 0): ?>
                                <?php foreach ($search_results as $borrower): ?>
                                    <div class="borrower-item" onclick="selectBorrower(<?php echo $borrower['id']; ?>, '<?php echo $borrower_type; ?>')">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo htmlspecialchars($borrower['first_name'] . ' ' . $borrower['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted">ID: <?php echo $borrower['code']; ?></small>
                                                <?php if ($borrower_type == 'student'): ?>
                                                    <small class="text-muted"> | Class: <?php echo $borrower['class_name']; ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted"> | <?php echo $borrower['position']; ?></small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-success" onclick="event.stopPropagation(); selectBorrower(<?php echo $borrower['id']; ?>, '<?php echo $borrower_type; ?>')">
                                                    Select
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No <?php echo $borrower_type; ?>s found.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Borrow Form -->
                    <form method="POST" action="" id="borrowForm" style="display: none;">
                        <input type="hidden" name="book_id" value="<?php echo $book_id; ?>">
                        <input type="hidden" name="borrower_id" id="borrower_id">
                        <input type="hidden" name="borrower_type" id="borrower_type" value="<?php echo $borrower_type; ?>">
                        <input type="hidden" name="borrow_book" value="1">
                        
                        <div class="mt-4">
                            <div class="alert alert-info" id="selected_borrower_info"></div>
                            
                            <div class="mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" required>
                                <small class="text-muted">Books are due 14 days from borrowing date.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check"></i> Confirm Borrowing
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-book fa-3x text-muted mb-3"></i>
                    <p>No book selected or book is currently unavailable.</p>
                    <a href="books.php" class="btn btn-primary">Browse Books</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectBorrower(id, type) {
            document.getElementById('borrower_id').value = id;
            document.getElementById('borrower_type').value = type;
            document.getElementById('borrowForm').style.display = 'block';
            
            // Get borrower name from selected item
            let selectedItem = event.target.closest('.borrower-item');
            let name = selectedItem.querySelector('strong').innerText;
            document.getElementById('selected_borrower_info').innerHTML = `Selected: <strong>${name}</strong> (${type.toUpperCase()})`;
            
            // Highlight selected
            document.querySelectorAll('.borrower-item').forEach(item => {
                item.classList.remove('selected');
            });
            selectedItem.classList.add('selected');
        }
    </script>
</body>
</html>