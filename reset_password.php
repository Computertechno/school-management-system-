<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$error = '';
$success = '';

// Verify token
$valid = false;
$email = '';
if (!empty($token)) {
    $sql = "SELECT email FROM password_resets WHERE token = ? AND expires_at > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $valid = true;
        $email = $result->fetch_assoc()['email'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid) {
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    
    if (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $hashed = password_hash($password, PASSWORD_BCRYPT);
        $update_sql = "UPDATE users SET password = ? WHERE email = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $hashed, $email);
        $update_stmt->execute();
        
        // Delete used token
        $delete_sql = "DELETE FROM password_resets WHERE token = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("s", $token);
        $delete_stmt->execute();
        
        $success = "Password reset successfully! You can now login.";
        header("refresh:3;url=login.php");
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Greenhill Academy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/master.css">
</head>
<body>
    <div class="forgot-wrapper">
        <div class="forgot-card">
            <div class="forgot-header">
                <i class="fas fa-lock"></i>
                <h2>Reset Password</h2>
                <?php if ($valid): ?>
                    <p class="text-muted">Create a new password for your account</p>
                <?php else: ?>
                    <p class="text-danger">Invalid or expired reset link.</p>
                <?php endif; ?>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($valid && !$success): ?>
                <form method="POST" action="">
                    <div class="input-group-custom mb-3">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" class="form-control" placeholder="New Password" required>
                    </div>
                    <div class="input-group-custom mb-4">
                        <i class="fas fa-check-circle"></i>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Confirm Password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-save me-2"></i> Reset Password
                    </button>
                </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php" class="text-decoration-none">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>