<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit("未登入");
}

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$student_id = $user['student_id'];
$stmt->close();

$code = $_POST['code'] ?? '';
if ($code) {
    $stmt = $conn->prepare("DELETE FROM selected_courses WHERE student_id = ? AND course_code = ?");
    $stmt->bind_param("ss", $student_id, $code);
    $stmt->execute();
    echo "success";
} else {
    http_response_code(400);
    echo "缺少課程代碼";
}
?>
