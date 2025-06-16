<?php
session_start(); // 啟動 session
require 'db.php'; // 確保 db.php 存在並包含資料庫連線資訊

// 檢查使用者是否已登入
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // HTTP 狀態碼 403 Forbidden
    echo json_encode(['error' => '未登入'], JSON_UNESCAPED_UNICODE); // 返回 JSON 錯誤訊息
    exit(); // 終止腳本執行
}

$user_id = $_SESSION['user_id']; // 取得 session 中的使用者 ID

// SQL 查詢：從 selected_courses 和 course_list 表格中獲取已選課程的名稱、時間、學分數 和 課程代碼
// 使用 COLLATE utf8mb4_unicode_ci 確保字元集比對一致性，避免潛在的匹配問題
$sql = "
SELECT cl.`科目名稱`, cl.`時間`, cl.`學分數`, cl.`課程代碼`  -- <-- 在這裡加上了 `cl.\`課程代碼\``
FROM selected_courses sc
JOIN course_list cl
  ON sc.course_code COLLATE utf8mb4_unicode_ci = cl.`課程代碼` COLLATE utf8mb4_unicode_ci
WHERE sc.user_id = ?
";

$stmt = $conn->prepare($sql); // 準備 SQL 語句

// SQL 準備錯誤處理
if (!$stmt) {
    http_response_code(500); // HTTP 狀態碼 500 Internal Server Error
    echo json_encode([
        'error' => '準備查詢失敗', // 友善的錯誤訊息
        'sql' => $sql, // 顯示有問題的 SQL 語句
        'mysqli_error' => $conn->error // 顯示詳細的資料庫錯誤訊息
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt->bind_param("i", $user_id); // 綁定使用者 ID 參數 (i 代表 integer)
$stmt->execute(); // 執行查詢
$result = $stmt->get_result(); // 獲取查詢結果

$selectedCourses = []; // 初始化一個空陣列來儲存已選課程

// 迴圈遍歷查詢結果，將每一行資料加入到 $selectedCourses 陣列中
while ($row = $result->fetch_assoc()) {
    $selectedCourses[] = $row;
}

// 將結果以 JSON 格式回傳，並確保中文字元正確顯示
echo json_encode($selectedCourses, JSON_UNESCAPED_UNICODE);

$stmt->close(); // 關閉 prepared statement
$conn->close(); // 關閉資料庫連線
?>