<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();

if ($user['role_name'] != 'admin') {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$error = '';
$success = '';

if ($student_id <= 0) {
    redirect('../students/index.php');
}

$student_name = getStudentName($student_id);

// Get existing parents for this student
$existing_sql = "SELECT p.parent_id, p.first_name, p.last_name, p.phone, p.email, sp.is_guardian 
                 FROM parents p 
                 JOIN student_parents sp ON p.parent_id = sp.parent_id 
                 WHERE sp.student_id = ?";
$existing_stmt = $conn->prepare($existing_sql);
$existing_stmt->bind_param("i", $student_id);
$existing_stmt->execute();
$existing_parents = $existing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Search parents
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_results = [];

if (!empty($search)) {
    $search_sql = "SELECT parent_id, first_name, last_name, phone, email, relationship 
                   FROM parents 
                   WHERE (first_name LIKE ? OR last_name LIKE ? OR phone LIKE ?) 
                   AND parent_id NOT IN (SELECT parent_id FROM student_parents WHERE student_id = ?)";
    $search_term = "%$search%";
    $search_stmt = $conn->prepare($search_sql);
    $search_stmt->bind_param("sssi", $search_term, $search_term, $search_term, $student_id);
    $search_stmt->execute();
    $search_results = $search_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Handle linking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['link_parent'])) {
    $parent_id = (int)$_POST['parent_id'];
    $is_guardian = isset($_POST['is_guardian']) ? 1 : 0;
    
    $link_sql = "INSERT INTO student_parents (student_id, parent_id, is_guardian) VALUES (?, ?, ?)";
    $link_stmt = $conn->prepare($link_sql);
    $link_stmt->bind_param("iii", $student_id, $parent_id, $is_guardian);
    
    if ($link_stmt->execute()) {
        $success = "Parent linked successfully!";
        logActivity($user['user_id'], 'LINK_PARENT', 'student_parents', $student_id);
        
        // Refresh existing parents
        $existing_stmt->execute();
        $existing_parents = $existing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Failed to link parent.";
    }
}

// Handle unlinking
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['unlink_parent'])) {
    $parent_id = (int)$_POST['parent_id'];
    
    $unlink_sql = "DELETE FROM student_parents WHERE student_id = ? AND parent_id = ?";
    $unlink_stmt = $conn->prepare($unlink_sql);
    $unlink_stmt->bind_param("ii", $student_id, $parent_id);
    
    if ($unlink_stmt->execute()) {
        $success = "Parent unlinked successfully!";
        logActivity($user['user_id'], 'UNLINK_PARENT', 'student_parents', $student_id);
        
        // Refresh existing parents
        $existing_stmt->execute();
        $existing_parents = $existing_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } else {
        $error = "Failed to unlink parent.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Parent to Student - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/master.css">
    <style>
        .parent-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
        }
        .parent-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }
        .existing-parent {
            background: #f8f9fa;
            border-left: 4px solid var(--primary-green);
        }
        .search-result {
            border-left: 4px solid var(--secondary-blue);
        }
        .parent-avatar {
            width: 50px;
            height: 50px;
            background: var(--gradient-primary);
            border-radius: var(--radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .parent-avatar i {
            font-size: 24px;
            color: white;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="dashboard-header">
            <h1><i class="fas fa-link me-3"></i>Link Parent to Student</h1>
            <p>Student: <?php echo htmlspecialchars($student_name); ?> (ID: <?php echo $student_id; ?>)</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Existing Parents -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-users text-primary me-2"></i> Linked Parents
                        <span class="badge bg-primary float-end"><?php echo count($existing_parents); ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (count($existing_parents) > 0): ?>
                            <?php foreach ($existing_parents as $parent): ?>
                                <div class="parent-card existing-parent">
                                    <div class="d-flex align-items-center">
                                        <div class="parent-avatar me-3">
                                            <i class="fas fa-user"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h6>
                                            <small class="text-muted">
                                                <i class="fas fa-phone"></i> <?php echo $parent['phone']; ?>
                                                <?php if ($parent['email']): ?>
                                                    <span class="mx-1">|</span>
                                                    <i class="fas fa-envelope"></i> <?php echo $parent['email']; ?>
                                                <?php endif; ?>
                                            </small>
                                            <div>
                                                <?php if ($parent['is_guardian']): ?>
                                                    <span class="badge bg-info mt-1">Guardian</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary mt-1">Parent</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <form method="POST" action="">
                                            <input type="hidden" name="parent_id" value="<?php echo $parent['parent_id']; ?>">
                                            <button type="submit" name="unlink_parent" class="btn btn-sm btn-danger" onclick="return confirm('Remove this parent from the student?')">
                                                <i class="fas fa-unlink"></i> Remove
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-2x text-muted mb-2"></i>
                                <p>No parents linked to this student yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Search Parents -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-white fw-bold">
                        <i class="fas fa-search text-primary me-2"></i> Search Parents
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="mb-4">
                            <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by name or phone..." value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="btn btn-primary">Search</button>
                            </div>
                        </form>
                        
                        <?php if (!empty($search)): ?>
                            <h6>Search Results:</h6>
                            <?php if (count($search_results) > 0): ?>
                                <?php foreach ($search_results as $parent): ?>
                                    <div class="parent-card search-result">
                                        <div class="d-flex align-items-center">
                                            <div class="parent-avatar me-3">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($parent['first_name'] . ' ' . $parent['last_name']); ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?php echo $parent['phone']; ?>
                                                    <?php if ($parent['email']): ?>
                                                        <span class="mx-1">|</span>
                                                        <i class="fas fa-envelope"></i> <?php echo $parent['email']; ?>
                                                    <?php endif; ?>
                                                </small>
                                                <div><?php echo $parent['relationship']; ?></div>
                                            </div>
                                            <form method="POST" action="">
                                                <input type="hidden" name="parent_id" value="<?php echo $parent['parent_id']; ?>">
                                                <div class="form-check me-2">
                                                    <input type="checkbox" name="is_guardian" class="form-check-input" id="guardian_<?php echo $parent['parent_id']; ?>">
                                                    <label class="form-check-label small" for="guardian_<?php echo $parent['parent_id']; ?>">Guardian</label>
                                                </div>
                                                <button type="submit" name="link_parent" class="btn btn-sm btn-success mt-2">
                                                    <i class="fas fa-link"></i> Link
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> No parents found. 
                                    <a href="../parents/add.php?student_id=<?php echo $student_id; ?>" class="alert-link">Add new parent</a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="mt-3">
                            <a href="../parents/add.php?student_id=<?php echo $student_id; ?>" class="btn btn-outline-primary w-100">
                                <i class="fas fa-plus me-2"></i> Add New Parent
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3">
            <a href="../students/view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Student Profile
            </a>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>