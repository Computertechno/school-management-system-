<?php
/**
 * GAIMS - Greenhill Academy Integrated Management System
 * Configuration File
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Africa/Kampala');

// Session Start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'greenhill_sms');


// Site Configuration
define('SITE_NAME', 'Greenhill Academy');
define('SITE_TAGLINE', 'Excellence in Education Since 1994');
define('SITE_URL', 'http://localhost/gaims/');  // ← MAKE SURE THIS IS CORRECT
define('ADMIN_EMAIL', 'info@greenhill.ac.ug');
define('SCHOOL_PHONE', '+256 414 663680');
define('SCHOOL_EMAIL', 'info@greenhill.ac.ug');


// File Upload Paths
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/gaims/uploads/');
define('PROFILE_PICS_PATH', UPLOAD_PATH . 'profile_pictures/');
define('DOCUMENTS_PATH', UPLOAD_PATH . 'documents/');
define('RECEIPTS_PATH', UPLOAD_PATH . 'receipts/');
define('REPORT_CARDS_PATH', UPLOAD_PATH . 'report_cards/');

// SMS Configuration (Africa's Talking)
define('SMS_ENABLED', true);
define('SMS_USERNAME', 'your_username');
define('SMS_API_KEY', 'your_api_key');
define('SMS_SENDER_ID', 'Greenhill');

// Academic Configuration
define('CURRENT_ACADEMIC_YEAR', '2024/2025');
define('CURRENT_TERM', 1);

// Pagination
define('ITEMS_PER_PAGE', 20);
?>




