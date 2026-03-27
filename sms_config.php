<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');

if (!$is_admin) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$error = '';
$success = '';

// Handle SMS configuration update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_config'])) {
    $username = trim($_POST['username']);
    $api_key = trim($_POST['api_key']);
    $sender_id = trim($_POST['sender_id']);
    $enabled = isset($_POST['enabled']) ? 1 : 0;
    
    // Update config file
    $config_file = '../../includes/config.php';
    $config_content = file_get_contents($config_file);
    
    // Update SMS configuration values
    $config_content = preg_replace("/define\('SMS_USERNAME', '.*'\);/", "define('SMS_USERNAME', '$username');", $config_content);
    $config_content = preg_replace("/define\('SMS_API_KEY', '.*'\);/", "define('SMS_API_KEY', '$api_key');", $config_content);
    $config_content = preg_replace("/define\('SMS_SENDER_ID', '.*'\);/", "define('SMS_SENDER_ID', '$sender_id');", $config_content);
    $config_content = preg_replace("/define\('SMS_ENABLED', (true|false)\);/", "define('SMS_ENABLED', " . ($enabled ? 'true' : 'false') . ");", $config_content);
    
    if (file_put_contents($config_file, $config_content)) {
        $success = "SMS configuration saved successfully!";
        logActivity($user['user_id'], 'UPDATE', 'sms_config', 0);
    } else {
        $error = "Failed to save SMS configuration.";
    }
}

// Test SMS functionality
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_sms'])) {
    $test_phone = trim($_POST['test_phone']);
    $test_message = "Greenhill Academy: This is a test SMS from GAIMS. If you receive this, SMS configuration is working correctly.";
    
    if (sendSMS($test_phone, $test_message)) {
        $success = "Test SMS sent successfully to $test_phone!";
    } else {
        $error = "Failed to send test SMS. Please check your API credentials.";
    }
}

// Get current SMS statistics
$stats_sql = "SELECT COUNT(*) as total_sent, 
              SUM(CASE WHEN sent_status = 'Sent' THEN 1 ELSE 0 END) as delivered,
              SUM(CASE WHEN sent_status = 'Failed' THEN 1 ELSE 0 END) as failed
              FROM notifications 
              WHERE notification_type = 'SMS'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Configuration - <?php echo SITE_NAME; ?></title>
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
        .config-status {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .status-enabled {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        .status-disabled {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        .stat-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
        }
        .stat-number {
            font-size: 28px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-sms"></i> SMS Configuration</h4>
            <a href="notifications.php" class="btn btn-primary">
                <i class="fas fa-bell"></i> Send Notifications
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Current Status -->
        <div class="config-status <?php echo SMS_ENABLED ? 'status-enabled' : 'status-disabled'; ?>">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-<?php echo SMS_ENABLED ? 'check-circle' : 'exclamation-circle'; ?> fa-2x me-3"></i>
                    <strong>SMS Service Status:</strong> <?php echo SMS_ENABLED ? 'Enabled' : 'Disabled'; ?>
                </div>
                <div>
                    <small>Provider: Africa's Talking | Sender ID: <?php echo SMS_SENDER_ID; ?></small>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number"><?php echo number_format($stats['total_sent'] ?? 0); ?></div>
                    <div class="text-muted">Total SMS Sent</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number text-success"><?php echo number_format($stats['delivered'] ?? 0); ?></div>
                    <div class="text-muted">Delivered</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <div class="stat-number text-danger"><?php echo number_format($stats['failed'] ?? 0); ?></div>
                    <div class="text-muted">Failed</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- SMS Configuration Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-cog"></i> Africa's Talking API Settings
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input type="checkbox" name="enabled" class="form-check-input" id="sms_enabled" <?php echo SMS_ENABLED ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="sms_enabled">Enable SMS Service</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" name="username" class="form-control" value="<?php echo SMS_USERNAME; ?>" required>
                                <small class="text-muted">Your Africa's Talking username (usually your email)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">API Key</label>
                                <input type="password" name="api_key" class="form-control" value="<?php echo SMS_API_KEY; ?>" required>
                                <small class="text-muted">Get your API key from the Africa's Talking dashboard</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sender ID</label>
                                <input type="text" name="sender_id" class="form-control" value="<?php echo SMS_SENDER_ID; ?>" required>
                                <small class="text-muted">This will appear as the sender name on recipients' phones</small>
                            </div>
                            <button type="submit" name="save_config" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Configuration
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Test SMS Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-vial"></i> Test SMS
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Test Phone Number</label>
                                <input type="tel" name="test_phone" class="form-control" placeholder="e.g., 07XXXXXXXX" required>
                                <small class="text-muted">Include country code for international numbers</small>
                            </div>
                            <button type="submit" name="test_sms" class="btn btn-info" <?php echo !SMS_ENABLED ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane"></i> Send Test SMS
                            </button>
                        </form>
                        
                        <hr>
                        
                        <div class="alert alert-info mt-3">
                            <i class="fas fa-info-circle"></i> <strong>SMS Templates:</strong>
                            <ul class="mt-2 mb-0">
                                <li>Fee Reminder: "Dear Parent, fees of UGX [amount] for [student] are due on [date]. Balance: UGX [balance]"</li>
                                <li>Absence Alert: "Your child [student] was absent on [date]. Reason: [reason]"</li>
                                <li>Report Card Ready: "Report card for [student] is now available on the parent portal."</li>
                                <li>Event Notification: "[Event name] will be held on [date] at [time]. Your attendance is appreciated."</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>