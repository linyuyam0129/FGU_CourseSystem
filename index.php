<?php
session_start();
require 'db.php'; // Assuming db.php handles database connection

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$sql_user = "SELECT name, student_id, department, user_group FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
if (!$stmt_user) {
    die("準備使用者資訊查詢失敗: " . $conn->error);
}
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();
$stmt_user->close(); // 關閉預處理語句

$_SESSION['name'] = $user['name'];
$_SESSION['student_id'] = $user['student_id'];
$_SESSION['department'] = $user['department'];
$_SESSION['user_group'] = $user['user_group'];

// 修正後的 SQL 語句：使用 UNION ALL 合併 selected_courses 和 completed_courses，
// 並為兩者都 JOIN course_list 以獲取 科目名稱 和 學分數。
$sql_courses = "
    SELECT
        sc.course_code,
        cl.科目名稱 AS course_name,
        cl.學分數 AS credits,
        sc.semester,
        'selected' AS source_table -- 標識來源表
    FROM selected_courses sc
    JOIN course_list cl ON CONVERT(sc.course_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(cl.課程代碼 USING utf8mb4) COLLATE utf8mb4_unicode_ci
    WHERE sc.user_id = ?

    UNION ALL

    SELECT
        cc.course_code,
        cl.科目名稱 AS course_name,
        cl.學分數 AS credits,
        cc.semester,
        'completed' AS source_table -- 標識來源表
    FROM completed_courses cc
    JOIN course_list cl ON CONVERT(cc.course_code USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(cl.課程代碼 USING utf8mb4) COLLATE utf8mb4_unicode_ci
    WHERE cc.user_id = ?
    ORDER BY semester DESC, course_code ASC -- 可以根據需求進行排序
";

$stmt_courses = $conn->prepare($sql_courses);
if (!$stmt_courses) {
    die("準備課程查詢失敗: " . $conn->error);
}
// 由於有兩個 ? 參數，需要綁定兩次 user_id
$stmt_courses->bind_param("ii", $user_id, $user_id);
$stmt_courses->execute();
$courses_result = $stmt_courses->get_result();

$total_credits = 0;
$completed_courses = []; // 這個陣列將包含所有來自 selected_courses 和 completed_courses 的課程
$added_course_codes = []; // 用於追蹤已添加的課程代碼，避免重複顯示

while ($row = $courses_result->fetch_assoc()) {
    // 為了避免重複顯示（如果同一個課程同時存在於兩張表），我們進行去重
    // 這裡的邏輯是，如果課程代碼已經被添加過，就不再重複添加
    if (!in_array($row['course_code'], $added_course_codes)) {
        $completed_courses[] = $row;
        $total_credits += $row['credits'];
        $added_course_codes[] = $row['course_code']; // 記錄已添加的課程代碼
    }
}
$stmt_courses->close(); // 關閉預處理語句

// 未完成必修課程的查詢，需要排除已在 selected_courses 和 completed_courses 中的所有課程
$sql_missing = "SELECT * FROM course_list
WHERE 修別 = '必' AND CONVERT(`課程代碼` USING utf8mb4) COLLATE utf8mb4_unicode_ci NOT IN (
    SELECT CONVERT(course_code USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM selected_courses WHERE user_id = ?
    UNION
    SELECT CONVERT(course_code USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM completed_courses WHERE user_id = ?
)";
$stmt_missing = $conn->prepare($sql_missing);
if (!$stmt_missing) {
    die("準備未完成課程查詢失敗: " . $conn->error);
}
$stmt_missing->bind_param("ii", $user_id, $user_id);
$stmt_missing->execute();
$missing_result = $stmt_missing->get_result();
// 不需要在這裡關閉 $stmt_missing，因為下面的 HTML 仍會使用 $missing_result

// Graduation requirement (example, can be dynamic from DB)
$graduation_credits_required = 128;
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
        /* --- Overall Layout & Base --- */
        body {
            font-family: 'Noto Sans TC', 'Segoe UI', sans-serif;
            background-color: #F0F2F5; /* Lighter background for more contrast */
            color: #343a40;
            margin: 0;
            line-height: 1.6;
            overflow-x: hidden; /* Prevent horizontal scroll on small devices */
        }

        .header {
            background-color: #3f51b5; /* Deeper blue for header */
            color: white;
            padding: 15px 30px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* 增加陰影，提升層次感 */
            font-weight: 700; /* Bolder font weight */
            position: relative;
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
            border-radius: 25px; /* More rounded */
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
            transform: translateY(-2px); /* Slight lift on hover */
        }
        .header-buttons button[onclick*="login.php"] {
            background-color: #f44336; /* Warning red */
            color: white;
            border-color: #f44336;
        }
        .header-buttons button[onclick*="login.php"]:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }

        .container {
            display: flex;
            gap: 25px; /* Consistent gap */
            padding: 25px;
            flex-wrap: wrap;
            justify-content: center; /* Center panels when they wrap */
        }

        .panel {
            flex: 1;
            background: white;
            border-radius: 15px; /* Even more rounded */
            padding: 25px 30px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            min-width: 400px; /* Slightly larger min-width for content */
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .panel:hover {
            transform: translateY(-3px); /* Subtle lift on panel hover */
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            cursor: pointer; /* Indicate collapsibility */
        }
        .panel-header.collapsed .toggle-icon::before {
            content: "\f0d7"; /* Font Awesome caret-down */
        }
        .panel-header .toggle-icon::before {
            content: "\f0d8"; /* Font Awesome caret-up */
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            margin-left: 10px;
            color: #777;
        }

        h3 {
            color: #3f51b5; /* Matches header blue */
            font-size: 24px;
            margin: 0;
            display: flex;
            align-items: center;
        }
        h3 i {
            margin-right: 10px;
            font-size: 22px;
            color: #4CAF50; /* Green for section icons */
        }
        h3.info i { color: #2196f3; } /* Blue for info */
        h3.input-section i { color: #FF9800; } /* Orange for input */
        h3.credit i { color: #673ab7; } /* Purple for credit */
        h3.course-list i { color: #009688; } /* Teal for course lists */

        /* Collapsible Content */
        .panel-content {
            max-height: 1000px; /* Max height for transition */
            overflow: hidden;
            transition: max-height 0.5s ease-out, opacity 0.5s ease-out;
            opacity: 1;
        }
        .panel-content.hidden {
            max-height: 0;
            opacity: 0;
        }

        /* Info section styling */
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

        /* Input section styling */
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
            background-color: #4CAF50; /* Success green */
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

        /* Credit display and progress bar */
        .credit-display {
            background-color: #e8f5e9; /* Light green background */
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
            color: #3f51b5; /* Highlight with primary blue */
            font-size: 28px;
            display: block; /* New line for emphasis */
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
            background-color: #4CAF50; /* Green fill */
            width: 0%; /* Will be set by JS */
            border-radius: 5px;
            text-align: center;
            color: white;
            line-height: 15px;
            font-size: 10px;
            transition: width 0.8s ease-out; /* Smooth transition */
        }
        .progress-bar-fill.warning {
            background-color: #FFC107; /* Yellow for warning */
        }
        .progress-bar-fill.danger {
            background-color: #F44336; /* Red for low progress */
        }


        /* Tabbed course lists */
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
            max-height: 400px; /* Fixed height for scrollable list */
            overflow-y: auto; /* Enable scrolling */
            padding-right: 10px; /* Space for scrollbar */
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
            border-left: 5px solid #3f51b5; /* Primary color highlight */
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
            animation: highlightFade 2s forwards; /* Animation for new courses */
        }
        @keyframes highlightFade {
            0% { background-color: #dcedc8; } /* Light green */
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
            border-left-color: #EF5350; /* Red for missing courses */
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .container {
                flex-direction: column;
                align-items: center; /* Center single column panels */
                padding: 20px;
            }
            .panel {
                min-width: unset; /* Remove min-width for smaller screens */
                width: 100%; /* Take full width */
                max-width: 550px; /* Optional: limit max width for better readability */
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
                flex: 1 1 auto; /* Allow buttons to wrap */
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .input-group select,
            .input-group input[type="text"] {
                flex: 1 1 100%;
            }
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
                    <div><span>姓名：</span><?= htmlspecialchars($_SESSION['name']) ?></div>
                    <div><span>學號：</span><?= htmlspecialchars($_SESSION['student_id']) ?></div>
                    <div><span>系所：</span><?= htmlspecialchars($_SESSION['department']) ?></div>
                    <div><span>組別：</span><?= htmlspecialchars($_SESSION['user_group']) ?></div>
                </div>
            </div>
        </div>

        <div class="panel input-section-panel">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="input-section"><i class="fas fa-plus-circle"></i> 登錄已修課程</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <p style="font-size: 14px; color: #555; margin-bottom: 15px;">請選擇課程類型並輸入您選修過的課號</p>
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
        <div class="panel credit-panel">
            <div class="panel-header" onclick="togglePanel(this)">
                <h3 class="credit"><i class="fas fa-graduation-cap"></i> 學分數統計</h3>
                <span class="toggle-icon"></span>
            </div>
            <div class="panel-content">
                <div class="credit-display">
                    已修學分數：<span><?= $total_credits ?></span> / <?= $graduation_credits_required ?> 學分數
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
                        <i class="fas fa-check-circle"></i> 已完成課程 (<?= count($completed_courses) ?>)
                    </button>
                    <button class="tab-button" onclick="openTab(event, 'missingCourses')">
                        <i class="fas fa-exclamation-circle"></i> 未完成必修課程 (<?= $missing_result->num_rows ?>)
                    </button>
                </div>

                <div id="completedCourses" class="tab-content active course-list completed">
                    <ul>
                        <?php if (empty($completed_courses)): ?>
                            <li>目前沒有已完成課程。</li>
                        <?php else: ?>
                            <?php foreach ($completed_courses as $row): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                                    <span>學分數：<?= $row['credits'] ?>｜完成學期：<?= htmlspecialchars($row['semester']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div id="missingCourses" class="tab-content course-list missing">
                    <ul>
                        <?php if ($missing_result->num_rows === 0): ?>
                            <li>恭喜！您已完成所有必修課程。</li>
                        <?php else: ?>
                            <?php while ($row = $missing_result->fetch_assoc()): ?>
                                <li>
                                    <strong><?= htmlspecialchars($row['科目名稱']) ?></strong>（<?= htmlspecialchars($row['課程代碼']) ?>）<br>
                                    <span>學分數：<?= $row['學分數'] ?></span>
                                </li>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <script>
        const graduationCredits = <?= $graduation_credits_required ?>;
        const currentCredits = <?= $total_credits ?>;

        document.addEventListener('DOMContentLoaded', function() {
            updateProgressBar();
            // Optional: Make all panels collapsible by default on small screens
            if (window.innerWidth <= 768) {
                document.querySelectorAll('.panel-header').forEach(header => {
                    const content = header.nextElementSibling;
                    if (content && !content.classList.contains('hidden')) { // Collapse if not already hidden
                        header.classList.add('collapsed');
                        content.classList.add('hidden');
                    }
                });
            }
        });

        function updateProgressBar() {
            const progressBar = document.getElementById('creditProgressBar');
            let percentage = (currentCredits / graduationCredits) * 100;
            if (percentage > 100) percentage = 100; // Cap at 100%

            progressBar.style.width = percentage + '%';
            progressBar.textContent = Math.round(percentage) + '%';

            // Change color based on progress
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

        function togglePanel(header) {
            const content = header.nextElementSibling; // The .panel-content div
            header.classList.toggle('collapsed');
            content.classList.toggle('hidden');
        }

        function openTab(evt, tabName) {
            let i, tabcontent, tablinks;

            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove('active'); // Remove active class for transitions
            }

            tablinks = document.getElementsByClassName("tab-button");
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(" active", "");
            }

            document.getElementById(tabName).style.display = "block";
            document.getElementById(tabName).classList.add('active'); // Add active class
            evt.currentTarget.className += " active";
        }

        function addCourse() {
            const code = document.getElementById("course_code").value.trim(); // 移除前後空白
            const type = document.getElementById("course_type").value;

            // 輸入驗證
            if (code === '') {
                alert('請輸入課程代碼！');
                return;
            }

            // 清空輸入框和重設下拉選單，提升使用者體驗
            document.getElementById("course_code").value = '';
            document.getElementById("course_type").value = 'GE'; // 重設為預設值

            fetch("add_course.php", {
                method: "POST",
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `course_code=${encodeURIComponent(code)}&course_type=${encodeURIComponent(type)}`
            })
            .then(response => {
                // 檢查 HTTP 響應是否成功 (例如 200 OK)
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
$stmt_missing->close();
$conn->close();
?>