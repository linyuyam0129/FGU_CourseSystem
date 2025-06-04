<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "尚未登入";
    exit();
}

$user_id = $_SESSION['user_id'];

// 撈 student_id
$stmt = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$student_id = $user['student_id'] ?? '';

$course_name = $_POST['course_name'] ?? '';
$action = $_POST['action'] ?? '';
$default_semester = '113-2';
$default_class_id = 0;

if (empty($course_name) || empty($action)) {
    http_response_code(400);
    echo "缺少必要參數";
    exit();
}

// 根據課程名稱查出課程代碼
$stmt = $conn->prepare("SELECT 課程代碼 FROM course_list WHERE 課程名稱 = ?");
$stmt->bind_param("s", $course_name);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo "找不到課程：$course_name";
    exit();
}

$course_code = $row['課程代碼'];

if ($action === 'add') {
    // 檢查是否已選
    $check = $conn->prepare("SELECT * FROM selected_courses WHERE user_id = ? AND course_code = ?");
    $check->bind_param("is", $user_id, $course_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo "已選擇此課程";
        exit();
    }

    $insert = $conn->prepare("INSERT INTO selected_courses (user_id, student_id, course_code, class_id, semester) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("issis", $user_id, $student_id, $course_code, $default_class_id, $default_semester);
    $insert->execute();
    echo "加入成功";
}
elseif ($action === 'drop') {
    $delete = $conn->prepare("DELETE FROM selected_courses WHERE user_id = ? AND course_code = ?");
    $delete->bind_param("is", $user_id, $course_code);
    $delete->execute();
    echo "移除成功";
}
else {
    http_response_code(400);
    echo "無效的操作";
}
