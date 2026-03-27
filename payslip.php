<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

requireLogin();

$salary_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($salary_id <= 0) {
    die("Invalid payslip ID.");
}

// Get salary details
$sql = "SELECT s.*, st.first_name, st.last_name, st.staff_no, st.position, st.department, 
               st.tin_number, st.nssf_number, st.bank_name, st.bank_account
        FROM salaries s
        JOIN staff st ON s.staff_id = st.staff_id
        WHERE s.salary_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $salary_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Payslip not found.");
}

$salary = $result->fetch_assoc();

function getPayslipHTML($salary) {
    $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Payslip - {$salary['staff_no']} - {$salary['month']}</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background: white;
        }
        .payslip {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #2c3e50;
            border-radius: 10px;
            overflow: hidden;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            opacity: 0.8;
        }
        .pay-period {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            font-weight: bold;
        }
        .employee-info {
            padding: 15px;
            border-bottom: 1px solid #ddd;
        }
        .employee-info table {
            width: 100%;
        }
        .employee-info td {
            padding: 5px;
        }
        .salary-details {
            padding: 15px;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .salary-table th, .salary-table td {
            border: 1px solid #ddd;
            padding: 10px;
        }
        .salary-table th {
            background: #3498db;
            color: white;
            text-align: left;
        }
        .total-row {
            background: #f8f9fa;
            font-weight: bold;
        }
        .net-salary {
            font-size: 24px;
            font-weight: bold;
            color: #27ae60;
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            margin-top: 15px;
        }
        .footer {
            background: #f8f9fa;
            padding: 10px;
            text-align: center;
            font-size: 10px;
            color: #7f8c8d;
            border-top: 1px solid #ddd;
        }
    </style>
</head>
<body>
    <div class="payslip">
        <div class="header">
            <h1>GREENHILL ACADEMY</h1>
            <p>Kampala, Uganda | Kibuli & Buwaate Campuses</p>
        </div>
        
        <div class="pay-period">
            PAYSLIP FOR {$salary['month']}
        </div>
        
        <div class="employee-info">
            <table>
                <tr>
                    <td width="50%"><strong>Staff Name:</strong> {$salary['first_name']} {$salary['last_name']}</td>
                    <td><strong>Staff No:</strong> {$salary['staff_no']}</td>
                </tr>
                 <tr>
                    <td><strong>Position:</strong> {$salary['position']}</td>
                    <td><strong>Department:</strong> {$salary['department']}</td>
                 </tr>
                 <tr>
                    <td><strong>TIN Number:</strong> " . ($salary['tin_number'] ?: 'Not registered') . "</td>
                    <td><strong>NSSF Number:</strong> " . ($salary['nssf_number'] ?: 'Not registered') . "</td>
                 </tr>
             </table>
        </div>
        
        <div class="salary-details">
            <h4>Salary Details</h4>
            <table class="salary-table">
                <tr>
                    <th>Description</th>
                    <th>Amount (UGX)</th>
                </tr>
                <tr>
                    <td>Basic Salary</td>
                    <td>" . number_format($salary['basic_salary'], 0) . "</td>
                </tr>
                <tr>
                    <td>Housing Allowance</td>
                    <td>" . number_format($salary['housing_allowance'], 0) . "</td>
                </tr>
                <tr>
                    <td>Transport Allowance</td>
                    <td>" . number_format($salary['transport_allowance'], 0) . "</td>
                </tr>
                <tr>
                    <td>Medical Allowance</td>
                    <td>" . number_format($salary['medical_allowance'], 0) . "</td>
                </tr>
                <tr>
                    <td>Other Allowances</td>
                    <td>" . number_format($salary['other_allowances'], 0) . "</td>
                </tr>
                <tr class="total-row">
                    <td>Gross Salary</td>
                    <td>" . number_format($salary['gross_salary'], 0) . "</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Deductions</strong></td>
                </tr>
                <tr>
                    <td>PAYE Tax</td>
                    <td>" . number_format($salary['paye_tax'], 0) . "</td>
                </tr>
                <tr>
                    <td>NSSF Employee Contribution (5%)</td>
                    <td>" . number_format($salary['nssf_employee'], 0) . "</td>
                </tr>
                <tr>
                    <td>Loan Deductions</td>
                    <td>" . number_format($salary['loan_deductions'], 0) . "</td>
                </tr>
                <tr>
                    <td>Other Deductions</td>
                    <td>" . number_format($salary['other_deductions'], 0) . "</td>
                </tr>
                <tr class="total-row">
                    <td>Total Deductions</td>
                    <td>" . number_format($salary['total_deductions'], 0) . "</td>
                </tr>
            </table>
            
            <div class="net-salary">
                Net Salary: UGX " . number_format($salary['net_salary'], 0) . "
            </div>
        </div>
        
        <div class="footer">
            <p>This is a computer-generated payslip and does not require a signature.</p>
            <p>Payment Date: " . ($salary['payment_date'] ? date('d M Y', strtotime($salary['payment_date'])) : 'Pending') . "</p>
            <p>Greenhill Academy - Excellence in Education</p>
        </div>
    </div>
</body>
</html>
HTML;
    return $html;
}

$html = getPayslipHTML($salary);

$options = new Options();
$options->set('defaultFont', 'Courier');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$filename = "Payslip_" . $salary['staff_no'] . "_" . date('Y_m', strtotime($salary['month_year'])) . ".pdf";
$dompdf->stream($filename, array("Attachment" => false));
exit;
?>
</html>