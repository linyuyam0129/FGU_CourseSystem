<?php
require 'db.php'; // 確保 db.php 存在並包含資料庫連線資訊

// 取得查詢參數，使用 ?? '' 提供預設空字串，避免未定義變數錯誤
$keyword = $_GET['keyword'] ?? '';
$type = $_GET['type'] ?? '';
$day = $_GET['day'] ?? '';
$dept = $_GET['dept'] ?? '';
$general = $_GET['general'] ?? ''; // 注意：前端的 ID 是 filter-general，這裡變數名稱統一為 $general

// 根據課程代碼判斷通識課群名稱的輔助函數
// 這個函數的目的是根據課程代碼推斷其所屬的通識課群，用於在搜尋結果中顯示或內部邏輯判斷
function getGenEdGroup($code) {
    if ($code === null) return ''; // 處理課程代碼為 null 的情況

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
    return ''; // 如果不屬於任何通識課群
}

$sql = "SELECT * FROM course_list WHERE 1=1"; // 基礎 SQL 語句，1=1 方便後續條件的追加
$params = []; // 用於儲存 SQL 查詢的參數值
$types = ""; // 用於儲存 bind_param 所需的參數類型字串

// 關鍵字查詢：模糊匹配課程名稱、教師或課程代碼
if (!empty($keyword)) {
    $sql .= " AND (課程名稱 LIKE CONCAT('%', ?, '%') OR 教師 LIKE CONCAT('%', ?, '%') OR 課程代碼 LIKE CONCAT('%', ?, '%'))";
    $params[] = $keyword;
    $params[] = $keyword;
    $params[] = $keyword;
    $types .= "sss"; // 三個字串類型參數
}

// 修別查詢：根據課程修別篩選
if (!empty($type)) {
    // 這裡我們假設 $type 只會有 '必修' 或 '選修'。
    // 通識課的篩選邏輯由 $general 參數處理。
    if ($type !== '通識') { // 避免重複處理通識邏輯，如果前端有傳 '通識' 這個值，這裡不處理
        $sql .= " AND 修別 = ?";
        $params[] = $type;
        $types .= "s";
    }
}

// 星期查詢：根據課程時間中的星期篩選
if (!empty($day)) {
    // 特別處理「無固定時段授課」的課程，其時間欄位通常包含「無固定時段」字樣
    if ($day === '無固定') {
        $sql .= " AND 時間 LIKE '%無固定時段%'";
    } else {
        $sql .= " AND 時間 LIKE CONCAT('%星期', ?, '%')";
        $params[] = $day;
        $types .= "s";
    }
}

// 開課單位查詢：根據課程代碼的前綴篩選 (例如 'CT' 代表創意與科技學院)
if (!empty($dept)) {
    $sql .= " AND 課程代碼 LIKE CONCAT(?, '%')";
    $params[] = $dept;
    $types .= "s";
}

// 通識課群篩選：根據前端傳來的課群代碼進行篩選
if (!empty($general)) {
    $sql .= " AND ("; // 開始一個括號，用於組合多個 OR 條件
    // switch-case 語句根據 $general 的值來構建 SQL 條件
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
        default: // 如果 $general 參數有值但不在預期的 case 內，則不加任何篩選條件
            $sql .= "1=1"; // 避免語法錯誤，效果是不過濾
            break;
    }
    $sql .= ")"; // 結束括號
}

$stmt = $conn->prepare($sql); // 準備 SQL 語句

// 檢查 prepare 是否成功
if (!$stmt) {
    http_response_code(500);
    echo json_encode([
        'error' => '搜尋查詢準備失敗',
        'sql' => $sql,
        'mysqli_error' => $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 如果有參數需要綁定，則進行綁定操作
if (!empty($params)) {
    // 使用 call_user_func_array 處理動態參數數量，將 $types 和 $params 合併後傳遞
    // PHP 5.6+ 可以使用 $stmt->bind_param($types, ...$params); 這種更簡潔的方式
    $stmt->bind_param($types, ...$params);
}
$stmt->execute(); // 執行查詢
$result = $stmt->get_result(); // 獲取查詢結果

// 檢查執行是否成功
if (!$result) {
    http_response_code(500);
    echo json_encode([
        'error' => '搜尋查詢執行失敗',
        'mysqli_error' => $conn->error
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$courses = [];
while ($row = $result->fetch_assoc()) {
    // 在返回給前端之前，將通識課群名稱添加到每個課程的資料中
    $row['通識課群'] = getGenEdGroup($row['課程代碼']); 
    $courses[] = $row;
}

// 設定回應的 Content-Type 為 application/json
header('Content-Type: application/json');
// 將課程資料轉換為 JSON 格式並輸出
echo json_encode($courses, JSON_UNESCAPED_UNICODE);

// 關閉 statement 和資料庫連線
$stmt->close();
$conn->close();
?>