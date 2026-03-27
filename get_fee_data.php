<?php
require_once '../../includes/config.php';

$year = date('Y');
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$collections = array_fill(0, 12, 0);

$sql = "SELECT MONTH(payment_date) as month, SUM(amount) as total 
        FROM payments WHERE YEAR(payment_date) = ? 
        GROUP BY MONTH(payment_date)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $year);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $collections[$row['month'] - 1] = $row['total'];
}

echo json_encode(['months' => $months, 'collections' => $collections]);
?>