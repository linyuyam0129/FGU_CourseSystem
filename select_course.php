<?php
session_start();
require 'db.php';

// 設置回應頭為 JSON 格式，這應該是所有輸出的第一行程式碼（除了 <?php 和 require）
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    // 統一回傳 JSON 格式
    echo json_encode(['status' => 'error', 'message' => '未登入'], JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = $_SESSION['user_id'];

// 撈 student_id
$stmt = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
// 檢查 prepare 是否成功
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => '資料庫查詢準備失敗: ' . $conn->error], JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();
$student_id = $user['student_id'] ?? ''; // 如果找不到 student_id，則為空字串

// 前端現在傳遞的是 course_code，而不是 course_name
// 確保前端 JavaScript 中的 body 是 `course_code=${encodeURIComponent(course_code)}&action=add`
$course_code = $_POST['course_code'] ?? ''; // 從前端接收 course_code
$action = $_POST['action'] ?? '';
$default_semester = '113-2';
$default_class_id = 0;

if (empty($course_code) || empty($action)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '缺少必要參數 (course_code 或 action)'], JSON_UNESCAPED_UNICODE);
    exit();
}

// 根據 course_code 查出課程名稱 (如果需要，用於日誌或更詳細的訊息)
// 這個查詢不是必需的，但有助於錯誤訊息和日誌記錄
$course_name_from_db = '';
$stmt_name = $conn->prepare("SELECT `課程名稱` FROM course_list WHERE `課程代碼` = ?");
if ($stmt_name) {
    $stmt_name->bind_param("s", $course_code);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    $row_name = $result_name->fetch_assoc();
    if ($row_name) {
        $course_name_from_db = $row_name['課程名稱'];
    }
    $stmt_name->close();
}


if ($action === 'add') {
    // 檢查是否已選
    $check = $conn->prepare("SELECT * FROM selected_courses WHERE user_id = ? AND course_code = ?");
    if (!$check) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '資料庫檢查準備失敗: ' . $conn->error], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $check->bind_param("is", $user_id, $course_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // 如果已選，回傳成功狀態但帶有訊息，告知前端已存在
        echo json_encode(['status' => 'success', 'message' => "課程「{$course_name_from_db}」已選擇"], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $check->close(); // 關閉 statement

    // 執行插入
    $insert = $conn->prepare("INSERT INTO selected_courses (user_id, student_id, course_code, class_id, semester) VALUES (?, ?, ?, ?, ?)");
    if (!$insert) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '資料庫插入準備失敗: ' . $conn->error], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $insert->bind_param("issis", $user_id, $student_id, $course_code, $default_class_id, $default_semester);
    if ($insert->execute()) {
        echo json_encode(['status' => 'success', 'message' => "課程「{$course_name_from_db}」加入成功"], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500); // 資料庫執行失敗
        echo json_encode(['status' => 'error', 'message' => "課程「{$course_name_from_db}」加入失敗: " . $insert->error], JSON_UNESCAPED_UNICODE);
    }
    $insert->close(); // 關閉 statement
}
elseif ($action === 'drop') {
    $delete = $conn->prepare("DELETE FROM selected_courses WHERE user_id = ? AND course_code = ?");
    if (!$delete) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => '資料庫刪除準備失敗: ' . $conn->error], JSON_UNESCAPED_UNICODE);
        exit();
    }
    $delete->bind_param("is", $user_id, $course_code);
    if ($delete->execute()) {
        echo json_encode(['status' => 'success', 'message' => "課程「{$course_name_from_db}」移除成功"], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500); // 資料庫執行失敗
        echo json_encode(['status' => 'error', 'message' => "課程「{$course_name_from_db}」移除失敗: " . $delete->error], JSON_UNESCAPED_UNICODE);
    }
    $delete->close(); // 關閉 statement
}
else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => '無效的操作'], JSON_UNESCAPED_UNICODE);
}

$conn->close(); // 關閉資料庫連線
?>