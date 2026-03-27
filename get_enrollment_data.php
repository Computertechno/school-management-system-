<?php
require_once '../../includes/config.php';

$sql = "SELECT class_level, COUNT(*) as count FROM students 
        JOIN classes ON students.current_class_id = classes.class_id 
        WHERE enrollment_status = 'Active' 
        GROUP BY class_level";
$result = $conn->query($sql);

$labels = [];
$values = [];
while ($row = $result->fetch_assoc()) {
    $labels[] = $row['class_level'];
    $values[] = $row['count'];
}

echo json_encode(['labels' => $labels, 'values' => $values]);
?>