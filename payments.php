<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

requireLogin();
$user = getCurrentUser();
$is_admin = ($user['role_name'] == 'admin');
$is_accountant = ($user['role_name'] == 'accountant');

if (!$is_admin && !$is_accountant) {
    redirect('modules/dashboard/' . $user['role_name'] . '.php');
}

$invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

$error = '';
$success = '';

// Get invoice details
if ($invoice_id > 0) {
    $invoice_sql = "SELECT i.*, s.first_name, s.last_name, s.admission_no, s.student_id
                    FROM invoices i
                    JOIN students s ON i.student_id = s.student_id
                    WHERE i.invoice_id = ?";
    $invoice_stmt = $conn->prepare($invoice_sql);
    $invoice_stmt->bind_param("i", $invoice_id);
    $invoice_stmt->execute();
    $invoice_result = $invoice_stmt->get_result();
    $invoice = $invoice_result->fetch_assoc();
    
    if (!$invoice) {
        redirect('invoices.php');
    }
    $student_id = $invoice['student_id'];
}

// Handle payment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_payment'])) {
    $invoice_id = (int)$_POST['invoice_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method'];
    $reference_no = trim($_POST['reference_no'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    
    if ($amount <= 0) {
        $error = "Please enter a valid amount.";
    } else {
        // Get invoice
        $invoice_sql = "SELECT * FROM invoices WHERE invoice_id = ?";
        $invoice_stmt = $conn->prepare($invoice_sql);
        $invoice_stmt->bind_param("i", $invoice_id);
        $invoice_stmt->execute();
        $invoice_data = $invoice_stmt->get_result()->fetch_assoc();
        
        // Generate payment number
        $payment_no = generateCode('PAY', 'payments', 'payment_no');
        
        // Insert payment
        $payment_sql = "INSERT INTO payments (payment_no, invoice_id, student_id, amount, payment_date, payment_method, reference_no, remarks, received_by) 
                        VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)";
        $payment_stmt = $conn->prepare($payment_sql);
        $payment_stmt->bind_param("siidsssi", $payment_no, $invoice_id, $invoice_data['student_id'], $amount, $payment_method, $reference_no, $remarks, $user['user_id']);
        
        if ($payment_stmt->execute()) {
            $payment_id = $payment_stmt->insert_id;
            
            // Update invoice
            $new_amount_paid = $invoice_data['amount_paid'] + $amount;
            $new_balance = $invoice_data['amount_due'] - $new_amount_paid;
            $new_status = $new_balance <= 0 ? 'Paid' : ($new_amount_paid > 0 ? 'Partial' : 'Pending');
            
            $update_sql = "UPDATE invoices SET amount_paid = ?, balance = ?, status = ? WHERE invoice_id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ddsi", $new_amount_paid, $new_balance, $new_status, $invoice_id);
            $update_stmt->execute();
            
            // Generate receipt
            $receipt_no = generateCode('RCP', 'receipts', 'receipt_no');
            $receipt_sql = "INSERT INTO receipts (receipt_no, payment_id, student_id, amount, receipt_date, generated_by) 
                            VALUES (?, ?, ?, ?, CURDATE(), ?)";
            $receipt_stmt = $conn->prepare($receipt_sql);
            $receipt_stmt->bind_param("siidi", $receipt_no, $payment_id, $invoice_data['student_id'], $amount, $user['user_id']);
            $receipt_stmt->execute();
            
            // Log activity
            logActivity($user['user_id'], 'RECORD_PAYMENT', 'payments', $payment_id);
            
            // Send SMS notification to parent
            if (SMS_ENABLED) {
                // Get parent phone
                $parent_sql = "SELECT p.phone FROM parents p 
                               JOIN student_parents sp ON p.parent_id = sp.parent_id 
                               WHERE sp.student_id = ? LIMIT 1";
                $parent_stmt = $conn->prepare($parent_sql);
                $parent_stmt->bind_param("i", $invoice_data['student_id']);
                $parent_stmt->execute();
                $parent_result = $parent_stmt->get_result();
                if ($parent = $parent_result->fetch_assoc()) {
                    $student_name = getStudentName($invoice_data['student_id']);
                    $message = "Dear Parent, payment of " . formatMoney($amount) . " received for $student_name. Receipt No: $receipt_no. Balance: " . formatMoney($new_balance) . " - Greenhill Academy";
                    sendSMS($parent['phone'], $message);
                }
            }
            
            $success = "Payment recorded successfully! Receipt No: $receipt_no";
            
            // Refresh invoice data
            $invoice_stmt->execute();
            $invoice = $invoice_stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to record payment: " . $conn->error;
        }
    }
}

// Get payment history
$payments = [];
if ($invoice_id > 0) {
    $payments_sql = "SELECT p.*, u.username as received_by_name
                     FROM payments p
                     LEFT JOIN users u ON p.received_by = u.user_id
                     WHERE p.invoice_id = ?
                     ORDER BY p.payment_date DESC";
    $payments_stmt = $conn->prepare($payments_sql);
    $payments_stmt->bind_param("i", $invoice_id);
    $payments_stmt->execute();
    $payments_result = $payments_stmt->get_result();
    $payments = $payments_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Payment - <?php echo SITE_NAME; ?></title>
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
        .invoice-summary {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .amount-large {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }
        .receipt-item {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <?php include '../dashboard/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h4><i class="fas fa-credit-card"></i> Record Payment</h4>
            <a href="invoices.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Invoices
            </a>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($invoice): ?>
            <!-- Invoice Summary -->
            <div class="invoice-summary">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Student:</strong> <?php echo htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']); ?></p>
                        <p class="mb-1"><strong>Admission No:</strong> <?php echo $invoice['admission_no']; ?></p>
                        <p class="mb-1"><strong>Invoice No:</strong> <?php echo $invoice['invoice_no']; ?></p>
                        <p class="mb-1"><strong>Term:</strong> <?php echo $invoice['term']; ?> | <strong>Year:</strong> <?php echo $invoice['academic_year']; ?></p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-1"><strong>Amount Due:</strong> <?php echo formatMoney($invoice['amount_due']); ?></p>
                        <p class="mb-1"><strong>Amount Paid:</strong> <?php echo formatMoney($invoice['amount_paid']); ?></p>
                        <p class="mb-1"><strong>Balance:</strong> <span class="text-<?php echo $invoice['balance'] > 0 ? 'danger' : 'success'; ?>"><?php echo formatMoney($invoice['balance']); ?></span></p>
                        <p class="mb-1"><strong>Due Date:</strong> <?php echo formatDate($invoice['due_date']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Payment Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i> Record New Payment
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">UGX</span>
                                    <input type="number" name="amount" class="form-control" step="1000" min="1" max="<?php echo $invoice['balance']; ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Payment Method</label>
                                <select name="payment_method" class="form-select" required>
                                    <option value="Cash">Cash</option>
                                    <option value="Bank Transfer">Bank Transfer</option>
                                    <option value="Mobile Money">Mobile Money</option>
                                    <option value="Cheque">Cheque</option>
                                    <option value="Card">Card</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reference Number</label>
                                <input type="text" name="reference_no" class="form-control" placeholder="Transaction reference (if any)">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Remarks</label>
                                <input type="text" name="remarks" class="form-control" placeholder="Optional notes">
                            </div>
                        </div>
                        <div class="text-end">
                            <button type="submit" name="record_payment" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Record Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Payment History -->
            <?php if (count($payments) > 0): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Payment History
                    </div>
                    <div class="card-body">
                        <?php foreach ($payments as $payment): ?>
                            <div class="receipt-item">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong><?php echo formatDate($payment['payment_date']); ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <?php echo formatMoney($payment['amount']); ?>
                                    </div>
                                    <div class="col-md-2">
                                        <span class="badge bg-info"><?php echo $payment['payment_method']; ?></span>
                                    </div>
                                    <div class="col-md-2">
                                        <?php echo $payment['payment_no']; ?>
                                    </div>
                                    <div class="col-md-2">
                                        <a href="receipt.php?id=<?php echo $payment['payment_id']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                            <i class="fas fa-print"></i> Receipt
                                        </a>
                                    </div>
                                </div>
                                <?php if ($payment['remarks']): ?>
                                    <div class="mt-1 small text-muted"><?php echo $payment['remarks']; ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-invoice fa-3x text-muted mb-3"></i>
                    <p>Invoice not found.</p>
                    <a href="invoices.php" class="btn btn-primary">View Invoices</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>