<?php
session_start();
require 'db.php'; // 假設 db.php 處理資料庫連線

// 檢查使用者是否已登入，若否則導向登入頁面
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// 從 Session 中獲取使用者資訊
// 使用 null coalescing operator (??) 確保即使 Session 變數未設定也不會報錯
$user_name = $_SESSION['name'] ?? '未知使用者';
$student_id = $_SESSION['student_id'] ?? '未知學號';
$user_department = $_SESSION['department'] ?? '';
$user_group = $_SESSION['user_group'] ?? ''; // 確保有組別資訊，如 '遊戲組' 或 '流音組'

// --- 步驟 1: 從 'courses' 表中獲取所有課程詳細資訊 ---
// 這是所有可用課程的主列表，以 course_code 為鍵，方便快速查找
// 包含 course_type 和 category，因為它們用於後續的學分歸類
$sql_all_courses = "SELECT `course_code`, `course_name`, `credits`, `course_type`, `category` FROM `courses`";
$stmt_all_courses = $conn->prepare($sql_all_courses);
if (!$stmt_all_courses) {
    die("準備所有課程基本資料查詢失敗: " . $conn->error);
}
$stmt_all_courses->execute();
$result_all_courses = $stmt_all_courses->get_result();

$courses_master_list = []; // 以 course_code 作為鍵的課程詳細資料
while ($row = $result_all_courses->fetch_assoc()) {
    $courses_master_list[$row['course_code']] = $row;
}
$stmt_all_courses->close();


// --- 步驟 2: 獲取使用者已完成和已選課程，並合併計算 ---
// 儲存所有已完成/已選課程的詳細資料，包含學期，用於學分計算和顯示
$user_combined_courses_details = [];
$total_combined_credits = 0;
$processed_course_codes = []; // 用於追蹤已處理的課程代碼，避免重複

// 2a. 先從 completed_courses 獲取
$sql_completed = "SELECT course_code, semester FROM completed_courses WHERE user_id = ?";
$stmt_completed = $conn->prepare($sql_completed);
if ($stmt_completed) {
    $stmt_completed->bind_param("i", $user_id);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    while ($row = $result_completed->fetch_assoc()) {
        $course_code = $row['course_code'];
        if (isset($courses_master_list[$course_code]) && !in_array($course_code, $processed_course_codes)) {
            $course_detail = $courses_master_list[$course_code];
            $user_combined_courses_details[] = array_merge($course_detail, ['semester' => $row['semester'], 'status' => '已完成']);
            $total_combined_credits += $course_detail['credits'];
            $processed_course_codes[] = $course_code; // 標記為已處理
        }
    }
    $stmt_completed->close();
} else {
    error_log("準備已完成課程查詢失敗: " . $conn->error);
}

// 2b. 再從 selected_courses 獲取 (只加入未在 completed_courses 中的課程)
$sql_selected = "SELECT course_code, semester FROM selected_courses WHERE user_id = ?";
$stmt_selected = $conn->prepare($sql_selected);
if ($stmt_selected) {
    $stmt_selected->bind_param("i", $user_id);
    $stmt_selected->execute();
    $result_selected = $stmt_selected->get_result();
    while ($row = $result_selected->fetch_assoc()) {
        $course_code = $row['course_code'];
        // 只有當課程在 master list 中且未被處理過 (即不在 completed_courses 中) 才加入
        if (isset($courses_master_list[$course_code]) && !in_array($course_code, $processed_course_codes)) {
            $course_detail = $courses_master_list[$course_code];
            $user_combined_courses_details[] = array_merge($course_detail, ['semester' => $row['semester'], 'status' => '已選']);
            $total_combined_credits += $course_detail['credits'];
            $processed_course_codes[] = $course_code; // 標記為已處理
        }
    }
    $stmt_selected->close();
} else {
    error_log("準備已選課程查詢失敗: " . $conn->error);
}

// 根據學期對合併後的課程進行排序（可選，使顯示更有序）
usort($user_combined_courses_details, function($a, $b) {
    // 假設學期格式為 YYYY-S (例如 2024-1 或 113-1)
    return strcmp($a['semester'], $b['semester']);
});


// --- 步驟 3: 從 program_definitions 表中獲取學程要求和學分範圍 ---
$program_credit_ranges = [];
$program_ids_map = []; // 將 program_name 映射到 program_id
$sql_program_defs = "SELECT program_id, program_name, min_credits, max_credits FROM program_definitions";
$stmt_program_defs = $conn->prepare($sql_program_defs);
if (!$stmt_program_defs) {
    die("準備學程定義查詢失敗: " . $conn->error);
}
$stmt_program_defs->execute();
$result_program_defs = $stmt_program_defs->get_result();
while ($row = $result_program_defs->fetch_assoc()) {
    $program_credit_ranges[$row['program_name']] = ['min' => $row['min_credits'], 'max' => $row['max_credits']];
    $program_ids_map[$row['program_name']] = $row['program_id'];
}
$stmt_program_defs->close();

// 設置畢業總學分要求 (如果 program_definitions 中有總學分要求，應該從那裡獲取)
$graduation_credits_required = $program_credit_ranges['總畢業學分']['min'] ?? 128; // 假設有一個 '總畢業學分' 的定義


// --- 步驟 4: 計算學程進度並識別未達成要求 ---
$unfulfilled_requirements = []; // 儲存所有未完成的要求訊息
$earned_program_credits = [ // 儲存每個學程已獲得的學分
    '通識學程' => 0,
    '院跨領域特色學程' => 0,
    '領域核心學程' => 0,
    '領域專業學程' => 0,
];
$completed_general_education_groups = []; // 追蹤通識學程各課群是否已修讀 (至少一門)
$general_education_group_credits = []; // 追蹤通識學程各課群已獲得的學分

// 定義通識課群的最低課程和學分要求
$general_education_groups_with_min_credits = [
    '中文能力' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '外語能力' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '人文藝術學群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '社會科學課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '自然科學課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '生命教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '生活教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '生涯教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '共同教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 0] // 體育等 0 學分課程
];

// 輔助函數：根據課程代碼判斷通識課群名稱
// 該函數應該與 search_course.php 中的邏輯一致
function getGenEdGroup($code) {
    if ($code === null) return '';

    if (in_array($code, ['GE111', 'GE112'])) return '中文能力';
    if ($code >= 'GE121' && $code <= 'GE138') return '外語能力';
    if ($code >= 'GE161' && $code <= 'GE204') return '共同教育課群'; // 更明確的名稱
    if (($code >= 'GE001' && $code <= 'GE016') || ($code >= 'GE151' && $code <= 'GE154')) return '體育運動課群';
    if ($code == 'GE250' || ($code >= 'GE501' && $code <= 'GE599')) return '人文藝術學群';
    if (($code >= 'GE205' && $code <= 'GE249') || ($code >= 'GE430' && $code <= 'GE499') || ($code >= 'GE410' && $code <= 'GE419')) return '社會科學課群';
    if (($code >= 'GE251' && $code <= 'GE258') || ($code >= 'GE301' && $code <= 'GE330')) return '自然科學課群';
    if (($code >= 'GE259' && $code <= 'GE274') || ($code >= 'GE331' && $code <= 'GE333')) return '生命教育課群';
    if ($code >= 'GE275' && $code <= 'GE299') return '生活教育課群';
    if (($code >= 'GE280' && $code <= 'GE289') || ($code >= 'GE650' && $code <= 'GE655')) return '生涯教育課群';

    return ''; // 如果不屬於任何通識課群
}


// 用於累積各學程學分 (更精確的 Chart.js 資料準備)
$display_chart_credits = []; // 儲存用於 Chart.js 的學分數據
$display_chart_colors = []; // 儲存用於 Chart.js 的顏色數據
$chart_labels = []; // 儲存用於 Chart.js 的標籤

// 定義顏色映射 (與 index.php 的圖表顏色一致)
$chart_color_map = [
    '必修' => '#FF6384',
    '選修' => '#36A2EB',
    '通識' => '#FFCE56', // 通識總體顏色
    '共同教育課群' => '#4BC0C0',
    '中文能力' => '#E0BBE4', // 柔和的紫羅蘭色
    '外語能力' => '#957DAD', // 較深的紫羅蘭色
    '人文藝術學群' => '#FFD1DC', // 淺粉色
    '社會科學課群' => '#A2D5F2', // 天藍色
    '自然科學課群' => '#FFEBCC', // 淺橙色
    '生命教育課群' => '#D4EDDA', // 淺綠色
    '生活教育課群' => '#FDEBD0', // 淺棕色
    '生涯教育課群' => '#D7BDE2', // 薰衣草紫
    '體育運動課群' => '#C3E6CB', // 另一種淺綠
    '其他' => '#9966FF', // 紫色
    '未知類型' => '#CCCCCC' // 灰色
];

// 重新計算各學程學分，確保 Chart.js 數據的精確性
foreach ($user_combined_courses_details as $course_detail) {
    $credits = (int)$course_detail['credits'];
    $course_code = $course_detail['course_code'];
    $course_type = $course_detail['course_type'] ?? '未知類型';
    $category_from_db = $course_detail['category'] ?? ''; // 從 courses 表中獲取的 category

    if ($course_type === '必修') {
        if (!isset($display_chart_credits['必修'])) {
            $display_chart_credits['必修'] = 0;
        }
        $display_chart_credits['必修'] += $credits;
    } elseif ($course_type === '選修') {
        if (!isset($display_chart_credits['選修'])) {
            $display_chart_credits['選修'] = 0;
        }
        $display_chart_credits['選修'] += $credits;
    } elseif ($course_type === '通識') {
        $gen_ed_group = getGenEdGroup($course_code);
        if (!empty($gen_ed_group)) {
            if (!isset($display_chart_credits[$gen_ed_group])) {
                $display_chart_credits[$gen_ed_group] = 0;
            }
            $display_chart_credits[$gen_ed_group] += $credits;

            // 同時累積通識學程總學分
            $earned_program_credits['通識學程'] += $credits;
            $completed_general_education_groups[$gen_ed_group] = true; // 標記課群已修
            if (!isset($general_education_group_credits[$gen_ed_group])) {
                $general_education_group_credits[$gen_ed_group] = 0;
            }
            $general_education_group_credits[$gen_ed_group] += $credits;

        } else {
            // 如果通識課程沒有匹配到任何課群，歸類到「其他通識」
            if (!isset($display_chart_credits['其他通識'])) {
                $display_chart_credits['其他通識'] = 0;
            }
            $display_chart_credits['其他通識'] += $credits;
        }
    } else {
        // 其他或未知類型課程
        if (!isset($display_chart_credits['其他'])) {
            $display_chart_credits['其他'] = 0;
        }
        $display_chart_credits['其他'] += $credits;
    }
}

// 根據 display_chart_credits 準備 Chart.js 的最終數據
$chart_labels = array_keys($display_chart_credits);
$chart_data = array_values($display_chart_credits);
$chart_background_colors = [];

foreach ($chart_labels as $label) {
    $chart_background_colors[] = $chart_color_map[$label] ?? '#CCCCCC'; // 預設灰色
}


// --- 通識學程檢查訊息 ---
$ge_missing_messages = [];
$ge_required_total = $program_credit_ranges['通識學程']['min'] ?? 32;
if ($earned_program_credits['通識學程'] < $ge_required_total) {
    $ge_missing_messages[] = "通識學程總學分不足，目前已修 {$earned_program_credits['通識學程']} 學分，需 {$ge_required_total} 學分。";
}

foreach ($general_education_groups_with_min_credits as $group => $req) {
    $current_credits = $general_education_group_credits[$group] ?? 0;
    $has_completed_at_least_one = isset($completed_general_education_groups[$group]);

    if (!$has_completed_at_least_one) {
        if ($req['min_credits_per_course'] > 0) {
            $ge_missing_messages[] = "【{$group}】尚未修讀，需至少修一門 {$req['min_credits_per_course']} 學分課程。";
        } else {
            $ge_missing_messages[] = "【{$group}】尚未修讀。";
        }
    } else if ($req['min_credits_per_course'] > 0 && $current_credits < $req['min_credits_per_course']) {
        $ge_missing_messages[] = "【{$group}】已修課但學分不足，目前已修 {$current_credits} 學分，需至少 {$req['min_credits_per_course']} 學分。";
    }
}

if (!empty($ge_missing_messages)) {
    $unfulfilled_requirements['通識學程'] = $ge_missing_messages;
}


// 定義簡化後的學分目標 (如果 program_definitions 中沒有提供，使用這些預設值)
$simplified_core_credits_required = $program_credit_ranges['領域核心學程']['min'] ?? 40; // 預設 40 學分
$simplified_professional_credits_required = $program_credit_ranges['領域專業學程']['min'] ?? 25; // 預設 25 學分
$simplified_interdisciplinary_credits_required = $program_credit_ranges['院跨領域特色學程']['min'] ?? 9; // 預設 9 學分

// 用於累積各學程學分
$core_earned_credits = 0;
$professional_earned_credits = 0;
$interdisciplinary_earned_credits = 0;

// Arrays to hold details of completed courses specific to these programs for display
$completed_core_courses_details = [];
$completed_professional_courses_details = [];
$completed_interdisciplinary_courses_details = [];

// 遍歷所有已完成/已選課程，計算各學程學分並收集詳細資訊
foreach ($user_combined_courses_details as $course_detail) {
    // 檢查是否為通識學程，如果是則跳過 (通識已單獨處理，避免重複計算)
    if ($course_detail['course_type'] === '通識') {
        continue;
    }

    // 判斷是否為用戶系所的課程
    // 這裡的判斷方式需要根據您的實際數據結構進行調整。
    // 假設 user_department (例如 'CS') 會是 course_code 的前綴，或者 course_type 更精確地指示系所
    $is_department_course = (strpos($course_detail['course_code'], $user_department) === 0);

    if ($course_detail['course_type'] === '必修' && $is_department_course) {
        // 判斷為領域核心學程 (系所必修)
        $core_earned_credits += $course_detail['credits'];
        $completed_core_courses_details[] = $course_detail;
    } else if ($course_detail['course_type'] === '選修' && $is_department_course) {
        // 判斷為領域專業學程 (系所選修)
        $professional_earned_credits += $course_detail['credits'];
        $completed_professional_courses_details[] = $course_detail;
    } else if ($course_detail['course_type'] === '選修' && !$is_department_course) {
        // 判斷為院跨領域特色學程 (非系所的選修)
        $interdisciplinary_earned_credits += $course_detail['credits'];
        $completed_interdisciplinary_courses_details[] = $course_detail;
    }
    // 其他課程類型或不符合上述規則的課程，將不計入這三個學程的學分
}

$earned_program_credits['領域核心學程'] = $core_earned_credits;
$earned_program_credits['領域專業學程'] = $professional_earned_credits;
$earned_program_credits['院跨領域特色學程'] = $interdisciplinary_earned_credits;


// --- 獲取所有未完成必修課程 (用於 "未完成必修課程" 標籤頁 和 領域核心學程的未修課程) ---
$sql_all_missing_required = "
    SELECT c.`course_code`, c.`course_name`, c.`credits`, c.`course_type`, c.`category`
    FROM `courses` c
    WHERE c.`course_type` = '必修'
    AND c.`course_code` NOT IN (
        SELECT course_code FROM completed_courses WHERE user_id = ?
        UNION
        SELECT course_code FROM selected_courses WHERE user_id = ?
    )
    ORDER BY c.category, c.course_code;
";
$stmt_all_missing_required = $conn->prepare($sql_all_missing_required);
$all_missing_required_courses_list = []; // 初始化為空陣列
if (!$stmt_all_missing_required) {
    error_log("準備所有未完成必修課程查詢失敗: " . $conn->error);
} else {
    $stmt_all_missing_required->bind_param("ii", $user_id, $user_id);
    $stmt_all_missing_required->execute();
    $temp_all_missing_required_result = $stmt_all_missing_required->get_result(); // 使用臨時變數

    if ($temp_all_missing_required_result) { // 檢查 get_result() 是否成功
        while ($row = $temp_all_missing_required_result->fetch_assoc()) {
            $all_missing_required_courses_list[] = $row; // 填充列表
        }
        $temp_all_missing_required_result->close(); // 關閉結果集
    } else {
        error_log("獲取所有未完成必修課程結果失敗: " . $stmt_all_missing_required->error);
    }
    $stmt_all_missing_required->close(); // 關閉語句
}


// --- 檢查領域核心學程 ---
$missing_core_messages = [];
if ($earned_program_credits['領域核心學程'] < $simplified_core_credits_required) {
    $missing_core_messages[] = "學分不足，目前已修 {$earned_program_credits['領域核心學程']} 學分，需 {$simplified_core_credits_required} 學分。";
}

// 收集實際未修的系所必修課程列表
$missing_core_courses_for_display = [];
foreach ($all_missing_required_courses_list as $missing_course) {
    // 再次判斷是否為系所必修，與上方計算學分邏輯一致
    $is_missing_department_course = (strpos($missing_course['course_code'], $user_department) === 0);

    if ($missing_course['course_type'] === '必修' && $is_missing_department_course) {
        $missing_core_messages[] = "未修讀：{$missing_course['course_name']} ({$missing_course['course_code']}) - {$missing_course['credits']} 學分";
        $missing_core_courses_for_display[] = $missing_course; // 儲存供顯示
    }
}
if (!empty($missing_core_messages)) {
    $unfulfilled_requirements['領域核心學程'] = $missing_core_messages;
} else {
    unset($unfulfilled_requirements['領域核心學程']);
}


// --- 檢查領域專業學程 ---
if ($earned_program_credits['領域專業學程'] < $simplified_professional_credits_required) {
    $unfulfilled_requirements['領域專業學程'] = ["學分不足，目前已修 {$earned_program_credits['領域專業學程']} 學分，需 {$simplified_professional_credits_required} 學分。"];
} else {
    unset($unfulfilled_requirements['領域專業學程']);
}

// --- 檢查院跨領域特色學程 ---
if ($earned_program_credits['院跨領域特色學程'] < $simplified_interdisciplinary_credits_required) {
    $unfulfilled_requirements['院跨領域特色學程'] = ["學分不足，目前已修 {$earned_program_credits['院跨領域特色學程']} 學分，需 {$simplified_interdisciplinary_credits_required} 學分。"];
} else {
    unset($unfulfilled_requirements['院跨領域特色學程']);
}


// --- 總畢業學分檢查 ---
if ($total_combined_credits < $graduation_credits_required) {
    $unfulfilled_requirements['總學分不足'] = ["目前總學分 {$total_combined_credits}，距離畢業所需 {$graduation_credits_required} 學分尚有不足。"];
} else {
    unset($unfulfilled_requirements['總學分不足']);
}


?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>畢業門檻狀態</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* --- 整體佈局與基礎樣式 --- */
        body {
            font-family: 'Noto Sans TC', 'Segoe UI', sans-serif;
            background-color: #F0F2F5; /* 淺色背景，增加對比度 */
            color: #343a40;
            margin: 0;
            line-height: 1.6;
            overflow-x: hidden; /* 防止在小螢幕上出現水平捲軸 */
        }

        .header {
            background-color: #3f51b5; /* 深藍色標頭 */
            color: white;
            padding: 15px 30px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* 增加陰影，提升層次感 */
            font-weight: 700; /* 更粗的字體 */
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-buttons {
            display: flex;
            gap: 15px;
        }

        .header-buttons button {
            background-color: #ffffff;
            color: #3f51b5;
            border: 1px solid #3f51b5;
            padding: 9px 18px;
            border-radius: 25px; /* 更圓的圓角 */
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 15px;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .header-buttons button:hover {
            background-color: #3f51b5;
            color: white;
            box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            transform: translateY(-2px); /* 懸停時輕微上浮 */
        }
        .header-buttons button[onclick*="login.php"] {
            background-color: #f44336; /* 警告紅色 */
            color: white;
            border-color: #f44336;
        }
        .header-buttons button[onclick*="login.php"]:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }

        .container {
            display: flex;
            gap: 25px; /* 一致的間距 */
            padding: 25px;
            flex-wrap: wrap;
            justify-content: center; /* 面板換行時居中 */
        }

        .panel {
            flex: 1;
            background: white;
            border-radius: 15px; /* 更圓的圓角 */
            padding: 25px 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            min-width: 400px; /* 內容最小寬度稍大 */
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .panel:hover {
            transform: translateY(-3px); /* 面板懸停時輕微上浮 */
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            cursor: pointer; /* 指示可折疊性 */
            padding-bottom: 10px; /* Add padding to separate from content */
            border-bottom: 1px solid #eee; /* Light separator */
        }
        .panel-header.collapsed .toggle-icon::before {
            content: "\f0d7"; /* Font Awesome 向下箭頭 */
        }
        .panel-header .toggle-icon::before {
            content: "\f0d8"; /* Font Awesome 向上箭頭 */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-left: 10px;
            color: #777;
            transition: transform 0.3s ease; /* Smooth rotation */
        }
        .panel-header.collapsed .toggle-icon::before {
            transform: rotate(180deg); /* Rotate for collapsed state */
        }

        h3 {
            color: #3f51b5; /* 與標頭藍色一致 */
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
        }
        h3 i {
            margin-right: 10px;
            font-size: 22px;
            color: #4CAF50; /* 綠色用於區塊圖標 */
        }
        h3.info i { color: #2196f3; } /* 藍色用於資訊 */
        h3.input-section i { color: #FF9800; } /* 橙色用於輸入 */
        h3.credit i { color: #673ab7; } /* 紫色用於學分 */
        h3.course-list i { color: #009688; } /* 青色用於課程列表 */
        h3.analysis i { color: #8BC34A; } /* 分析圖標的淺綠色 */

        /* 可折疊內容 */
        .panel-content {
            max-height: 1000px; /* 過渡的最大高度 */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
            padding-top: 15px; /* Add padding after header */
        }
        .panel-content.hidden {
            max-height: 0;
            opacity: 0;
            padding-top: 0; /* Remove padding when hidden */
        }

        /* 資訊區塊樣式 */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            font-size: 16px;
            color: #555;
            padding: 10px 0;
        }
        .info-grid div span {
            font-weight: 600;
            color: #343a40;
            margin-right: 5px;
        }

        /* 輸入區塊樣式 */
        .input-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }
        .input-group select,
        .input-group input[type="text"] {
            flex: 1 1 calc(50% - 12px);
            padding: 12px 15px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            font-size: 16px;
            background-color: #fcfcfc;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .input-group input[type="text"]::placeholder {
            color: #999;
        }
        .input-group input[type="text"]:focus,
        .input-group select:focus {
            border-color: #3f51b5;
            box-shadow: 0 0 0 4px rgba(63, 81, 181, 0.2);
            outline: none;
        }
        .input-group button {
            background-color: #4CAF50; /* 成功綠色 */
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            flex-grow: 1;
            font-size: 16px;
        }
        .input-group button:hover {
            background-color: #43A047;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transform: translateY(-1px);
        }

        /* 學分顯示與進度條 - (This section appears to be unused in the HTML structure,
           as the credit display is handled by the chart boxes. I'll keep the styles
           but note its potential redundancy.) */
        .credit-display {
            background-color: #e8f5e9; /* 淺綠色背景 */
            border: 1px solid #c8e6c9;
            border-radius: 10px;
            padding: 20px 25px;
            margin-top: 20px;
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #2e7d32;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .credit-display span {
            color: #3f51b5; /* 以主藍色高亮 */
            font-size: 28px;
            display: block; /* 新行以強調 */
            margin-bottom: 8px;
        }
        .credit-display small {
            display: block;
            margin-top: 10px;
            font-size: 14px;
            color: #546e7a;
            line-height: 1.5;
        }

        .progress-bar-container {
            width: 100%;
            background-color: #e0e0e0;
            border-radius: 5px;
            height: 15px;
            overflow: hidden;
            margin-top: 15px;
        }
        .progress-bar-fill {
            height: 100%;
            background-color: #4CAF50; /* 綠色填充 */
            width: 0%; /* 將由 JS 設定 */
            border-radius: 5px;
            text-align: center;
            color: white;
            line-height: 15px;
            font-size: 10px;
            transition: width 0.8s ease-out; /* 平滑過渡 */
        }
        .progress-bar-fill.warning {
            background-color: #FFC107; /* 黃色警告 */
        }
        .progress-bar-fill.danger {
            background-color: #F44336; /* 紅色低進度 */
        }


        /* 標籤頁課程列表 */
        .tabs {
            display: flex;
            margin-bottom: 15px;
            border-bottom: 2px solid #e0e0e0;
        }
        .tab-button {
            flex: 1;
            padding: 12px 0;
            text-align: center;
            cursor: pointer;
            font-weight: 600;
            color: #777;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        .tab-button.active {
            color: #3f51b5;
            border-bottom-color: #3f51b5;
            background-color: #f9f9f9;
        }
        .tab-button:hover:not(.active) {
            color: #555;
            background-color: #f0f0f0;
        }

        .tab-content {
            display: none;
            padding-top: 10px; /* Add some padding to content below tabs */
        }
        .tab-content.active {
            display: block;
        }

        .course-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
            max-height: 400px; /* 固定高度以實現捲動列表 */
            overflow-y: auto; /* 啟用捲動 */
            padding-right: 10px; /* 捲軸空間 */
        }
        .course-list ul::-webkit-scrollbar {
            width: 8px;
        }
        .course-list ul::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .course-list ul::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .course-list ul::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .course-list li {
            background: #fefefe;
            border: 1px solid #e9ecef;
            border-left: 5px solid #3f51b5; /* 主色高亮 */
            padding: 12px 18px;
            margin-bottom: 10px;
            border-radius: 10px;
            font-size: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            flex-direction: column;
            transition: all 0.2s ease;
        }
        .course-list li.added-highlight {
            animation: highlightFade 2s forwards; /* 新課程的動畫 */
        }
        @keyframes highlightFade {
            0% { background-color: #dcedc8; } /* 淺綠色 */
            100% { background-color: #fefefe; }
        }

        .course-list li strong {
            color: #343a40;
            font-size: 16px;
            margin-bottom: 4px;
        }
        .course-list li span {
            color: #6c757d;
            font-size: 14px;
        }
        .course-list.missing li {
            border-left-color: #EF5350; /* 缺少課程的紅色 */
        }

        /* 響應式調整 */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                align-items: center; /* 單列面板居中 */
                padding: 20px;
            }
            .panel {
                min-width: unset; /* 小螢幕移除最小寬度 */
                width: 100%; /* 佔滿寬度 */
                max-width: 550px; /* 可選：限制最大寬度以提高可讀性 */
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .header-buttons {
                margin-top: 15px;
                flex-wrap: wrap;
                justify-content: center;
                width: 100%;
            }
            .header-buttons button {
                flex: 1 1 auto; /* 允許按鈕換行 */
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .input-group select,
            .input-group input[type="text"] {
                flex: 1 1 100%;
            }
             /* 確保響應式佈局下，學程區塊也能良好顯示 */
            .program-analysis-panel {
                min-width: unset;
                width: 100%;
                max-width: 700px; /* 為了表格閱讀體驗，稍微放寬一些 */
            }
            /* The .program-section styles below are for elements not present in the provided HTML.
               Assuming these are for a hypothetical future expansion, I will keep them but note
               they don't apply to the current structure. */
            .program-overview {
                flex-direction: column;
                align-items: flex-end; /* 右側對齊 */
                gap: 5px;
            }
            .program-section .panel-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            .program-section .panel-header h4.program-title {
                font-size: 18px;
            }
            .program-section .panel-header .toggle-icon {
                position: absolute;
                right: 20px;
                top: 18px;
            }
            .program-analysis-panel th, .program-analysis-panel td {
                padding: 8px; /* 減少內邊距 */
                font-size: 13px; /* 縮小字體 */
            }
        }

        /* ----------------------- 學程分析區塊樣式 ----------------------- */
        .program-analysis-panel {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            display: flex;
            flex-direction: column;
            align-items: center; /* 圖表居中 */
        }

        .program-analysis-panel h3 {
            color: #3f51b5;
            font-size: 24px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        .program-analysis-panel h3 i {
            margin-right: 10px;
            font-size: 22px;
            color: #8BC34A;
        }

        /* Chart styles */
        .chart-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center; /* Centered charts */
            gap: 40px; /* Increased gap between charts */
            margin-top: 20px;
            width: 100%; /* Take full width */
        }

        .chart-box {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 25px; /* Increased padding */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            text-align: center;
            flex: 1; /* Allow charts to grow and shrink */
            max-width: 400px; /* Adjusted max-width for larger charts in two columns */
            min-width: 280px; /* Ensure charts are large enough on smaller screens */
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .chart-box h4 {
            color: #3f51b5;
            margin-top: 0;
            margin-bottom: 20px; /* Increased margin below title */
            font-size: 1.5em; /* Larger title */
            font-weight: 700;
        }

        .chart-box canvas {
            max-width: 100%; /* Make canvas responsive within its box */
            height: auto; /* Maintain aspect ratio */
            min-height: 200px; /* Ensure a minimum height for the canvas */
            max-height: 300px; /* Set a maximum height to control size */
        }

        .chart-box p {
            font-size: 1.1em;
            margin-top: 15px;
            font-weight: 600;
            color: #555;
        }

        /* New styles for notification */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: #4CAF50; /* Green for success */
            color: white;
            padding: 15px 25px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.5s forwards, fadeOut 0.5s 2.5s forwards;
            font-weight: 600;
        }
        .notification.error {
            background-color: #F44336; /* Red for error */
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeOut {
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }

        @media (max-width: 768px) {
            .chart-box {
                flex: 1 1 90%; /* Single column, nearly full width on small screens */
                max-width: 90%;
            }
            .notification {
                width: calc(100% - 40px);
                left: 20px;
                right: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        畢業門檻狀態
        <div class="header-buttons">
            <button onclick="location.href='course.php'"><i class="fas fa-chalkboard"></i> 輔助選課</button>
            <button onclick="location.href='Downloads.html'"><i class="fas fa-download"></i> 下載手冊</button>
            <button onclick="location.href='login.php'"><i class="fas fa-sign-out-alt"></i> 登出</button>
        </div>
    </div>

    <div class="container">
        <div class="panel info-panel">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="info"><i class="fas fa-user-circle"></i> 個人資訊</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="info-grid">
                    <div><span>姓名：</span><?= htmlspecialchars($user_name) ?></div>
                    <div><span>學號：</span><?= htmlspecialchars($student_id) ?></div>
                    <div><span>系所：</span><?= htmlspecialchars($user_department) ?></div>
                    <div><span>組別：</span><?= htmlspecialchars($user_group) ?></div>
                </div>
            </div>
        </div>

        <div class="panel input-section-panel">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="input-section"><i class="fas fa-plus-circle"></i> 登錄已修課程</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <p style="font-size: 14px; color: #555; margin-bottom: 15px;">請選擇課程類型並輸入您已修過的課號</p>
                <div class="input-group">
                    <select id="course_type">
                        <option value="GE">通識課程 GE</option>
                        <optgroup label="創意與科技學院">
                            <option value="CT">創科院 CT</option>
                            <option value="CA">文資系 CA</option>
                            <option value="AR">建築系 AR</option>
                            <option value="CS">資應系 CS</option>
                            <option value="PM">產媒系 PM</option>
                            <option value="CN">傳播系 CN</option>
                        </optgroup>
                        <optgroup label="佛教學院">
                            <option value="CB">佛教院 CB</option>
                            <option value="BU">佛教系 BU</option>
                        </optgroup>
                        <optgroup label="樂活產業學院">
                            <option value="HS">樂活院 HS</option>
                            <option value="FL">樂活系 FL</option>
                            <option value="VS">蔬食系 VS</option>
                        </optgroup>
                        <optgroup label="管理學院">
                            <option value="MA">管院 MA</option>
                            <option value="MD">管理系 MD</option>
                            <option value="SH">運健系 SH</option>
                            <option value="AE">經濟系 AE</option>
                        </optgroup>
                        <optgroup label="社會科學學院">
                            <option value="SO">社科院 SO</option>
                            <option value="SC">心理系 SC</option>
                            <option value="PA">公事系 PA</option>
                            <option value="SY">社會系 SY</option>
                        </optgroup>
                        <optgroup label="人文學院">
                            <option value="HC">人文院 HC</option>
                            <option value="LC">外文系 LC</option>
                            <option value="LE">中文系 LE</option>
                            <option value="HI">歷史系 HI</option>
                        </optgroup>
                    </select>
                    <input type="text" id="course_code" placeholder="輸入已修過的課號 (例如GE111只需輸入111)">
                    <button onclick="addCourse()"><i class="fas fa-check-circle"></i> 確認新增</button>
                </div>
            </div>
        </div>
    </div>

    <div class="container" style="margin-top: -10px;">
        <div class="panel program-analysis-panel" style="flex-grow: 2; min-width: 600px;">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="analysis"><i class="fas fa-chart-pie"></i> 畢業學程完成度分析</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="chart-container">
                    <div class="chart-box">
                        <h4>學分分佈</h4>
                        <canvas id="creditDistributionChart"></canvas>
                        <p>已修總學分: <?= $total_combined_credits ?> 學分</p>
                    </div>
                    <div class="chart-box">
                        <h4>總學分進度</h4>
                        <canvas id="totalCreditsChart"></canvas>
                        <p>已修: <?= $total_combined_credits ?> / <?= $graduation_credits_required ?> 學分</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="panel course-lists-panel" style="flex: 2; min-width: 600px;">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="course-list"><i class="fas fa-check-circle"></i> 已完成及已選課程清單 (<?= count($user_combined_courses_details) ?>)</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="tabs">
                    <button class="tab-button active" onclick="openTab(event, 'combinedCourses')">
                        <i class="fas fa-book-reader"></i> 所有已修/已選課程
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'missingCourses')">
                        <i class="fas fa-exclamation-circle"></i> 未完成必修課程 (<?= count($all_missing_required_courses_list) ?>)
                    </button>
                </div>

                <div id="combinedCourses" class="tab-content active course-list completed">
                    <ul>
                        <?php if (empty($user_combined_courses_details)): ?>
                            <li>目前沒有已完成或已選課程。</li>
                        <?php else: ?>
                            <?php foreach ($user_combined_courses_details as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                                    <span>學分數：<?= $row['credits'] ?>｜修別：<?= htmlspecialchars($row['course_type']) ?>｜類別：<?= htmlspecialchars($row['category']) ?></span>
                                    <?php if (!empty($row['semester'])): ?>
                                    <br><span>學期：<?= htmlspecialchars($row['semester']) ?>｜狀態：<?= htmlspecialchars($row['status']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div id="missingCourses" class="tab-content course-list missing">
                    <ul>
                        <?php if (empty($all_missing_required_courses_list)): ?>
                            <li>恭喜！您已完成所有必修課程。</li>
                        <?php else: ?>
                            <?php foreach ($all_missing_required_courses_list as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                                    <span>學分數：<?= $row['credits'] ?>｜修別：<?= htmlspecialchars($row['course_type']) ?>｜類別：<?= htmlspecialchars($row['category']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div id="notification-container"></div>

    <script>
        // 從 PHP 獲取數據
        const graduationCredits = <?= $graduation_credits_required ?>;
        const currentCredits = <?= $total_combined_credits ?>; // 總計已完成 + 已選學分

        // 從 PHP 獲取用於學分分佈圖的數據
        const creditDistributionLabels = <?= json_encode($chart_labels, JSON_UNESCAPED_UNICODE) ?>;
        const creditDistributionData = <?= json_encode($chart_data) ?>;
        const creditDistributionColors = <?= json_encode($chart_background_colors) ?>;


        document.addEventListener('DOMContentLoaded', function() {
            // Set initial state for panels on load
            document.querySelectorAll('.panel-header').forEach(header => {
                const content = header.nextElementSibling;
                // Keep '個人資訊' and '登錄已修課程' open by default on larger screens
                // and '畢業學程完成度分析' open.
                // Collapse '課程清單' by default.
                if (header.closest('.course-lists-panel') && window.innerWidth > 992) {
                     header.classList.add('collapsed');
                     content.classList.add('hidden');
                } else if (window.innerWidth <= 992) { // On small screens, collapse all
                    header.classList.add('collapsed');
                    content.classList.add('hidden');
                }
            });

            // 初始化圓餅圖
            initDoughnutCharts();
        });

        // Toggle panel visibility
        function togglePanel(header) {
            const content = header.nextElementSibling;
            header.classList.toggle('collapsed');
            content.classList.toggle('hidden');
        }

        // Open specific tab
        function openTab(evt, tabName) {
            let i, tabcontent, tablinks;

            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].classList.remove('active');
            }

            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].classList.remove('active');
            }

            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.classList.add('active');
        }

        // Function to display notifications
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                notification.addEventListener('transitionend', () => notification.remove());
            }, 3000);
        }

        // Add course function
        function addCourse() {
            const typePrefix = document.getElementById("course_type").value;
            let codeSuffix = document.getElementById("course_code").value.trim();

            if (codeSuffix === '') {
                showNotification('請輸入課程代碼！', 'error');
                return;
            }

            // 組合完整課號，如果不是 GE 開頭的，就直接用輸入的課號，避免重複前綴
            let fullCourseCode;
            if (typePrefix === 'GE' && !codeSuffix.startsWith('GE')) {
                 fullCourseCode = typePrefix + codeSuffix;
            } else if (typePrefix !== 'GE' && !codeSuffix.startsWith(typePrefix)) {
                 fullCourseCode = typePrefix + codeSuffix;
            } else {
                fullCourseCode = codeSuffix; // 如果已經包含了前綴，直接使用
            }

            // 清空輸入欄位
            document.getElementById("course_code").value = '';
            document.getElementById("course_type").value = 'GE'; // 重置為預設值


            fetch("add_course.php", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                // 只傳 course_code，user_id 會從 session 獲取
                body: `course_code=${encodeURIComponent(fullCourseCode)}`
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP 錯誤！狀態碼: ${response.status}, 響應內容: ${text}`);
                    });
                }
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        throw new Error(`非預期的響應格式，預期 JSON 但收到: ${text}`);
                    });
                }
            })
            .then(data => {
                if (data.status === 'success' || data.status === 'warning') { // 處理成功和警告
                    showNotification(data.message, data.status);
                    // 延遲重載頁面以顯示通知
                    setTimeout(() => { location.reload(); }, 1500);
                } else {
                    showNotification("錯誤: " + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                showNotification("新增課程時發生網路錯誤或伺服器問題：" + error.message, 'error');
            });
        }

        // Initialize Doughnut Charts
        function initDoughnutCharts() {
            // 學分分佈圖
            const creditDistributionCtx = document.getElementById('creditDistributionChart').getContext('2d');
            new Chart(creditDistributionCtx, {
                type: 'doughnut',
                data: {
                    labels: creditDistributionLabels, // 使用 PHP 傳過來的動態標籤
                    datasets: [{
                        data: creditDistributionData, // 使用 PHP 傳過來的動態數據
                        backgroundColor: creditDistributionColors, // 使用 PHP 傳過來的動態顏色
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right', // 放置在右側以節省空間
                            labels: {
                                font: {
                                    size: 12 // 調整圖例字體大小
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                    const percentage = (total > 0 ? (value / total * 100) : 0).toFixed(1) + '%';
                                    return `${label}: ${value} 學分 (${percentage})`;
                                }
                            },
                            bodyFont: {
                                size: 14
                            }
                        }
                    }
                }
            });

            // 總學分進度圖
            const totalCtx = document.getElementById('totalCreditsChart').getContext('2d');
            const totalRemainingCredits = Math.max(0, graduationCredits - currentCredits);
            new Chart(totalCtx, {
                type: 'doughnut',
                data: {
                    labels: ['已修學分', '剩餘學分'],
                    datasets: [{
                        data: [currentCredits, totalRemainingCredits],
                        backgroundColor: [
                            'rgba(63, 81, 181, 0.8)', // Blue (Earned)
                            'rgba(255, 159, 64, 0.8)' // Orange (Remaining)
                        ],
                        borderColor: [
                            'rgba(63, 81, 181, 1)',
                            'rgba(255, 159, 64, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                font: {
                                    size: 14
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed;
                                    const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                    const percentage = (total > 0 ? (value / total * 100) : 0).toFixed(1) + '%';
                                    return `${label}: ${value} 學分 (${percentage})`;
                                }
                            },
                            bodyFont: {
                                size: 14
                            }
                        }
                    }
                }
            });
        }

        // HTML 實體化函數，防止 XSS 攻擊
        function htmlspecialchars(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    </script>
</body>
</html>

<?php
$conn->close();
?>
