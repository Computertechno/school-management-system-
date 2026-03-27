<?php
require_once '../../includes/config.php';

$sql = "SELECT c.class_name, COUNT(s.student_id) as count 
        FROM classes c 
        LEFT JOIN students s ON c.class_id = s.current_class_id AND s.enrollment_status = 'Active'
        GROUP BY c.class_id 
        ORDER BY c.class_level, c.class_name
        LIMIT 15";
$result = $conn->query($sql);

$labels = [];
$values = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['class_name'];
    $values[] = $row['count'];
}

echo json_encode(['labels' => $labels, 'values' => $values]);
?>