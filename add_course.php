<?php

session_start();
require 'db.php'; //db.php 處理資料庫連線

// 設定回傳內容為 JSON 格式，必須在任何輸出之前
header('Content-Type: application/json');


if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => '未登入，請先登入。']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 從 POST 請求獲取課程代碼和課程類型
$course_code_raw = $_POST['course_code'] ?? ''; // 例如 '111'
$course_type = $_POST['course_type'] ?? ''; // 例如 'GE'

if (empty($course_code_raw)) {
    echo json_encode(['status' => 'error', 'message' => '請輸入課程代碼。']);
    exit();
}

// 組合完整的課程代碼 (例如：GE111)。轉為大寫以確保與資料庫一致性。
$full_course_code = strtoupper($course_type) . strtoupper($course_code_raw);

// 步驟1: 檢查課程代碼是否存在於 course_list 資料表
// 使用 CONVERT 和 COLLATE 處理可能存在的編碼問題
$sql_check_course_list = "SELECT 科目名稱, 學分數 FROM course_list WHERE CONVERT(`課程代碼` USING utf8mb4) COLLATE utf8mb4_unicode_ci = ?";
$stmt_check_course_list = $conn->prepare($sql_check_course_list);
if ($stmt_check_course_list === false) {
    echo json_encode(['status' => 'error', 'message' => '預處理檢查課程列表查詢失敗: ' . $conn->error]);
    exit();
}
$stmt_check_course_list->bind_param("s", $full_course_code);
$stmt_check_course_list->execute();
$result_check_course_list = $stmt_check_course_list->get_result();

if ($result_check_course_list->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => '查無此課程代碼，請確認輸入是否正確。']);
    $stmt_check_course_list->close();
    exit();
}
$course_info = $result_check_course_list->fetch_assoc(); // 獲取課程名稱和學分
$stmt_check_course_list->close();

// 步驟2: 檢查該使用者是否已選修過該課程
// 這裡應該檢查 `completed_courses` 而不是 `selected_courses`
$sql_check_completed = "SELECT 1 FROM completed_courses WHERE user_id = ? AND CONVERT(`course_code` USING utf8mb4) COLLATE utf8mb4_unicode_ci = ?";
$stmt_check_completed = $conn->prepare($sql_check_completed);
if ($stmt_check_completed === false) {
    echo json_encode(['status' => 'error', 'message' => '預處理檢查已修課程查詢失敗: ' . $conn->error]);
    exit();
}
$stmt_check_completed->bind_param("is", $user_id, $full_course_code);
$stmt_check_completed->execute();
$result_check_completed = $stmt_check_completed->get_result();

if ($result_check_completed->num_rows > 0) {
    echo json_encode(['status' => 'error', 'message' => '您已選修過此課程，無需重複新增。']);
    $stmt_check_completed->close();
    exit();
}
$stmt_check_completed->close();

// 步驟3: 將課程新增到 completed_courses 資料表
// 請確保您的 completed_courses 表有 course_name 和 credits 欄位
$semester = '113-2'; // 假設預設為目前學期 (請根據實際學期調整)

$sql_insert = "INSERT INTO completed_courses (user_id, course_code, course_name, credits, semester) VALUES (?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($sql_insert);
if ($stmt_insert === false) {
    echo json_encode(['status' => 'error', 'message' => '預處理插入課程查詢失敗: ' . $conn->error]);
    exit();
}
// 'issis' 代表參數類型：integer, string, string, integer, string
$stmt_insert->bind_param("issis", $user_id, $full_course_code, $course_info['科目名稱'], $course_info['學分數'], $semester);

if ($stmt_insert->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => '課程新增成功！',
        'course_name' => $course_info['科目名稱'],
        'course_code' => $full_course_code,
        'credits' => $course_info['學分數'],
        'semester' => $semester
    ]);
} else {
    echo json_encode(['status' => 'error', 'message' => '新增課程失敗: ' . $stmt_insert->error]);
}

$stmt_insert->close();
$conn->close();
?>