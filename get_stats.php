<?php
require_once '../../includes/config.php';
require_once '../../includes/db_connection.php';

$students = $conn->query("SELECT COUNT(*) FROM students")->fetch_row()[0] ?? 0;
$staff = $conn->query("SELECT COUNT(*) FROM staff")->fetch_row()[0] ?? 0;
$parents = $conn->query("SELECT COUNT(*) FROM parents")->fetch_row()[0] ?? 0;
$books = $conn->query("SELECT COUNT(*) FROM books")->fetch_row()[0] ?? 0;

echo json_encode([
    'students' => $students,
    'staff' => $staff,
    'parents' => $parents,
    'books' => $books
]);
?>