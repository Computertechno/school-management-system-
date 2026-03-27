<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_librarian = ($user['role_name'] == 'librarian');

if (!$is_admin && !$is_librarian) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$error = '';
$success = '';

// Handle book operations
if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($is_admin || $is_librarian)) {
    $action = $_POST['action'];
    
    if ($action == 'add') {
        $isbn = trim($_POST['isbn']);
        $title = trim($_POST['title']);
        $author = trim($_POST['author']);
        $publisher = trim($_POST['publisher']);
        $publication_year = (int)$_POST['publication_year'];
        $edition = trim($_POST['edition']);
        $category = $_POST['category'];
        $subject_id = (int)$_POST['subject_id'];
        $language = $_POST['language'];
        $total_copies = (int)$_POST['total_copies'];
        $location = trim($_POST['location']);
        $purchase_date = $_POST['purchase_date'];
        $purchase_price = (float)$_POST['purchase_price'];
        
        if (empty($title) || empty($author) || $total_copies <= 0) {
            $error = "Please fill all required fields.";
        } else {
            $sql = "INSERT INTO books (isbn, title, author, publisher, publication_year, edition, category, 
                    subject_id, language, total_copies, available_copies, location, purchase_date, purchase_price) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssssissssiiisd", $isbn, $title, $author, $publisher, $publication_year, $edition, 
                              $category, $subject_id, $language, $total_copies, $total_copies, $location, 
                              $purchase_date, $purchase_price);
            
            if ($stmt->execute()) {
                $success = "Book added successfully!";
                logActivity($user['user_id'], 'CREATE', 'books', $stmt->insert_id);
            } else {
                $error = "Failed to add book: " . $conn->error;
            }
        }
    } elseif ($action == 'update') {
        $book_id = (int)$_POST['book_id'];
        $total_copies = (int)$_POST['total_copies'];
        $location = trim($_POST['location']);
        $status = $_POST['status'];
        
        $sql = "UPDATE books SET total_copies = ?, location = ?, status = ? WHERE book_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("issi", $total_copies, $location, $status, $book_id);
        
        if ($stmt->execute()) {
            $success = "Book updated successfully!";
            logActivity($user['user_id'], 'UPDATE', 'books', $book_id);
        } else {
            $error = "Failed to update book.";
        }
    }
}

// Get books with filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $where .= " AND (title LIKE ? OR author LIKE ? OR isbn LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($category_filter)) {
    $where .= " AND category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if (!empty($status_filter)) {
    $where .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql = "SELECT * FROM books $where ORDER BY title ASC";
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$books_result = $stmt->get_result();

// Get categories for filter
$categories = ['Fiction', 'Non-fiction', 'Textbook', 'Reference', 'Children', 'Biography', 'Science', 'History', 'Literature', 'Other'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Catalog - <?php echo SITE_NAME; ?></title>
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
        .book-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #eee;
            transition: transform 0.2s;
        }
        .book-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .book-title {
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        .book-author {
            color: #7f8c8d;
            font-size: 14px;
        }
        .status-available { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .status-borrowed { background: #fff3cd; color: #856404; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .status-damaged { background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 12px; font-size: 11px; }
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .modal-header {
            background: #2c3e50;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-book"></i> Library Catalog</h4>
            <?php if ($is_admin || $is_librarian): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-plus"></i> Add New Book
                </button>
            <?php endif; ?>
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
                <div class="col-md-4">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by title, author, or ISBN..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="category" class="form-select" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat; ?>" <?php echo ($category_filter == $cat) ? 'selected' : ''; ?>>
                                <?php echo $cat; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Available" <?php echo ($status_filter == 'Available') ? 'selected' : ''; ?>>Available</option>
                        <option value="Borrowed" <?php echo ($status_filter == 'Borrowed') ? 'selected' : ''; ?>>Borrowed</option>
                        <option value="Damaged" <?php echo ($status_filter == 'Damaged') ? 'selected' : ''; ?>>Damaged</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <?php if ($search || $category_filter || $status_filter): ?>
                        <a href="books.php" class="btn btn-secondary w-100">Clear Filters</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Books Grid -->
        <div class="row">
            <?php if ($books_result->num_rows > 0): ?>
                <?php while ($book = $books_result->fetch_assoc()): ?>
                    <div class="col-md-4">
                        <div class="book-card">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                    <div class="book-author">by <?php echo htmlspecialchars($book['author']); ?></div>
                                </div>
                                <span class="status-<?php echo strtolower($book['status']); ?>">
                                    <?php echo $book['status']; ?>
                                </span>
                            </div>
                            <div class="mt-2">
                                <div><small><i class="fas fa-barcode"></i> ISBN: <?php echo $book['isbn'] ?: 'N/A'; ?></small></div>
                                <div><small><i class="fas fa-tag"></i> Category: <?php echo $book['category']; ?></small></div>
                                <div><small><i class="fas fa-copy"></i> Copies: <?php echo $book['available_copies']; ?>/<?php echo $book['total_copies']; ?></small></div>
                                <div><small><i class="fas fa-map-marker-alt"></i> Location: <?php echo $book['location'] ?: 'Not specified'; ?></small></div>
                            </div>
                            <div class="mt-3 d-flex justify-content-between">
                                <a href="borrow.php?book_id=<?php echo $book['book_id']; ?>" class="btn btn-sm btn-success <?php echo ($book['available_copies'] <= 0) ? 'disabled' : ''; ?>">
                                    <i class="fas fa-hand-holding-heart"></i> Borrow
                                </a>
                                <button class="btn btn-sm btn-info" onclick="viewBookDetails(<?php echo htmlspecialchars(json_encode($book)); ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                <?php if ($is_admin || $is_librarian): ?>
                                    <button class="btn btn-sm btn-warning" onclick="editBook(<?php echo $book['book_id']; ?>, <?php echo $book['total_copies']; ?>, '<?php echo addslashes($book['location']); ?>', '<?php echo $book['status']; ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-book-open fa-3x text-muted mb-3"></i>
                            <p>No books found in the catalog.</p>
                            <?php if ($is_admin || $is_librarian): ?>
                                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                                    <i class="fas fa-plus"></i> Add First Book
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle"></i> Add New Book</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">ISBN</label>
                                <input type="text" name="isbn" class="form-control" placeholder="978-3-16-148410-0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Author</label>
                                <input type="text" name="author" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Publisher</label>
                                <input type="text" name="publisher" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Publication Year</label>
                                <input type="number" name="publication_year" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Edition</label>
                                <input type="text" name="edition" class="form-control">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label required">Category</label>
                                <select name="category" class="form-select" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Language</label>
                                <select name="language" class="form-select">
                                    <option value="English">English</option>
                                    <option value="French">French</option>
                                    <option value="Swahili">Swahili</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Total Copies</label>
                                <input type="number" name="total_copies" class="form-control" value="1" min="1" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <input type="text" name="location" class="form-control" placeholder="e.g., Shelf A1, Section 2">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" name="purchase_date" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Price (UGX)</label>
                                <input type="number" name="purchase_price" class="form-control" step="1000">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Book Details Modal -->
    <div class="modal fade" id="bookDetailsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Book Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="bookDetailsContent">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Book Modal -->
    <div class="modal fade" id="editBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Book</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="book_id" id="edit_book_id">
                        <div class="mb-3">
                            <label class="form-label">Total Copies</label>
                            <input type="number" name="total_copies" id="edit_total_copies" class="form-control" min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Location</label>
                            <input type="text" name="location" id="edit_location" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="Available">Available</option>
                                <option value="Borrowed">Borrowed</option>
                                <option value="Damaged">Damaged</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Book</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewBookDetails(book) {
            let html = `
                <p><strong>Title:</strong> ${book.title}</p>
                <p><strong>Author:</strong> ${book.author}</p>
                <p><strong>ISBN:</strong> ${book.isbn || 'N/A'}</p>
                <p><strong>Publisher:</strong> ${book.publisher || 'N/A'}</p>
                <p><strong>Publication Year:</strong> ${book.publication_year || 'N/A'}</p>
                <p><strong>Edition:</strong> ${book.edition || 'N/A'}</p>
                <p><strong>Category:</strong> ${book.category}</p>
                <p><strong>Language:</strong> ${book.language}</p>
                <p><strong>Total Copies:</strong> ${book.total_copies}</p>
                <p><strong>Available Copies:</strong> ${book.available_copies}</p>
                <p><strong>Location:</strong> ${book.location || 'Not specified'}</p>
                <p><strong>Purchase Date:</strong> ${book.purchase_date || 'N/A'}</p>
                <p><strong>Purchase Price:</strong> ${book.purchase_price ? 'UGX ' + book.purchase_price.toLocaleString() : 'N/A'}</p>
                <p><strong>Status:</strong> ${book.status}</p>
            `;
            document.getElementById('bookDetailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('bookDetailsModal')).show();
        }
        
        function editBook(bookId, totalCopies, location, status) {
            document.getElementById('edit_book_id').value = bookId;
            document.getElementById('edit_total_copies').value = totalCopies;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_status').value = status;
            new bootstrap.Modal(document.getElementById('editBookModal')).show();
        }
    </script>
</body>
</html>