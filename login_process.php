<?php
session_start();
require 'db.php';

$student_id = $_POST['student_id'];
$password = $_POST['password'];

// 查詢用戶
$sql = "SELECT id, student_id, name, department, user_group, email, password, salt FROM users WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($id, $stored_student_id, $name, $department, $user_group, $email, $stored_password, $salt);
$stmt->fetch();

if ($stmt->num_rows === 1) {
    if (password_verify($password . $salt, $stored_password)) {
        // 成功登入：寫入 session
        session_regenerate_id(true); // 防止 session fixation
        $_SESSION['user_id'] = $id;
        $_SESSION['student_id'] = $stored_student_id;
        $_SESSION['name'] = $name;
        $_SESSION['department'] = $department;
        $_SESSION['user_group'] = $user_group;

        header("Location: index.php");
        exit();
    } else {
        $_SESSION['login_error'] = "密碼錯誤，請再試一次！";
        header("Location: login.php");
        exit();
    }
} else {
    $_SESSION['login_error'] = "查無此學號，請重新輸入！";
    header("Location: login.php");
    exit();
}

$stmt->close();
$conn->close();
