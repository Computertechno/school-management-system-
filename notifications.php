<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_teacher = ($user['role_name'] == 'teacher');

$error = '';
$success = '';

// Get classes for filter
$classes_sql = "SELECT class_id, class_name FROM classes WHERE is_active = 1 ORDER BY class_name";
$classes_result = $conn->query($classes_sql);

// Get departments for staff filter
$dept_sql = "SELECT DISTINCT department FROM staff WHERE department IS NOT NULL AND department != ''";
$dept_result = $conn->query($dept_sql);

// Handle sending notifications
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_notification'])) {
    $notification_type = $_POST['notification_type'];
    $recipient_type = $_POST['recipient_type'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $category = $_POST['category'];
    $class_id = isset($_POST['class_id']) ? (int)$_POST['class_id'] : 0;
    $department = isset($_POST['department']) ? $_POST['department'] : '';
    
    if (empty($title) || empty($message)) {
        $error = "Please enter both title and message.";
    } else {
        $sent_count = 0;
        $recipients = [];
        
        // Get recipients based on type
        if ($recipient_type == 'all_parents') {
            $parent_sql = "SELECT DISTINCT p.parent_id, p.phone, p.email FROM parents p 
                           JOIN student_parents sp ON p.parent_id = sp.parent_id";
            $parent_result = $conn->query($parent_sql);
            while ($parent = $parent_result->fetch_assoc()) {
                $recipients[] = $parent;
            }
        } elseif ($recipient_type == 'class_parents') {
            $parent_sql = "SELECT DISTINCT p.parent_id, p.phone, p.email FROM parents p 
                           JOIN student_parents sp ON p.parent_id = sp.parent_id 
                           JOIN students s ON sp.student_id = s.student_id 
                           WHERE s.current_class_id = ?";
            $parent_stmt = $conn->prepare($parent_sql);
            $parent_stmt->bind_param("i", $class_id);
            $parent_stmt->execute();
            $parent_result = $parent_stmt->get_result();
            while ($parent = $parent_result->fetch_assoc()) {
                $recipients[] = $parent;
            }
        } elseif ($recipient_type == 'all_staff') {
            $staff_sql = "SELECT staff_id, phone, email FROM staff WHERE is_active = 1";
            $staff_result = $conn->query($staff_sql);
            while ($staff = $staff_result->fetch_assoc()) {
                $recipients[] = $staff;
            }
        } elseif ($recipient_type == 'department_staff') {
            $staff_sql = "SELECT staff_id, phone, email FROM staff WHERE is_active = 1 AND department = ?";
            $staff_stmt = $conn->prepare($staff_sql);
            $staff_stmt->bind_param("s", $department);
            $staff_stmt->execute();
            $staff_result = $staff_stmt->get_result();
            while ($staff = $staff_result->fetch_assoc()) {
                $recipients[] = $staff;
            }
        }
        
        // Send notifications
        foreach ($recipients as $recipient) {
            // Record notification
            $sql = "INSERT INTO notifications (notification_type, recipient_type, title, message, category, sent_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssssi", $notification_type, $recipient_type, $title, $message, $category, $user['user_id']);
            $stmt->execute();
            $notification_id = $stmt->insert_id;
            
            // Send SMS if type is SMS
            if ($notification_type == 'SMS' && SMS_ENABLED && !empty($recipient['phone'])) {
                $sms_sent = sendSMS($recipient['phone'], $message);
                $update_sql = "UPDATE notifications SET sent_status = ?, sent_at = NOW() WHERE notification_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $status = $sms_sent ? 'Sent' : 'Failed';
                $update_stmt->bind_param("si", $status, $notification_id);
                $update_stmt->execute();
                if ($sms_sent) $sent_count++;
            } elseif ($notification_type == 'Email' && !empty($recipient['email'])) {
                // Email sending logic (can be implemented with PHPMailer)
                // For now, mark as pending
                $update_sql = "UPDATE notifications SET sent_status = 'Pending', sent_at = NOW() WHERE notification_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $notification_id);
                $update_stmt->execute();
                $sent_count++;
            } else {
                $update_sql = "UPDATE notifications SET sent_status = 'Pending' WHERE notification_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $notification_id);
                $update_stmt->execute();
            }
        }
        
        if ($sent_count > 0) {
            $success = "Notification sent successfully to $sent_count recipient(s)!";
            logActivity($user['user_id'], 'SEND_NOTIFICATION', 'notifications', 0);
        } else {
            $success = "Notification recorded. SMS sending may be disabled or recipients have no phone numbers.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - <?php echo SITE_NAME; ?></title>
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
        .template-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .template-card:hover {
            background: #e9ecef;
        }
        .char-counter {
            font-size: 12px;
            text-align: right;
            margin-top: 5px;
        }
        .char-counter.warning {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-bell"></i> Send Notifications</h4>
            <a href="history.php" class="btn btn-info">
                <i class="fas fa-history"></i> View History
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Send Notification Form -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i> Compose Notification
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="notificationForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Notification Type</label>
                                    <select name="notification_type" class="form-select" id="notification_type" required>
                                        <option value="SMS">SMS</option>
                                        <option value="Email">Email</option>
                                        <option value="In-App">In-App Notification</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select" required>
                                        <option value="General">General</option>
                                        <option value="Fees">Fees</option>
                                        <option value="Grades">Grades</option>
                                        <option value="Attendance">Attendance</option>
                                        <option value="Events">Events</option>
                                        <option value="Emergency">Emergency</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Recipient Type</label>
                                <select name="recipient_type" class="form-select" id="recipient_type" required>
                                    <option value="all_parents">All Parents</option>
                                    <option value="class_parents">Parents by Class</option>
                                    <option value="all_staff">All Staff</option>
                                    <option value="department_staff">Staff by Department</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="class_filter" style="display: none;">
                                <label class="form-label">Select Class</label>
                                <select name="class_id" class="form-select">
                                    <option value="0">-- Select Class --</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo $class['class_id']; ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="department_filter" style="display: none;">
                                <label class="form-label">Select Department</label>
                                <select name="department" class="form-select">
                                    <option value="">-- Select Department --</option>
                                    <?php while ($dept = $dept_result->fetch_assoc()): ?>
                                        <option value="<?php echo $dept['department']; ?>">
                                            <?php echo $dept['department']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Title/Subject</label>
                                <input type="text" name="title" class="form-control" id="notification_title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="5" id="notification_message" required></textarea>
                                <div class="char-counter" id="char_counter">0 / 160 characters (SMS limit)</div>
                            </div>
                            
                            <div class="alert alert-info" id="recipient_count">
                                <i class="fas fa-users"></i> <span id="recipient_count_value">0</span> recipients will receive this notification.
                            </div>
                            
                            <button type="submit" name="send_notification" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane"></i> Send Notification
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Templates Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-file-alt"></i> Message Templates
                    </div>
                    <div class="card-body">
                        <div class="template-card" onclick="useTemplate('fee_reminder')">
                            <strong><i class="fas fa-money-bill-wave"></i> Fee Reminder</strong>
                            <p class="small text-muted mb-0">Remind parents about upcoming fee payments</p>
                        </div>
                        <div class="template-card" onclick="useTemplate('absence_alert')">
                            <strong><i class="fas fa-user-clock"></i> Absence Alert</strong>
                            <p class="small text-muted mb-0">Notify parents about student absence</p>
                        </div>
                        <div class="template-card" onclick="useTemplate('report_card')">
                            <strong><i class="fas fa-file-pdf"></i> Report Card Ready</strong>
                            <p class="small text-muted mb-0">Inform parents report cards are available</p>
                        </div>
                        <div class="template-card" onclick="useTemplate('event_notification')">
                            <strong><i class="fas fa-calendar-alt"></i> Event Notification</strong>
                            <p class="small text-muted mb-0">Announce upcoming school events</p>
                        </div>
                        <div class="template-card" onclick="useTemplate('emergency')">
                            <strong><i class="fas fa-exclamation-triangle"></i> Emergency Alert</strong>
                            <p class="small text-muted mb-0">Important emergency notifications</p>
                        </div>
                        <div class="template-card" onclick="useTemplate('general')">
                            <strong><i class="fas fa-bullhorn"></i> General Announcement</strong>
                            <p class="small text-muted mb-0">General school announcements</p>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-3">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> SMS Tips
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>SMS messages are limited to 160 characters</li>
                            <li>Emojis count as multiple characters</li>
                            <li>Always identify yourself as Greenhill Academy</li>
                            <li>For emergency alerts, keep messages clear and concise</li>
                            <li>Fee reminders should include due dates and amounts</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide filters based on recipient type
        document.getElementById('recipient_type').addEventListener('change', function() {
            let classFilter = document.getElementById('class_filter');
            let deptFilter = document.getElementById('department_filter');
            
            if (this.value === 'class_parents') {
                classFilter.style.display = 'block';
                deptFilter.style.display = 'none';
            } else if (this.value === 'department_staff') {
                classFilter.style.display = 'none';
                deptFilter.style.display = 'block';
            } else {
                classFilter.style.display = 'none';
                deptFilter.style.display = 'none';
            }
            
            updateRecipientCount();
        });
        
        // Character counter for SMS
        let messageField = document.getElementById('notification_message');
        let charCounter = document.getElementById('char_counter');
        
        messageField.addEventListener('input', function() {
            let length = this.value.length;
            let type = document.getElementById('notification_type').value;
            
            if (type === 'SMS') {
                charCounter.innerHTML = length + ' / 160 characters (SMS limit)';
                if (length > 160) {
                    charCounter.classList.add('warning');
                } else {
                    charCounter.classList.remove('warning');
                }
            } else {
                charCounter.innerHTML = length + ' characters';
            }
        });
        
        // Update recipient count when filters change
        function updateRecipientCount() {
            let recipientType = document.getElementById('recipient_type').value;
            let classId = document.querySelector('[name="class_id"]')?.value || 0;
            let department = document.querySelector('[name="department"]')?.value || '';
            
            fetch(`get_recipient_count.php?type=${recipientType}&class_id=${classId}&department=${encodeURIComponent(department)}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('recipient_count_value').innerText = data.count;
                });
        }
        
        // Template functions
        function useTemplate(template) {
            let title = '';
            let message = '';
            
            switch(template) {
                case 'fee_reminder':
                    title = 'Fee Payment Reminder';
                    message = 'Dear Parent, this is to remind you that school fees are due. Please ensure payment is made by the deadline to avoid penalties. Thank you. - Greenhill Academy';
                    break;
                case 'absence_alert':
                    title = 'Student Absence Notification';
                    message = 'Dear Parent, your child was marked absent today. Please contact the school if you have any concerns. - Greenhill Academy';
                    break;
                case 'report_card':
                    title = 'Report Card Available';
                    message = 'Dear Parent, your child\'s term report card is now available. Please log in to the parent portal to view and download. - Greenhill Academy';
                    break;
                case 'event_notification':
                    title = 'Upcoming School Event';
                    message = 'Dear Parent, please mark your calendar for the upcoming school event. Details will be shared soon. - Greenhill Academy';
                    break;
                case 'emergency':
                    title = 'EMERGENCY ALERT';
                    message = 'EMERGENCY: Please check your email for important information regarding school operations. - Greenhill Academy';
                    break;
                case 'general':
                    title = 'School Announcement';
                    message = 'Dear Parent, please stay tuned for important updates from Greenhill Academy. Thank you for your continued support.';
                    break;
            }
            
            document.getElementById('notification_title').value = title;
            document.getElementById('notification_message').value = message;
            
            // Trigger character counter update
            let event = new Event('input');
            document.getElementById('notification_message').dispatchEvent(event);
        }
        
        // Update recipient count on page load
        updateRecipientCount();
        
        // Also update when class or department changes
        document.addEventListener('change', function(e) {
            if (e.target.name === 'class_id' || e.target.name === 'department') {
                updateRecipientCount();
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>