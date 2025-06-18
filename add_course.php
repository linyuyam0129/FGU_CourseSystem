<?php
session_start();
require 'db.php'; // 確保 db.php 檔案存在且資料庫連線正確

header('Content-Type: application/json'); // 設定回應的內容類型為 JSON

$response = [
    'status' => 'error',
    'message' => '未知錯誤。'
];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = '使用者未登入。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = $_SESSION['user_id'];

// 檢查 POST 請求中是否有 course_code 參數
if (!isset($_POST['course_code']) || empty($_POST['course_code'])) {
    $response['message'] = '課程代碼不能為空。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$course_code = $_POST['course_code'];
$semester = date("Y") . '學年度'; // 預設新增的課程學期為當前年度 (例如: 2024學年度)

// 驗證 course_code 是否存在於 `courses` 表中
$course_name_from_db = '';
$course_credits_from_db = 0;
$sql_check_course = "SELECT `course_name`, `credits` FROM `courses` WHERE `course_code` = ?";
$stmt_check_course = $conn->prepare($sql_check_course);
if (!$stmt_check_course) {
    $response['message'] = '準備課程驗證查詢失敗: ' . $conn->error;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt_check_course->bind_param("s", $course_code);
$stmt_check_course->execute();
$check_result = $stmt_check_course->get_result();

if ($check_result->num_rows === 0) {
    $response['message'] = "課程代碼 '{$course_code}' 不存在於課程列表中。請確認輸入無誤。";
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    $stmt_check_course->close();
    exit();
}
$course_info = $check_result->fetch_assoc();
$course_name_from_db = $course_info['course_name'];
$course_credits_from_db = $course_info['credits'];
$stmt_check_course->close();


// 檢查課程是否已經在 completed_courses 或 selected_courses 中
// 這裡的邏輯是：如果已在 completed_courses，則警告；如果只在 selected_courses，也警告，不重複添加。
$sql_check_status = "
    SELECT course_code FROM completed_courses WHERE user_id = ? AND course_code = ?
    UNION ALL
    SELECT course_code FROM selected_courses WHERE user_id = ? AND course_code = ?
";
$stmt_check_status = $conn->prepare($sql_check_status);
if (!$stmt_check_status) {
    $response['message'] = '準備課程狀態檢查失敗: ' . $conn->error;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt_check_status->bind_param("isis", $user_id, $course_code, $user_id, $course_code);
$stmt_check_status->execute();
$status_result = $stmt_check_status->get_result();

if ($status_result->num_rows > 0) {
    $response['message'] = "課程 '{$course_name_from_db}' 已在您的已完成或已選列表中，無需重複新增。";
    $response['status'] = 'warning'; // 設為警告，表示非錯誤，只是不需重複新增
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    $stmt_check_status->close();
    exit();
}
$stmt_check_status->close();


// 將課程新增到 completed_courses 表中 (因為您說要將兩個表合併後顯示在已完成課程，所以這裡直接加入 completed_courses)
$sql_insert_course = "INSERT INTO completed_courses (user_id, course_code, semester) VALUES (?, ?, ?)";
$stmt_insert_course = $conn->prepare($sql_insert_course);
if (!$stmt_insert_course) {
    $response['message'] = '準備新增課程查詢失敗: ' . $conn->error;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt_insert_course->bind_param("iss", $user_id, $course_code, $semester);

if ($stmt_insert_course->execute()) {
    $response['status'] = 'success';
    $response['message'] = "課程 '{$course_name_from_db}' 成功加入已完成課程！";
} else {
    $response['message'] = '新增課程失敗: ' . $stmt_insert_course->error;
}

$stmt_insert_course->close();
$conn->close();

echo json_encode($response);
?>
