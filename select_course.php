<?php
session_start();
require 'db.php'; // 確保 db.php 檔案存在且資料庫連線正確

// 設置回應頭為 JSON 格式，這應該是所有輸出的第一行程式碼（除了 <?php 和 require）
header('Content-Type: application/json');

// 初始化一個錯誤響應模板
$response = [
    'status' => 'error',
    'message' => '未知錯誤。'
];

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    $response['message'] = '使用者未登入。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

$user_id = $_SESSION['user_id'];

// 撈 student_id
$stmt_user = $conn->prepare("SELECT student_id FROM users WHERE id = ?");
if (!$stmt_user) {
    http_response_code(500);
    $response['message'] = '資料庫查詢準備失敗: ' . $conn->error;
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$res_user = $stmt_user->get_result();
$user_data = $res_user->fetch_assoc();
$student_id = $user_data['student_id'] ?? '';
$stmt_user->close();

// --- 新增：處理獲取已選課程的 GET 請求 ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_selected') {
    try {
        $sql_get_selected = "SELECT course_code FROM selected_courses WHERE user_id = ?";
        $stmt_get_selected = $conn->prepare($sql_get_selected);
        if (!$stmt_get_selected) {
            throw new Exception('準備獲取已選課程失敗: ' . $conn->error);
        }
        $stmt_get_selected->bind_param("i", $user_id);
        $stmt_get_selected->execute();
        $result_get_selected = $stmt_get_selected->get_result();

        $current_selected_courses = [];
        while ($row = $result_get_selected->fetch_assoc()) {
            $current_selected_courses[] = $row['course_code'];
        }
        $stmt_get_selected->close();

        $response['status'] = 'success';
        $response['message'] = '已成功獲取已選課程。';
        $response['selected_courses'] = $current_selected_courses; // 回傳課程代碼陣列
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit(); // 立即退出，不執行後續的 POST 邏輯
    } catch (Exception $e) {
        http_response_code(500);
        $response['message'] = '獲取已選課程時發生錯誤: ' . $e->getMessage();
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
}


// --- 以下是原有的 POST 請求處理邏輯 (add/drop course) ---

$course_code = $_POST['course_code'] ?? ''; // 從前端接收 course_code
$action = $_POST['action'] ?? '';
$default_semester = '113-2'; // 預設學期
$default_class_id = 0; // 預設班級 ID

if (empty($course_code) || empty($action)) {
    http_response_code(400);
    $response['message'] = '缺少必要參數 (course_code 或 action)。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

// 根據 course_code 查出科目名稱 (如果需要，用於日誌或更詳細的訊息)
$course_name_from_db = '';
$stmt_name = $conn->prepare("SELECT `科目名稱` FROM course_list WHERE `課程代碼` = ?");
if ($stmt_name) {
    $stmt_name->bind_param("s", $course_code);
    $stmt_name->execute();
    $result_name = $stmt_name->get_result();
    $row_name = $result_name->fetch_assoc();
    if ($row_name) {
        $course_name_from_db = $row_name['科目名稱'];
    }
    $stmt_name->close();
}


if ($action === 'add') {
    // 檢查是否已選
    $check = $conn->prepare("SELECT * FROM selected_courses WHERE user_id = ? AND course_code = ?");
    if (!$check) {
        http_response_code(500);
        $response['message'] = '資料庫檢查準備失敗: ' . $conn->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    $check->bind_param("is", $user_id, $course_code);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        // 如果已選，回傳成功狀態但帶有訊息，告知前端已存在
        $response['status'] = 'warning'; // 設為 warning 讓前端可以區分
        $response['message'] = "課程「{$course_name_from_db}」已選擇。";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    $check->close(); // 關閉 statement

    // 執行插入
    $insert = $conn->prepare("INSERT INTO selected_courses (user_id, student_id, course_code, class_id, semester) VALUES (?, ?, ?, ?, ?)");
    if (!$insert) {
        http_response_code(500);
        $response['message'] = '資料庫插入準備失敗: ' . $conn->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    $insert->bind_param("issis", $user_id, $student_id, $course_code, $default_class_id, $default_semester);
    if ($insert->execute()) {
        $response['status'] = 'success';
        $response['message'] = "課程「{$course_name_from_db}」加入成功！";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500); // 資料庫執行失敗
        $response['message'] = "課程「{$course_name_from_db}」加入失敗: " . $insert->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    $insert->close(); // 關閉 statement
}
elseif ($action === 'drop') {
    $delete = $conn->prepare("DELETE FROM selected_courses WHERE user_id = ? AND course_code = ?");
    if (!$delete) {
        http_response_code(500);
        $response['message'] = '資料庫刪除準備失敗: ' . $conn->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }
    $delete->bind_param("is", $user_id, $course_code);
    if ($delete->execute()) {
        $response['status'] = 'success';
        $response['message'] = "課程「{$course_name_from_db}」移除成功！";
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500); // 資料庫執行失敗
        $response['message'] = "課程「{$course_name_from_db}」移除失敗: " . $delete->error;
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }
    $delete->close(); // 關閉 statement
}
else {
    http_response_code(400);
    $response['message'] = '無效的操作。';
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}

$conn->close(); // 關閉資料庫連線
?>
