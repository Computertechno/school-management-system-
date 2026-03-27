<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$department = isset($_GET['department']) ? trim($_GET['department']) : '';

$count = 0;

if ($type == 'all_parents') {
    $sql = "SELECT COUNT(DISTINCT parent_id) as count FROM parents";
    $result = $conn->query($sql);
    $count = $result->fetch_assoc()['count'];
} elseif ($type == 'class_parents' && $class_id > 0) {
    $sql = "SELECT COUNT(DISTINCT p.parent_id) as count 
            FROM parents p 
            JOIN student_parents sp ON p.parent_id = sp.parent_id 
            JOIN students s ON sp.student_id = s.student_id 
            WHERE s.current_class_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
} elseif ($type == 'all_staff') {
    $sql = "SELECT COUNT(*) as count FROM staff WHERE is_active = 1";
    $result = $conn->query($sql);
    $count = $result->fetch_assoc()['count'];
} elseif ($type == 'department_staff' && !empty($department)) {
    $sql = "SELECT COUNT(*) as count FROM staff WHERE is_active = 1 AND department = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $department);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
}

echo json_encode(['count' => $count]);
?>