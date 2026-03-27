<?php
/**
 * GAIMS - Helper Functions
 */

require_once 'db_connection.php';

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

/**
 * Display alert message
 */
function showAlert($message, $type = 'success') {
    $alertClass = ($type == 'success') ? 'alert-success' : (($type == 'error') ? 'alert-danger' : 'alert-info');
    return "<div class='alert $alertClass alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
            </div>";
}

/**
 * Get current user data
 */
function getCurrentUser() {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT u.*, r.role_name 
            FROM users u 
            LEFT JOIN roles r ON u.role_id = r.role_id 
            WHERE u.user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

/**
 * Check if user has permission
 */
function hasPermission($permission_name) {
    global $conn;
    
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $user = getCurrentUser();
    $role_id = $user['role_id'];
    
    $sql = "SELECT p.perm_name 
            FROM role_permissions rp 
            JOIN permissions p ON rp.perm_id = p.perm_id 
            WHERE rp.role_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $role_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        if ($row['perm_name'] == $permission_name) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate unique code
 */
function generateCode($prefix, $table, $column) {
    global $conn;
    
    $year = date('Y');
    $sql = "SELECT MAX(CAST(SUBSTRING($column, -4) AS UNSIGNED)) as max_num 
            FROM $table 
            WHERE $column LIKE '{$prefix}/{$year}/%'";
    
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    $next_num = ($row['max_num'] ?? 0) + 1;
    
    return sprintf("%s/%d/%04d", $prefix, $year, $next_num);
}

/**
 * Get student full name
 */
function getStudentName($student_id) {
    global $conn;
    
    $sql = "SELECT first_name, last_name FROM students WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['first_name'] . ' ' . $row['last_name'];
    }
    
    return 'Unknown';
}

/**
 * Get class name by ID
 */
function getClassName($class_id) {
    global $conn;
    
    $sql = "SELECT class_name FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['class_name'];
    }
    
    return 'Unknown';
}

/**
 * Calculate grade based on percentage
 */
function calculateGrade($percentage, $class_level) {
    global $conn;
    
    $sql = "SELECT grade FROM grade_scales 
            WHERE class_level = ? 
            AND ? BETWEEN min_percentage AND max_percentage 
            AND is_active = 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $class_level, $percentage);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['grade'];
    }
    
    return 'F';
}

/**
 * Send SMS via Africa's Talking API
 */
function sendSMS($phone, $message) {
    if (!SMS_ENABLED) {
        return false;
    }
    
    // Format phone number to Ugandan format
    $phone = preg_replace('/^0/', '+256', $phone);
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.africastalking.com/version1/messaging',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . SMS_API_KEY
        ),
        CURLOPT_POSTFIELDS => http_build_query(array(
            'username' => SMS_USERNAME,
            'to' => $phone,
            'message' => $message,
            'from' => SMS_SENDER_ID
        ))
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    $result = json_decode($response, true);
    return isset($result['SMSMessageData']['Recipients'][0]['status']) && 
           $result['SMSMessageData']['Recipients'][0]['status'] == 'success';
}

/**
 * Log system activity
 */
function logActivity($user_id, $action, $entity, $entity_id, $old_value = null, $new_value = null) {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    $sql = "INSERT INTO audit_logs (user_id, action, entity, entity_id, old_value, new_value, ip_address) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ississs", $user_id, $action, $entity, $entity_id, $old_value, $new_value, $ip_address);
    return $stmt->execute();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect('modules/auth/login.php');
    }
}

/**
 * Require specific role
 */
function requireRole($role_name) {
    requireLogin();
    $user = getCurrentUser();
    if ($user['role_name'] != $role_name && $user['role_name'] != 'admin') {
        redirect('modules/dashboard/' . $user['role_name'] . '.php');
    }
}

/**
 * Format currency
 */
function formatMoney($amount) {
    return 'UGX ' . number_format($amount, 0);
}

/**
 * Format date
 */
function formatDate($date) {
    if (!$date) return '';
    return date('d M Y', strtotime($date));
}
?>