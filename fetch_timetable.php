<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => '未登入']);
    exit();
}

$user_id = $_SESSION['user_id'];

// 強制使用相同 collation 解決比對錯誤
$sql = "
SELECT cl.`課程名稱`, cl.`時間`, cl.`學分`
FROM selected_courses sc
JOIN course_list cl 
  ON sc.course_code COLLATE utf8mb4_unicode_ci = cl.`課程代碼` COLLATE utf8mb4_unicode_ci
WHERE sc.user_id = ?
";

$stmt = $conn->prepare($sql);

// SQL 錯誤處理
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Prepare failed',
        'sql' => $sql,
        'mysqli_error' => $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Execute failed',
        'mysqli_error' => $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 組合資料
$courses = [];
while ($row = $result->fetch_assoc()) {
    $courses[] = $row;
}

// 正常輸出 JSON
header('Content-Type: application/json');
echo json_encode($courses, JSON_UNESCAPED_UNICODE);
