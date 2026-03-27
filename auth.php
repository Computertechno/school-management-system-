<?php
/**
 * GAIMS - Authentication Handler
 */

require_once 'db_connection.php';
require_once 'functions.php';

class Auth {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Check if username exists
        $sql = "SELECT user_id, username, password, role_id, is_active 
                FROM users 
                WHERE username = ? OR email = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 0) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        $user = $result->fetch_assoc();
        
        // Check if account is active
        if (!$user['is_active']) {
            return ['success' => false, 'message' => 'Account is deactivated. Contact administrator.'];
        }
        
        // Verify password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => 'Invalid username or password'];
        }
        
        // Update last login
        $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
        $update_stmt = $this->conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user['user_id']);
        $update_stmt->execute();
        
        // Set session
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role_id'] = $user['role_id'];
        
        // Log activity
        logActivity($user['user_id'], 'LOGIN', 'users', $user['user_id']);
        
        // Get role name for redirect
        $role_sql = "SELECT role_name FROM roles WHERE role_id = ?";
        $role_stmt = $this->conn->prepare($role_sql);
        $role_stmt->bind_param("i", $user['role_id']);
        $role_stmt->execute();
        $role_result = $role_stmt->get_result();
        $role = $role_result->fetch_assoc();
        
        return ['success' => true, 'role' => $role['role_name']];
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'LOGOUT', 'users', $_SESSION['user_id']);
        }
        session_destroy();
        return true;
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        // Get current password
        $sql = "SELECT password FROM users WHERE user_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        // Hash new password
        $new_hashed = password_hash($new_password, PASSWORD_BCRYPT);
        
        // Update password
        $update_sql = "UPDATE users SET password = ? WHERE user_id = ?";
        $update_stmt = $this->conn->prepare($update_sql);
        $update_stmt->bind_param("si", $new_hashed, $user_id);
        
        if ($update_stmt->execute()) {
            logActivity($user_id, 'CHANGE_PASSWORD', 'users', $user_id);
            return ['success' => true, 'message' => 'Password changed successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to change password'];
    }
}
?>