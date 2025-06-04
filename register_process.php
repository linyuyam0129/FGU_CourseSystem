<?php
session_start();
require 'db.php';

// 取得表單資料
$student_id = $_POST['student_id'] ?? '';
$name = $_POST['name'] ?? '';
$college = $_POST['college'] ?? '';
$department = $_POST['department'] ?? '';
$group = $_POST['group'] ?? '';
$password = $_POST['password'] ?? '';
$email = $_POST['email'] ?? '';

// 簡易驗證
if (empty($student_id) || empty($name) || empty($college) || empty($department) || empty($group) || empty($password) || empty($email)) {
    echo "<script>alert('所有欄位皆為必填！'); history.back();</script>";
    exit();
}

// 密碼加密（若你不用 salt，可以直接這樣）
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// 預設欄位（course_structure 與 reset_code 可預設）
$course_structure = '未設定';
$reset_code = NULL;
$salt = NULL; // 若你用不到，可以傳 NULL 或空字串

// 寫入資料
$stmt = $conn->prepare("INSERT INTO users (email, password, salt, reset_code, student_id, course_structure, department, name, user_group, college) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssssss", $email, $hashed_password, $salt, $reset_code, $student_id, $course_structure, $department, $name, $group, $college);

if ($stmt->execute()) {
    $new_user_id = $stmt->insert_id;
    $_SESSION['user_id'] = $new_user_id;
    $_SESSION['name'] = $name;
    $_SESSION['student_id'] = $student_id;
    $_SESSION['department'] = $department;
    $_SESSION['user_group'] = $group;

    echo "<script>
        alert('註冊成功，已自動登入！');
        window.location.href = 'index.php';
    </script>";
    exit();
} else {
    echo "<script>alert('註冊失敗：" . addslashes($stmt->error) . "'); history.back();</script>";
    exit();
}
?>
