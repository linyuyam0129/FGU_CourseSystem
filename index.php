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

// --- 步驟 1: 從統一的 'courses' 表中獲取所有課程詳細資訊 ---
// 這是所有可用課程的主列表，以 course_code 為鍵，方便快速查找
$sql_all_courses = "SELECT `course_code`, `course_name`, `credits`, `course_type`, `category` FROM `courses`";
$stmt_all_courses = $conn->prepare($sql_all_courses);
if (!$stmt_all_courses) {
    die("準備所有課程查詢失敗: " . $conn->error);
}
$stmt_all_courses->execute();
$result_all_courses = $stmt_all_courses->get_result();

$courses_master_list = [];
while ($row = $result_all_courses->fetch_assoc()) {
    $courses_master_list[$row['course_code']] = $row;
}
$stmt_all_courses->close();


// --- 步驟 2: 獲取使用者已完成和已選課程 ---
$completed_course_codes = []; // 僅儲存已完成課程的課號，用於快速查找
$selected_course_codes = []; // 僅儲存已選但未完成課程的課號
$user_completed_courses_details = []; // 儲存已完成課程的完整細節，包含學期，用於學分計算和顯示
$total_completed_credits = 0;

$sql_user_course_status = "
    SELECT course_code, semester, 'completed' AS status FROM completed_courses WHERE user_id = ?
    UNION ALL
    SELECT course_code, semester, 'selected' AS status FROM selected_courses WHERE user_id = ?
";
$stmt_user_course_status = $conn->prepare($sql_user_course_status);
if (!$stmt_user_course_status) {
    die("準備使用者課程狀態查詢失敗: " . $conn->error);
}
$stmt_user_course_status->bind_param("ii", $user_id, $user_id);
$stmt_user_course_status->execute();
$user_course_status_result = $stmt_user_course_status->get_result();

while ($row = $user_course_status_result->fetch_assoc()) {
    $course_code = $row['course_code'];
    // 確保課程存在於總課程列表中，才處理其狀態
    if (isset($courses_master_list[$course_code])) {
        $course_detail = $courses_master_list[$course_code];
        if ($row['status'] === 'completed') {
            if (!in_array($course_code, $completed_course_codes)) { // 避免重複處理
                $completed_course_codes[] = $course_code;
                $total_completed_credits += $course_detail['credits'];
                // 合併課程詳細資料和用戶的修課狀態（學期）
                $user_completed_courses_details[] = array_merge($course_detail, ['semester' => $row['semester'], 'status' => 'completed']);
            }
        } elseif ($row['status'] === 'selected') {
            // 只有當課程未完成且未被標記為已選時才加入
            if (!in_array($course_code, $completed_course_codes) && !in_array($course_code, $selected_course_codes)) {
                $selected_course_codes[] = $course_code;
            }
        }
    }
}
$stmt_user_course_status->close();


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

// 設置畢業總學分要求
$graduation_credits_required = 128;


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
// 這些數據應該與您在 SQL 腳本中填充 `courses` 表的 `category` 欄位一致
$general_education_groups_with_min_credits = [
    '中文能力課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '外語能力課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '人文藝術學群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '社會科學課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '自然科學課群' => ['min_courses' => 1, 'min_credits_per_course' => 3],
    '生命教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '生活教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '生涯教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 2],
    '共同教育課群' => ['min_courses' => 1, 'min_credits_per_course' => 0] // 體育等 0 學分課程
];

// 從使用者已完成課程中計算通識學分和已完成課群
foreach ($user_completed_courses_details as $course_detail) {
    if (array_key_exists($course_detail['category'], $general_education_groups_with_min_credits)) {
        $earned_program_credits['通識學程'] += $course_detail['credits'];
        $completed_general_education_groups[$course_detail['category']] = true;
        if (!isset($general_education_group_credits[$course_detail['category']])) {
            $general_education_group_credits[$course_detail['category']] = 0;
        }
        $general_education_group_credits[$course_detail['category']] += $course_detail['credits'];
    }
}


// --- 通識學程檢查訊息 ---
$ge_missing_messages = [];
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
if ($earned_program_credits['通識學程'] < ($program_credit_ranges['通識學程']['min'] ?? 32)) {
    $ge_missing_messages[] = "通識學程總學分不足，目前已修 {$earned_program_credits['通識學程']} 學分，需 " . ($program_credit_ranges['通識學程']['min'] ?? 32) . " 學分。";
}
if (!empty($ge_missing_messages)) {
    $unfulfilled_requirements['通識學程'] = $ge_missing_messages;
}


// --- 系所專屬學程檢查 (核心、專業、跨領域) ---
$department_specific_programs_to_check = [
    '領域核心學程',
    '領域專業學程',
    '院跨領域特色學程'
];

$program_display_courses = []; // 儲存每個學程的詳細課程列表，用於在 HTML 中顯示

foreach ($department_specific_programs_to_check as $program_name) {
    $unfulfilled_requirements[$program_name] = []; // 初始化該學程的未完成訊息
    $current_program_earned_credits = 0;
    $program_required_courses_details = []; // 儲存這個學程在該系所組別下的所有必修課程細節

    // 根據 program_name 獲取 program_id
    $current_program_id = $program_ids_map[$program_name] ?? null;

    if ($current_program_id === null) {
        error_log("錯誤: 在 program_definitions 中找不到學程ID: '{$program_name}'");
        continue; // 跳過此學程的檢查
    }

    // 查詢 program_course_requirements (學程課程要求表)
    // 結合 courses 表獲取課程詳細信息
    // 結合 department_program_mapping 表根據使用者系所組別過濾
    $sql_required_program_courses = "
        SELECT
            c.course_code,
            c.course_name,
            c.credits,
            c.course_type,
            c.category
        FROM
            program_course_requirements pcr
        JOIN
            courses c ON pcr.course_code = c.course_code
        WHERE
            pcr.program_id = ?
            AND pcr.is_mandatory = TRUE
            AND c.course_code IN ( -- 確保這些課程是該系所/組別的實際學程的一部分
                SELECT course_code FROM program_course_requirements pcr_inner
                JOIN department_program_mapping dpm_inner ON pcr_inner.program_id = dpm_inner.program_id
                WHERE dpm_inner.department_name = ? AND dpm_inner.user_group = ? AND pcr_inner.program_id = ?
            );
    ";
    $stmt_required_program_courses = $conn->prepare($sql_required_program_courses);
    if (!$stmt_required_program_courses) {
        error_log("準備 {$program_name} 必修課程查詢失敗: " . $conn->error);
        continue;
    }
    // 注意綁定參數的順序和數量 (program_id, department_name, user_group, program_id)
    $stmt_required_program_courses->bind_param("isii", $current_program_id, $user_department, $user_group, $current_program_id);
    $stmt_required_program_courses->execute();
    $result_required_program_courses = $stmt_required_program_courses->get_result();

    while ($course = $result_required_program_courses->fetch_assoc()) {
        $program_required_courses_details[] = $course; // 將課程加入顯示列表
        if (in_array($course['course_code'], $completed_course_codes)) {
            $current_program_earned_credits += $course['credits'];
        } else {
            $unfulfilled_requirements[$program_name][] = "未修讀：{$course['course_name']} ({$course['course_code']}) - {$course['credits']} 學分";
        }
    }
    $stmt_required_program_courses->close();

    // 儲存該學程的課程列表，供 HTML 渲染時使用
    $program_display_courses[$program_name] = $program_required_courses_details;

    // 檢查學分是否達到該學程的最低要求
    $min_credits_for_program = $program_credit_ranges[$program_name]['min'] ?? 0;
    if ($current_program_earned_credits < $min_credits_for_program) {
        $unfulfilled_requirements[$program_name][] = "學分不足，目前已修 {$current_program_earned_credits} 學分，需 {$min_credits_for_program} 學分。";
    }
    // 更新該學程已獲得的總學分
    $earned_program_credits[$program_name] = $current_program_earned_credits;
}


// --- 總畢業學分檢查 ---
if ($total_completed_credits < $graduation_credits_required) {
    $unfulfilled_requirements['總學分不足'] = ["目前總學分 {$total_completed_credits}，距離畢業所需 {$graduation_credits_required} 學分尚有不足。"];
} else {
    // 如果總學分已達標，確保這個訊息不會顯示
    unset($unfulfilled_requirements['總學分不足']);
}

// --- 獲取所有未完成必修課程 (用於 "未完成必修課程" 標籤頁) ---
// 這會列出在 `courses` 表中被標記為 '必修'，但使用者尚未完成或選修的所有課程。
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
if (!$stmt_all_missing_required) {
    die("準備所有未完成必修課程查詢失敗: " . $conn->error);
}
$stmt_all_missing_required->bind_param("ii", $user_id, $user_id);
$stmt_all_missing_required->execute();
$all_missing_required_result = $stmt_all_missing_required->get_result();

?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>畢業門檻狀態</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
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

        /* 可折疊內容 */
        .panel-content {
            max-height: 1000px; /* 過渡的最大高度 */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
        }
        .panel-content.hidden {
            max-height: 0;
            opacity: 0;
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

        /* 學分顯示與進度條 */
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
        }

        /* ----------------------- 新增的學程檢查區塊樣式 ----------------------- */
        .program-analysis-panel {
            background: white;
            border-radius: 15px;
            padding: 25px 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            margin-bottom: 25px; /* 與其他面板分開 */
            display: flex;
            flex-direction: column;
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
            color: #8BC34A; /* 分析圖標的淺綠色 */
        }

        .program-analysis-panel .status-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: bold;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .program-analysis-panel .status-message.success {
            background-color: #e8f5e9;
            border: 1px solid #a5d6a7;
            color: #2e7d32;
        }
        .program-analysis-panel .status-message.warning {
            background-color: #fff3e0;
            border: 1px solid #ffcc80;
            color: #ef6c00;
        }
        .program-analysis-panel .status-message.danger {
            background-color: #ffebee;
            border: 1px solid #ef9a9a;
            color: #c62828;
        }

        .program-analysis-panel .program-summary {
            margin-bottom: 20px;
        }
        .program-analysis-panel .program-summary h4 {
            font-size: 20px;
            color: #555;
            margin-bottom: 15px;
            border-bottom: 1px dashed #e0e0e0;
            padding-bottom: 8px;
        }
        .program-analysis-panel .program-summary p {
            font-size: 16px;
            margin-bottom: 8px;
        }
        .program-analysis-panel .program-summary p span {
            font-weight: bold;
            color: #3f51b5;
        }

        .program-analysis-panel table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .program-analysis-panel th, .program-analysis-panel td {
            border: 1px solid #e0e0e0;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }
        .program-analysis-panel th {
            background-color: #f7f7f7;
            font-weight: 600;
            color: #444;
        }
        .program-analysis-panel .status-cell {
            font-weight: bold;
        }
        .program-analysis-panel .status-cell.completed { color: #28a745; }
        .program-analysis-panel .status-cell.not-completed { color: #dc3545; }
        .program-analysis-panel .status-cell.selected { color: #007bff; }
        .program-analysis-panel .status-cell.info { color: #6c757d; }

        .program-analysis-panel .missing-list {
            margin-top: 15px;
            list-style: disc;
            padding-left: 20px;
        }
        .program-analysis-panel .missing-list li {
            color: #dc3545;
            margin-bottom: 5px;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        畢業門檻狀態
        <div class="header-buttons">
            <button onclick="location.href='login.php'"><i class="fas fa-sign-out-alt"></i> 登出</button>
            <button onclick="location.href='course.php'"><i class="fas fa-chalkboard"></i> 輔助選課</button>
            <button onclick="location.href='Downloads.html'"><i class="fas fa-download"></i> 下載手冊</button>
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
                <p style="font-size: 14px; color: #555; margin-bottom: 15px;">請選擇課程類型並輸入您已修過的課號 (注意：此為模擬功能，實際後端需自行處理)</p>
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

    <!-- 畢業學程分析區塊 -->
    <div class="container" style="margin-top: -10px;">
        <div class="panel program-analysis-panel" style="flex-grow: 2; min-width: 600px;">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="analysis"><i class="fas fa-tasks"></i> 畢業學程完成度分析</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <?php
                $overall_status_class = 'success';
                $overall_status_text = '恭喜！目前所有畢業學程和總學分要求都已達成或在進度中。';

                $all_unfulfilled = [];
                foreach ($unfulfilled_requirements as $program => $messages) {
                    if (!empty($messages)) {
                        $all_unfulfilled[$program] = $messages;
                    }
                }

                if (!empty($all_unfulfilled)) {
                    $overall_status_class = 'danger'; // 預設為危險狀態
                    $overall_status_text = '以下為尚未達成的學程要求：';
                    // 如果只有總學分不足，可以給予警告狀態而不是危險
                    if (count($all_unfulfilled) === 1 && isset($all_unfulfilled['總學分不足'])) {
                         $overall_status_class = 'warning';
                    }
                }
                ?>
                <div class="status-message <?= $overall_status_class ?>">
                    <i class="fas <?= $overall_status_class === 'success' ? 'fa-award' : ($overall_status_class === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle') ?>"></i>
                    <p><?= $overall_status_text ?></p>
                </div>

                <?php if (!empty($all_unfulfilled)): ?>
                    <ul class="missing-list">
                        <?php foreach ($all_unfulfilled as $program => $messages): ?>
                            <li><strong><?= htmlspecialchars($program) ?>:</strong>
                                <ul style="list-style: circle; margin-left: 20px;">
                                    <?php foreach ($messages as $msg): ?>
                                        <li><?= htmlspecialchars($msg) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <div class="program-summary">
                    <h4>校通識學程 (總學分：<?= $program_credit_ranges['通識學程']['min'] ?? 32 ?>)</h4>
                    <p>已修學分：<span><?= $earned_program_credits['通識學程'] ?></span> / 需 <?= $program_credit_ranges['通識學程']['min'] ?? 32 ?> 學分</p>
                    <table>
                        <thead>
                            <tr>
                                <th>課群</th>
                                <th>學分要求</th>
                                <th>已修學分</th>
                                <th>狀態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($general_education_groups_with_min_credits as $group => $req): ?>
                                <?php
                                $current_ge_credits = $general_education_group_credits[$group] ?? 0;
                                $status_class = 'not-completed';
                                $status_text = '未完成';
                                $group_fulfilled = isset($completed_general_education_groups[$group]) && ($current_ge_credits >= $req['min_credits_per_course'] || $req['min_credits_per_course'] === 0);

                                if ($group_fulfilled) {
                                    $status_class = 'completed';
                                    $status_text = '已完成';
                                } else if (isset($completed_general_education_groups[$group]) && $current_ge_credits < $req['min_credits_per_course']) {
                                     $status_class = 'not-completed';
                                     $status_text = '學分不足';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($group) ?></td>
                                    <td><?= $req['min_credits_per_course'] > 0 ? "至少 " . $req['min_credits_per_course'] . " 學分" : "至少一門課" ?></td>
                                    <td><?= $current_ge_credits ?></td>
                                    <td class="status-cell <?= $status_class ?>"><?= $status_text ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php
                // 動態生成系所專屬學程區塊
                $department_specific_program_order = [
                    '領域核心學程',
                    '領域專業學程',
                    '院跨領域特色學程'
                ];

                foreach ($department_specific_program_order as $program_name):
                    $courses_for_display = $program_display_courses[$program_name] ?? [];
                    $min_credits = $program_credit_ranges[$program_name]['min'] ?? 0;
                    $max_credits = $program_credit_ranges[$program_name]['max'] ?? 'N/A'; // 如果未定義，顯示 N/A
                    $earned_current_program_credits = $earned_program_credits[$program_name];
                ?>
                    <div class="program-summary">
                        <h4><?= htmlspecialchars($program_name) ?> (學分：<?= $min_credits ?>-<?= $max_credits ?>)</h4>
                        <p>已修學分：<span><?= $earned_current_program_credits ?></span> / 需 <?= $min_credits ?> 學分</p>
                        <?php if (!empty($unfulfilled_requirements[$program_name]) && count($unfulfilled_requirements[$program_name]) > 1): ?>
                            <ul class="missing-list">
                                <?php foreach ($unfulfilled_requirements[$program_name] as $msg): ?>
                                    <li><?= htmlspecialchars($msg) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if (!empty($courses_for_display)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>課號</th>
                                    <th>科目名稱</th>
                                    <th>學分</th>
                                    <th>修別</th>
                                    <th>狀態</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($courses_for_display as $course): ?>
                                    <?php
                                    $status_class = 'not-completed';
                                    $status_text = '未修';
                                    if (in_array($course['course_code'], $completed_course_codes)) {
                                        $status_class = 'completed';
                                        $status_text = '已修';
                                    } elseif (in_array($course['course_code'], $selected_course_codes)) {
                                        $status_class = 'selected';
                                        $status_text = '已選';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($course['course_code']) ?></td>
                                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                                        <td><?= $course['credits'] ?></td>
                                        <td><?= htmlspecialchars($course['course_type']) ?></td>
                                        <td class="status-cell <?= $status_class ?>"><?= $status_text ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                            <p style="color: #6c757d; font-style: italic;">目前無此學程的必修課程資料，請確認資料庫配置或學生系所組別是否正確對應。</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>


    <div class="container" style="margin-top: -10px;">
        <div class="panel credit-panel">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="credit"><i class="fas fa-graduation-cap"></i> 總學分數統計</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="credit-display">
                    已修學分數：<span><?= $total_completed_credits ?></span> / <?= $graduation_credits_required ?> 學分數
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="creditProgressBar" style="width: 0%;"></div>
                    </div>
                    <small>畢業門檻需修滿 <?= $graduation_credits_required ?> 學分數。通識教育32學分數，院與系則依各自規定。</small>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="panel course-lists-panel" style="flex: 2; min-width: 600px;">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="course-list"><i class="fas fa-list-alt"></i> 課程清單</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="tabs">
                    <button class="tab-button active" onclick="openTab(event, 'completedCourses')">
                        <i class="fas fa-check-circle"></i> 已完成課程 (<?= count($user_completed_courses_details) ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'missingCourses')">
                        <i class="fas fa-exclamation-circle"></i> 未完成必修課程 (<?= $all_missing_required_result->num_rows ?>)
                    </button>
                </div>

                <div id="completedCourses" class="tab-content active course-list completed">
                    <ul>
                        <?php if (empty($user_completed_courses_details)): ?>
                            <li>目前沒有已完成課程。</li>
                        <?php else: ?>
                            <?php foreach ($user_completed_courses_details as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                                    <span>學分數：<?= $row['credits'] ?>｜修別：<?= htmlspecialchars($row['course_type']) ?>｜類別：<?= htmlspecialchars($row['category']) ?></span>
                                    <?php if (!empty($row['semester'])): ?>
                                    <br><span>完成學期：<?= htmlspecialchars($row['semester']) ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div id="missingCourses" class="tab-content course-list missing">
                    <ul>
                        <?php if ($all_missing_required_result->num_rows === 0): ?>
                            <li>恭喜！您已完成所有必修課程。</li>
                        <?php else: ?>
                            <?php while ($row = $all_missing_required_result->fetch_assoc()): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                                    <span>學分數：<?= $row['credits'] ?>｜類別：<?= htmlspecialchars($row['category']) ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 從 PHP 獲取數據
        const graduationCredits = <?= $graduation_credits_required ?>;
        const currentCredits = <?= $total_completed_credits ?>;

        document.addEventListener('DOMContentLoaded', function() {
            updateProgressBar();
            // 可選: 在小螢幕上預設折疊所有面板
            if (window.innerWidth <= 992) { // 調整面板斷點
                document.querySelectorAll('.panel-header').forEach(header => {
                    const content = header.nextElementSibling;
                    if (content && !content.classList.contains('hidden')) { // 如果內容未隱藏，則折疊
                        header.classList.add('collapsed');
                        content.classList.add('hidden');
                    }
                });
            }
        });

        // 更新進度條視覺效果
        function updateProgressBar() {
            const progressBar = document.getElementById('creditProgressBar');
            let percentage = (currentCredits / graduationCredits) * 100;
            if (percentage > 100) percentage = 100; // 超過 100% 則上限為 100%

            progressBar.style.width = percentage + '%';
            progressBar.textContent = Math.round(percentage) + '%';

            // 根據進度條顏色變化
            if (percentage < 30) {
                progressBar.classList.add('danger');
                progressBar.classList.remove('warning');
            } else if (percentage < 70) {
                progressBar.classList.add('warning');
                progressBar.classList.remove('danger');
            } else {
                progressBar.classList.remove('danger', 'warning');
            }
        }

        // 折疊/展開面板
        function togglePanel(header) {
            const content = header.nextElementSibling; // .panel-content div
            header.classList.toggle('collapsed');
            content.classList.toggle('hidden');
        }

        // 開啟指定標籤頁
        function openTab(evt, tabName) {
            let i, tabcontent, tablinks;

            // 隱藏所有標籤內容
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove('active'); // 移除活躍類別以實現過渡
            }

            // 移除所有標籤按鈕的活躍類別
            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }

            // 顯示當前標籤內容並將其標記為活躍
            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add('active');
            evt.currentTarget.className += " active";
        }

        // 模擬新增課程功能
        function addCourse() {
            const typePrefix = document.getElementById("course_type").value;
            let codeSuffix = document.getElementById("course_code").value.trim();

            if (codeSuffix === '') {
                alert('請輸入課程代碼！');
                return;
            }

            // 組合完整的課號 (例如 'GE' + '111' -> 'GE111')
            const fullCourseCode = typePrefix + codeSuffix;

            // 清空輸入框和重設下拉選單，提升使用者體驗
            document.getElementById("course_code").value = '';
            document.getElementById("course_type").value = 'GE'; // 重設為預設值

            // AJAX 請求到後端 `add_course.php`
            // 注意：此處假設 `add_course.php` 存在且能正確處理資料庫操作
            fetch("add_course.php", {
                method: "POST",
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `course_code=${encodeURIComponent(fullCourseCode)}&user_id=<?= $user_id ?>` // 將 user_id 也傳遞給後端
            })
            .then(response => {
                if (!response.ok) {
                    // 如果 HTTP 狀態碼不是 2xx，則嘗試讀取錯誤訊息
                    return response.text().then(text => {
                        throw new Error(`HTTP 錯誤！狀態碼: ${response.status}, 響應內容: ${text}`);
                    });
                }
                // 檢查響應的 Content-Type 是否為 application/json
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json(); // 解析 JSON
                } else {
                    // 如果不是 JSON，但 HTTP 狀態碼是 OK，可能是 PHP 輸出錯誤或警告
                    return response.text().then(text => {
                        throw new Error(`非預期的響應格式，預期 JSON 但收到: ${text}`);
                    });
                }
            })
            .then(data => {
                // 根據後端回傳的狀態顯示訊息
                if (data.status === 'success') {
                    alert(data.message); // 例如：「課程成功加入已選列表！」
                    location.reload(); // 成功後重新載入頁面以顯示新課程
                } else {
                    alert("錯誤: " + data.message); // 顯示錯誤訊息
                }
            })
            .catch(error => {
                // 捕獲網路錯誤或 JSON 解析錯誤
                console.error('Fetch error:', error);
                alert("新增課程時發生網路錯誤或伺服器問題：" + error.message);
            });
        }
    </script>
</body>
</html>

<?php
// 關閉資料庫連線和剩餘的預處理語句
// 確保 $all_missing_required_result 已經被使用過
if (isset($all_missing_required_result) && $all_missing_required_result instanceof mysqli_result) {
    $all_missing_required_result->close();
}
// 關閉資料庫連線
$conn->close();
?>
