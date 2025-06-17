<?php
// 確保回應的 Content-Type 為 JSON，這是最重要的，必須在任何輸出之前
header('Content-Type: application/json');

require 'db.php'; // 確保 db.php 存在並包含資料庫連線資訊

// 初始化一個錯誤響應模板
$response = [
    'status' => 'error',
    'message' => '未知錯誤。'
];

// 使用 try-catch 區塊來捕獲潛在的錯誤
try {
    // 取得查詢參數，使用 ?? '' 提供預設空字串，避免未定義變數錯誤
    $keyword = $_GET['keyword'] ?? '';
    $type = $_GET['type'] ?? '';
    $day = $_GET['day'] ?? '';
    $dept = $_GET['dept'] ?? '';
    $general = $_GET['general'] ?? '';

    // 根據課程代碼判斷通識課群名稱的輔助函數
    function getGenEdGroup($code) {
        if ($code === null) return '';

        if (in_array($code, ['GE111', 'GE112'])) return '中文能力';
        if ($code >= 'GE121' && $code <= 'GE138') return '外語能力';
        if ($code >= 'GE161' && $code <= 'GE204') return '共同教育';
        if (($code >= 'GE001' && $code <= 'GE016') || ($code >= 'GE151' && $code <= 'GE154')) return '體育運動';
        if ($code == 'GE250' || ($code >= 'GE500' && $code <= 'GE599')) return '人文藝術';
        if (($code >= 'GE300' && $code <= 'GE330') || ($code >= 'GE420' && $code <= 'GE429')) return '社會科學';
        if (($code >= 'GE430' && $code <= 'GE499') || ($code >= 'GE410' && $code <= 'GE419')) return '自然科學';
        if (($code >= 'GE259' && $code <= 'GE274') || ($code >= 'GE331' && $code <= 'GE333')) return '生命教育';
        if ($code >= 'GE275' && $code <= 'GE299') return '生活教育';
        if (($code >= 'GE280' && $code <= 'GE289') || ($code >= 'GE650' && $code <= 'GE655')) return '生涯教育';
        return '';
    }

    $sql = "SELECT * FROM course_list WHERE 1=1";
    $params = [];
    $types = "";

    if (!empty($keyword)) {
        $sql .= " AND (科目名稱 LIKE CONCAT('%', ?, '%') OR 教師 LIKE CONCAT('%', ?, '%') OR 課程代碼 LIKE CONCAT('%', ?, '%'))";
        $params[] = $keyword;
        $params[] = $keyword;
        $params[] = $keyword;
        $types .= "sss";
    }

    if (!empty($type)) {
        if ($type !== '通識') {
            $sql .= " AND 修別 = ?";
            $params[] = $type;
            $types .= "s";
        }
    }

    if (!empty($day)) {
        if ($day === '無固定') {
            $sql .= " AND 時間 LIKE '%無固定時段%'";
        } else {
            $sql .= " AND 時間 LIKE CONCAT('%星期', ?, '%')";
            $params[] = $day;
            $types .= "s";
        }
    }

    if (!empty($dept)) {
        $sql .= " AND 課程代碼 LIKE CONCAT(?, '%')";
        $params[] = $dept;
        $types .= "s";
    }

    if (!empty($general)) {
        $sql .= " AND (";
        switch ($general) {
            case '中文': $sql .= "課程代碼 IN ('GE111','GE112')"; break;
            case '外語': $sql .= "課程代碼 BETWEEN 'GE121' AND 'GE138'"; break;
            case '共同': $sql .= "課程代碼 BETWEEN 'GE161' AND 'GE204'"; break;
            case '體育': $sql .= "(課程代碼 BETWEEN 'GE001' AND 'GE016' OR 課程代碼 BETWEEN 'GE151' AND 'GE154')"; break;
            case '人文': $sql .= "(課程代碼 = 'GE250' OR 課程代碼 BETWEEN 'GE500' AND 'GE599')"; break;
            case '社會': $sql .= "(課程代碼 BETWEEN 'GE300' AND 'GE330' OR 課程代碼 BETWEEN 'GE420' AND 'GE429')"; break;
            case '自然': $sql .= "(課程代碼 BETWEEN 'GE430' AND 'GE499' OR 課程代碼 BETWEEN 'GE410' AND 'GE419')"; break;
            case '生命': $sql .= "(課程代碼 BETWEEN 'GE259' AND 'GE274' OR 課程代碼 BETWEEN 'GE331' AND 'GE333')"; break;
            case '生活': $sql .= "課程代碼 BETWEEN 'GE275' AND 'GE299'"; break;
            case '生涯': $sql .= "(課程代碼 BETWEEN 'GE280' AND 'GE289' OR 課程代碼 BETWEEN 'GE650' AND 'GE655')"; break;
            default: $sql .= "1=1"; break; // Should not happen with current frontend, but as a safeguard
        }
        $sql .= ")";
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception('搜尋查詢準備失敗: ' . $conn->error);
    }

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$result) {
        throw new Exception('搜尋查詢執行失敗: ' . $conn->error);
    }

    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $row['通識課群'] = getGenEdGroup($row['課程代碼']);
        $courses[] = $row;
    }

    echo json_encode($courses, JSON_UNESCAPED_UNICODE);

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
