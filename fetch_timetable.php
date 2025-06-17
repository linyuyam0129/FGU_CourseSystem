<?php
session_start(); // 啟動 session

// 確保回應的 Content-Type 為 JSON，這是最重要的，必須在任何輸出之前
header('Content-Type: application/json');

require 'db.php'; // 確保 db.php 存在並包含資料庫連線資訊

// 初始化一個錯誤響應模板
$response = [
    'status' => 'error',
    'message' => '未知錯誤。'
];

try {
    // 檢查使用者是否已登入
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403); // HTTP 狀態碼 403 Forbidden
        $response['message'] = '未登入。';
        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit();
    }

    $user_id = $_SESSION['user_id']; // 取得 session 中的使用者 ID

    // SQL 查詢：從 selected_courses 和 course_list 表格中獲取已選課程的名稱、時間、學分數 和 課程代碼
    // 使用 COLLATE utf8mb4_unicode_ci 確保字元集比對一致性，避免潛在的匹配問題
    $sql = "
    SELECT cl.`科目名稱`, cl.`時間`, cl.`學分數`, cl.`課程代碼`
    FROM selected_courses sc
    JOIN course_list cl
      ON sc.course_code COLLATE utf8mb4_unicode_ci = cl.`課程代碼` COLLATE utf8mb4_unicode_ci
    WHERE sc.user_id = ?
    ";

    $stmt = $conn->prepare($sql); // 準備 SQL 語句

    // SQL 準備錯誤處理
    if (!$stmt) {
        throw new Exception('準備查詢已選課程失敗: ' . $conn->error);
    }

    $stmt->bind_param("i", $user_id); // 綁定使用者 ID 參數 (i 代表 integer)
    $stmt->execute(); // 執行查詢
    $result = $stmt->get_result(); // 獲取查詢結果

    // 檢查執行是否成功
    if (!$result) {
        throw new Exception('執行查詢已選課程失敗: ' . $conn->error);
    }

    $selectedCourses = []; // 初始化一個空陣列來儲存已選課程
    // 迴圈遍歷查詢結果，將每一行資料加入到 $selectedCourses 陣列中
    while ($row = $result->fetch_assoc()) {
        $selectedCourses[] = $row;
    }

    // 將結果以 JSON 格式回傳，並確保中文字元正確顯示
    echo json_encode($selectedCourses, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    // 捕獲所有異常並以 JSON 格式返回錯誤訊息
    http_response_code(500); // Internal Server Error
    $response['message'] = '伺服器錯誤: ' . $e->getMessage();
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} finally {
    // 確保在任何情況下都會關閉 statement 和連線
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>
