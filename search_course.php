<?php
require 'db.php';

// 取得查詢參數
$keyword = $_GET['keyword'] ?? '';
$type = $_GET['type'] ?? '';
$day = $_GET['day'] ?? '';
$dept = $_GET['dept'] ?? '';
$gened = $_GET['gened'] ?? '';

// 根據課號判斷通識課群名稱
function getGenEdGroup($code) {
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

// 關鍵字查詢
if (!empty($keyword)) {
    $sql .= " AND (課程名稱 LIKE CONCAT('%', ?, '%') OR 教師 LIKE CONCAT('%', ?, '%') OR 課程代碼 LIKE CONCAT('%', ?, '%'))";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "sss";
}

// 修別查詢（通識 → 用課程代碼判斷）
if (!empty($type)) {
    if ($type === '通識') {
        $sql .= " AND (
            課程代碼 IN ('GE111', 'GE112') OR
            (課程代碼 BETWEEN 'GE121' AND 'GE138') OR
            (課程代碼 BETWEEN 'GE161' AND 'GE204') OR
            (課程代碼 BETWEEN 'GE001' AND 'GE016') OR
            (課程代碼 BETWEEN 'GE151' AND 'GE154') OR
            (課程代碼 = 'GE250' OR 課程代碼 BETWEEN 'GE500' AND 'GE599') OR
            (課程代碼 BETWEEN 'GE300' AND 'GE330' OR 課程代碼 BETWEEN 'GE420' AND 'GE429') OR
            (課程代碼 BETWEEN 'GE430' AND 'GE499' OR 課程代碼 BETWEEN 'GE410' AND 'GE419') OR
            (課程代碼 BETWEEN 'GE259' AND 'GE274' OR 課程代碼 BETWEEN 'GE331' AND 'GE333') OR
            (課程代碼 BETWEEN 'GE275' AND 'GE299') OR
            (課程代碼 BETWEEN 'GE280' AND 'GE289' OR 課程代碼 BETWEEN 'GE650' AND 'GE655')
        )";
    } else {
        $sql .= " AND 修別 = ?";
        $params[] = $type;
        $types .= "s";
    }
}

// 星期查詢
if (!empty($day)) {
    $sql .= " AND 時間 LIKE CONCAT('%星期', ?, '%')";
    $params[] = $day;
    $types .= "s";
}

// 開課單位查詢
if (!empty($dept)) {
    $sql .= " AND 課程代碼 LIKE CONCAT(?, '%')";
    $params[] = $dept;
    $types .= "s";
}

// 課群篩選（以課程代碼判斷）
if (!empty($gened)) {
    $sql .= " AND (";
    switch ($gened) {
        case '中文能力': $sql .= "課程代碼 IN ('GE111','GE112')"; break;
        case '外語能力': $sql .= "課程代碼 BETWEEN 'GE121' AND 'GE138'"; break;
        case '共同教育': $sql .= "課程代碼 BETWEEN 'GE161' AND 'GE204'"; break;
        case '體育運動': $sql .= "(課程代碼 BETWEEN 'GE001' AND 'GE016' OR 課程代碼 BETWEEN 'GE151' AND 'GE154')"; break;
        case '人文藝術': $sql .= "(課程代碼 = 'GE250' OR 課程代碼 BETWEEN 'GE500' AND 'GE599')"; break;
        case '社會科學': $sql .= "(課程代碼 BETWEEN 'GE300' AND 'GE330' OR 課程代碼 BETWEEN 'GE420' AND 'GE429')"; break;
        case '自然科學': $sql .= "(課程代碼 BETWEEN 'GE430' AND 'GE499' OR 課程代碼 BETWEEN 'GE410' AND 'GE419')"; break;
        case '生命教育': $sql .= "(課程代碼 BETWEEN 'GE259' AND 'GE274' OR 課程代碼 BETWEEN 'GE331' AND 'GE333')"; break;
        case '生活教育': $sql .= "課程代碼 BETWEEN 'GE275' AND 'GE299'"; break;
        case '生涯教育': $sql .= "(課程代碼 BETWEEN 'GE280' AND 'GE289' OR 課程代碼 BETWEEN 'GE650' AND 'GE655')"; break;
    }
    $sql .= ")";
}

// 執行查詢
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$courses = [];
while ($row = $result->fetch_assoc()) {
    $row['通識課群'] = getGenEdGroup($row['課程代碼']);
    $courses[] = $row;
}

header('Content-Type: application/json');
echo json_encode($courses, JSON_UNESCAPED_UNICODE);
?>
