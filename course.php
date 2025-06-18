<?php
session_start();
require 'db.php'; // 確保 db.php 檔案存在且資料庫連線正確

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

// 從 'course_list' 表中獲取所有課程詳細資訊，用於前端顯示和驗證
$all_courses = [];
$sql_all_courses = "SELECT `課程代碼`, `科目名稱`, `學分數` AS credits, `時間`, `教室`, `教師` FROM `course_list`";
$stmt_all_courses = $conn->prepare($sql_all_courses);
if ($stmt_all_courses) {
    $stmt_all_courses->execute();
    $result_all_courses = $stmt_all_courses->get_result();
    while ($row = $result_all_courses->fetch_assoc()) {
        // 使用 '課程代碼' 作為 JavaScript 中 allCoursesData 的鍵
        $all_courses[$row['課程代碼']] = $row;
    }
    $stmt_all_courses->close();
} else {
    error_log("準備所有課程查詢失敗: " . $conn->error);
}

// 獲取使用者已選課程 (此處僅為初始化 PHP 變數，實際列表由 JS 載入)
$selected_courses_details = [];
// 這裡不需要再次查詢 selected_courses_details，因為頁面載入後會由 JS 的 loadSelectedCoursesFromDatabase() 填充
// 但為了 PHP 程式碼的完整性，保留此變數定義。

?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>輔助選課系統</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>

        body {
            font-family: 'Noto Sans TC', 'Segoe UI', sans-serif; /* 使用思源黑體或Segoe UI，確保中文顯示 */
            background-color: #F8F9FA; /* 柔和的淺色背景 */
            color: #343a40; /* 基礎文字顏色 */
            margin: 0;
            line-height: 1.6; /* 增加行高，提升閱讀舒適度 */
        }

        .header {
            background-color: #26a69a; /* 更清新的主色 */
            color: white;
            padding: 15px 30px; /* 調整內邊距 */
            text-align: center;
            font-size: 24px; /* 標題字體大小 */
            font-weight: bold;
            position: relative;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); /* 增加陰影，提升層次感 */
            font-weight: 700; /* Bolder font weight */
            position: relative;
            display: flex; /* 使用 flexbox 讓內容垂直居中 */
            align-items: center;
            justify-content: space-between; /* 標題和按鈕兩端對齊 */
        }

        .header-buttons {
            display: flex;
            gap: 15px; /* 按鈕間距 */
        }

        .header-buttons button {
            background-color: #ffffff;
            color: #26a69a; /* 與主色匹配 */
            border: 1px solid #26a69a;
            padding: 9px 18px; /* 增加按鈕大小 */
            border-radius: 25px; /* 更圓潤的按鈕 */
            cursor: pointer;
            font-weight: 600; /* 加粗按鈕文字 */
            transition: all 0.3s ease; /* 更平滑的過渡效果 */
            font-size: 15px;
            white-space: nowrap; /* 防止按鈕文字換行 */
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .header-buttons button:hover {
            background-color: #26a69a;
            color: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2); /* 懸停時增加陰影 */
            transform: translateY(-2px); /* Slight lift on hover */
        }
        /* 登出按鈕特殊樣式 */
        .header-buttons button[onclick*="login.php"] { /* 根據 onclick 屬性選擇登出按鈕 */
            background-color: #ef5350; /* 警示紅 */
            color: white;
            border-color: #ef5350;
        }
        .header-buttons button[onclick*="login.php"]:hover {
            background-color: #d32f2f;
            border-color: #d32f2f;
        }

        .container {
            display: flex;
            gap: 30px; /* 增加兩欄間距 */
            padding: 30px;
            flex-wrap: wrap; /* 允許換行以適應小螢幕 */
        }

        .panel {
            flex: 1;
            background: white;
            border-radius: 12px; /* 更圓潤的邊角 */
            padding: 25px; /* 增加內邊距 */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08); /* 更明顯的陰影 */
            min-width: 380px; /* 調整最小寬度以適應內容 */
            display: flex; /* 使用 flexbox 垂直排布內容 */
            flex-direction: column;
        }

        h3 {
            color: #26a69a; /* 標題顏色與主色匹配 */
            font-size: 22px;
            margin-top: 0;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        h3::before { /* 小圖示 */
            content: '🔎'; /* 或其他 emoji */
            margin-right: 8px;
            font-size: 20px;
        }
        .panel:nth-child(2) h3::before { /* 第二個面板（課表）的圖示 */
            content: '📜';
        }

        /* --- 搜尋與篩選區塊 --- */
        .search-controls { /* 給篩選區塊加一個 class */
            display: flex;
            flex-wrap: wrap; /* 允許換行 */
            gap: 15px; /* 篩選器間距 */
            margin-bottom: 20px;
        }

        .search-controls input[type="text"],
        .search-controls select {
            flex: 1 1 calc(50% - 15px); /* 兩欄佈局，小於一定寬度會換行 */
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 15px;
            background-color: #f8f9fa; /* 輕微的背景色 */
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .search-controls input[type="text"]:focus,
        .search-controls select:focus {
            border-color: #26a69a;
            box-shadow: 0 0 0 3px rgba(38, 166, 154, 0.25); /* 聚焦時的光暈效果 */
            outline: none;
        }
        .search-controls input[type="text"] { /* 關鍵字輸入框占滿一行 */
            flex: 1 1 100%;
        }

        /* --- 搜尋結果 --- */
        #search-results {
            max-height: 600px; /* 固定高度，增加滾動條 */
            overflow-y: auto;
            border: 1px solid #e0e0e0; /* 邊框 */
            border-radius: 8px;
            flex-grow: 1; /* 佔滿剩餘空間 */
        }

        #search-results table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        #search-results th, #search-results td {
            padding: 10px 12px;
            text-align: left; /* 左對齊更符合閱讀習慣 */
            border-bottom: 1px solid #e0e0e0;
        }
        #search-results th {
            background-color: #e0f2f7; /* 淺藍色背景 */
            font-weight: bold;
            color: #495057;
            position: sticky; /* 表頭固定 */
            top: 0;
            z-index: 1;
        }
        #search-results tr:hover {
            background-color: #f0f8ff; /* 懸停效果 */
            cursor: grab; /* 鼠標變為抓手，提示可拖曳 */
        }
        #search-results tr.dragging { /* 拖曳時的效果 */
            opacity: 0.7;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* --- 課表表格 --- */
        .timetable {
            border: 1px solid #ccc;
            border-radius: 8px; /* 課表整體圓角 */
            overflow: hidden; /* 確保內容不超出圓角 */
            background-color: #fff;
        }
        .timetable th, .timetable td {
            border: 1px solid #e0e0e0; /* 細膩的邊框 */
            padding: 4px;
            font-size: 13px; /* 課表內文字小一點 */
            vertical-align: top; /* 內容置頂 */
        }
        .timetable td:first-child {
            background-color: #e0f7fa; /* 節次欄位背景色 */
            font-weight: normal;
            width: 50px;
            min-width: 40px;
            padding: 4px;
            text-align: center; /* 節次欄位文字置中 */
            color: #212121;
        }
        .timetable th {
            background-color: #b2dfdb; /* 表頭背景色 */
            color: #212121;
        }

        /* 課表課程顯示樣式 */
        .highlight {
            background-color: #e0f2f7; /* 淺藍綠色 */
            color: #004d40;
            font-weight: bold;
            cursor: pointer;
            position: relative;
            transition: background-color 0.2s ease;
        }
        .highlight:hover {
            background-color: #cce7eb; /* 懸停變深 */
        }
        .conflict {
            background-color: #ffcdd2 !important; /* 衝突課程顯眼警示紅 */
            color: #c62828 !important;
            font-weight: bold;
        }

        /* --- 無固定時段課程面板 --- */
        .nofixed-panel {
            margin-top: 25px; /* 增加與課表的間距 */
            background: #ffffff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05); /* 輕微陰影 */
            border: 1px dashed #cfd8dc; /* 虛線邊框，區別於固定課表 */
        }
        .nofixed-panel h4 {
            color: #546e7a; /* 標題顏色 */
            font-size: 18px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        #nofixed-list {
            display: flex;
            flex-wrap: wrap; /* 課程卡片可以換行 */
            gap: 10px; /* 卡片間距 */
        }
        .nofixed-course {
            background: #e8f5e9; /* 淺綠色背景 */
            border: 1px solid #a5d6a7; /* 邊框 */
            padding: 10px 15px;
            border-radius: 8px;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
            align-items: center;
            min-width: 200px; /* 確保卡片有最小寬度 */
            flex-grow: 1; /* 允許卡片佔用空間 */
        }
        .nofixed-course span {
            color: #388e3c; /* 文字顏色 */
        }
        .nofixed-course button {
            background: #ef9a9a; /* 移除按鈕背景色 */
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
            font-size: 13px;
            transition: background-color 0.2s ease;
        }
        .nofixed-course button:hover {
            background: #e57373;
        }

        /* --- 總學分數顯示 --- */
        #credit-total {
            margin-top: 20px;
            font-size: 18px; /* 放大字體 */
            font-weight: bold;
            text-align: right;
            color: #26a69a; /* 與主色匹配 */
        }

        /* --- 響應式調整 --- */
        @media (max-width: 768px) {
            .container {
                flex-direction: column; /* 小螢幕時改為單欄佈局 */
                padding: 15px;
            }
            .panel {
                min-width: auto; /* 移除最小寬度限制 */
            }
            .search-controls input[type="text"],
            .search-controls select {
                flex: 1 1 100%; /* 小螢幕時全部占滿一行 */
            }
            .header {
                flex-direction: column;
                align-items: flex-start;
                padding: 15px;
            }
            .header-buttons {
                margin-top: 10px;
                flex-wrap: wrap;
                gap: 10px;
            }
            .header-buttons button {
                margin-left: 0;
            }
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
        輔助選課系統
        <div class="header-buttons">
            <button onclick="location.href='index.php'"><i class="fas fa-chalkboard"></i> 畢業門檻狀態</button>
            <button onclick="location.href='Downloads.html'"><i class="fas fa-download"></i> 下載手冊</button>
            <button onclick="location.href='login.php'"><i class="fas fa-sign-out-alt"></i> 登出</button>
        </div>
    </div>

    <div class="container">
        <div class="panel">
            <h3> 課程搜尋</h3>
            <p style="font-size: 14px; color: #555; margin-bottom: 15px; background-color: #e8f5e9; padding: 10px; border-radius: 5px;">
          💡提示：將課程從下方課程列表拖曳至右方課表即可加入。點擊我的課表中的課程即可移除。
    </p>
            <div class="search-controls">
                <input type="text" id="search-input" placeholder="輸入科目名稱 / 教師 / 代碼">
                <select id="filter-type">
                    <option value="">全部修別</option>
                    <option value="必修">必修</option>
                    <option value="選修">選修</option>
                </select>
                <select id="filter-general">
                    <option value="">全部通識課群</option>
                    <optgroup label="語文能力課群">
                        <option value="中文">中文能力課群</option>
                        <option value="外語">外語能力課群</option>
                    </optgroup>
                    <optgroup label="博雅教育課程">
                        <option value="共同">共同教育課群</option>
                        <option value="體育">體育運動課群</option>
                        <option value="人文">人文藝術課群</option>
                        <option value="社會">社會科學課群</option>
                        <option value="自然">自然科學課群</option>
                    </optgroup>
                    <optgroup label="現代書院實踐課程">
                        <option value="生命">生命教育課群</option>
                        <option value="生活">生活教育課群</option>
                        <option value="生涯">生涯教育課群</option>
                    </optgroup>
                </select>
                <select id="filter-day">
                    <option value="">全部星期</option>
                    <option value="一">星期一</option>
                    <option value="二">星期二</option>
                    <option value="三">星期三</option>
                    <option value="四">星期四</option>
                    <option value="五">星期五</option>
                    <option value="無固定">無固定時段授課</option>
                </select>
                <select id="filter-dept">
                <option value="">全部學院 / 系所</option> <optgroup label="創意與科技學院">
                    <option value="CT">創意與科技學院 CT</option> 
                    <option value="CA">文資系 CA</option>
                    <option value="AR">建築系 AR</option>
                    <option value="CS">資應系 CS</option>
                    <option value="PM">產媒系 PM</option>
                    <option value="CN">傳播系 CN</option>
                </optgroup>
                <optgroup label="佛教學院">
                    <option value="CB">佛教學院 CB</option>
                    <option value="BU">佛教系 BU</option>
                </optgroup>
                <optgroup label="樂活產業學院">
                    <option value="HS">樂活學院 HS</option>
                    <option value="FL">樂活系 FL</option>
                    <option value="VS">蔬食系 VS</option>
                  </optgroup>
                <optgroup label="管理學院">
                    <option value="MA">管理學院 MA</option>
                    <option value="MD">管理系 MD</option>
                    <option value="SH">運健系 SH</option>
                    <option value="AE">應用經濟學系</option>
                </optgroup>
                <optgroup label="社會科學學院">
                    <option value="SO">社會科學學院 SO</option>
                    <option value="SC">心理系 SC</option>
                    <option value="PA">公共事務學系</option>
                    <option value="SY">社會系 SY</option>
                </optgroup>
                <optgroup label="人文學院">
                    <option value="HC">人文學院 HC</option>
                    <option value="LC">外文系 LC</option>
                    <option value="LE">中國文學系</option>
                    <option value="HI">歷史系 HI</option>
                </optgroup>
</select>
            </div>
            <div id="search-results"></div>
        </div>

        <div class="panel">
            <h3>我的課表</h3>
            <table class="timetable">
                <thead>
                    <tr><th>節次</th><th>一</th><th>二</th><th>三</th><th>四</th><th>五</th></tr>
                </thead>
                <tbody id="timetable-body"></tbody>
            </table>

            <div class="nofixed-panel">
                <h4>📦 無固定時段課程</h4>
                <div id="nofixed-list"></div>
            </div>

            <p id="credit-total">已選學分數：0 學分</p>
        </div>
    </div>

    <script>
        const timeSlots = [
            "08:10~09:00", "09:10-10:00", "10:20-11:10", "11:20-12:10",
            "13:10-14:00", "14:10-15:00", "15:20-16:10", "16:20-17:10",
            "17:20-18:10", /* 您可以根據實際需要添加更多節次，如 "18:20-19:10", "19:20-20:10", "20:20-21:10" */
        ];
        const days = ["一", "二", "三", "四", "五"];
        const tbody = document.getElementById("timetable-body");
        const nofixedList = document.getElementById("nofixed-list");
        let selectedCoursesOnTimetable = {}; // 儲存已選課程的「科目名稱: { credit, code }」對，僅限於課表顯示使用

        // 顯示通知訊息
        function showNotification(message, type = 'success') {
            const container = document.getElementById('notification-container');
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            container.appendChild(notification);

            // 移除 notification after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateY(-20px)';
                notification.addEventListener('transitionend', () => notification.remove());
            }, 3000);
        }

        // HTML 實體化函數，防止 XSS 攻擊
        function htmlspecialchars(str) {
            const div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }

        // 建立課表表格
        function initializeTimetableGrid() {
            tbody.innerHTML = ''; // 清空現有課表
            timeSlots.forEach((t, i) => {
                const row = document.createElement("tr");
                row.innerHTML = `<td>第${i + 1}節<br>${t}</td>`;
                days.forEach((day) => {
                    const cell = document.createElement("td");
                    cell.id = `cell-${day}-${i + 1}`;
                    cell.ondrop = drop;
                    cell.ondragover = allowDrop;
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            });
        }
        
        // 綁定搜尋欄位事件 (input 實時搜尋, change 當選單改變時搜尋)
        ["search-input", "filter-type", "filter-day", "filter-dept", "filter-general"].forEach((id) => {
            const element = document.getElementById(id);
            if (element) { // 確保元素存在
                element.addEventListener("input", triggerSearch);
                element.addEventListener("change", triggerSearch);
            }
        });

        // 搜尋課程並顯示結果
        function triggerSearch() {
            const keyword = document.getElementById("search-input").value.trim();
            const type = document.getElementById("filter-type").value;
            const day = document.getElementById("filter-day").value;
            const dept = document.getElementById("filter-dept").value;
            const general = document.getElementById("filter-general").value; // 取得通識類別

            // 構建查詢字串
            const queryString = new URLSearchParams({
                keyword: keyword,
                type: type,
                day: day,
                dept: dept,
                general: general // 使用 general 對應後端 $general 參數
            }).toString();

            fetch(`search_course.php?${queryString}`)
                .then(res => {
                    if (!res.ok) {
                        // 處理 HTTP 錯誤 (例如 404 Not Found, 500 Internal Server Error)
                        return res.text().then(text => { throw new Error(`HTTP error! status: ${res.status}, body: ${text}`); });
                    }
                    return res.json();
                })
                .then(displayResults)
                .catch(error => {
                    console.error("❌ 搜尋課程時發生錯誤:", error);
                    document.getElementById("search-results").innerHTML = `<p style="color: red;">載入搜尋結果失敗：${error.message}</p>`;
                });
        }

        // 顯示搜尋結果
        function displayResults(courses) {
            const resultDiv = document.getElementById("search-results");
            resultDiv.innerHTML = ""; // 清空舊結果

            if (courses.length === 0) {
                resultDiv.innerHTML = "<p style='text-align: center; color: #6c757d; padding: 20px;'>查無結果</p>";
                return;
            }

            const table = document.createElement("table");
            table.innerHTML = `<tr><th>科目名稱</th><th>時間</th><th>教師</th><th>修別</th><th>學分數</th><th>教室</th><th>通識課群</th></tr>`;
            courses.forEach(c => {
                const tr = document.createElement("tr");
                tr.draggable = true; // 設定為可拖曳
                tr.dataset.name = c.科目名稱;
                tr.dataset.time = c.時間;
                tr.dataset.credit = c.學分數;
                tr.dataset.teacher = c.教師;
                tr.dataset.type = c.修別;
                tr.dataset.room = c.教室;
                tr.dataset.code = c.課程代碼; // 儲存課程代碼，用於選課和退選
                tr.ondragstart = drag; // 綁定拖曳開始事件

                // 根據課程代碼顯示通識課群
                const genEdGroup = c.通識課群 ? c.通識課群 : '—'; // 如果沒有通識課群則顯示 —

                tr.innerHTML = `
                    <td>${c.科目名稱}</td>
                    <td>${c.時間}</td>
                    <td>${c.教師}</td>
                    <td>${c.修別}</td>
                    <td>${c.學分數}</td>
                    <td>${c.教室}</td>
                    <td>${genEdGroup}</td>`;
                table.appendChild(tr);
            });
            resultDiv.appendChild(table);
        }

        // 拖拉功能：允許放置
        function allowDrop(ev) {
            ev.preventDefault(); // 阻止默認行為，允許放置
        }

        // 拖拉功能：拖曳開始
        function drag(ev) {
            // 在拖曳開始時，將所有課程資料傳遞
            ev.dataTransfer.setData(
                "text/plain",
                JSON.stringify({
                    name: ev.target.dataset.name,
                    time: ev.target.dataset.time,
                    credit: ev.target.dataset.credit,
                    teacher: ev.target.dataset.teacher,
                    type: ev.target.dataset.type,
                    room: ev.target.dataset.room,
                    code: ev.target.dataset.code // 傳遞課程代碼
                })
            );
            ev.target.classList.add('dragging'); // 添加拖曳中的樣式
        }

        // 拖曳結束時移除樣式
        document.addEventListener('dragend', (ev) => {
            ev.target.classList.remove('dragging');
        });


        // 放入課表
        function drop(ev) {
            ev.preventDefault(); // 阻止默認行為

            const courseData = JSON.parse(ev.dataTransfer.getData("text/plain"));
            const { name, time, credit, code } = courseData; // 取得課程代碼

            // 如果科目名稱為空，通常不應發生，但做個防範
            if (!name) {
                console.warn("嘗試拖曳無科目名稱的項目");
                return;
            }

            // 檢查課程是否已經選過（在前端 selectedCoursesOnTimetable 中）
            if (selectedCoursesOnTimetable.hasOwnProperty(name)) {
                showNotification(`課程「${name}」已在您的課表中或無固定時段清單中。`, 'warning');
                return;
            }

            // 處理「無固定時段授課」的課程
            if (time && time.includes("無固定時段")) {
                addNoFixedCourseAndSaveToDatabase(courseData); // 將整個 courseData 傳入，並觸發儲存
                return;
            }

            // 以下處理固定時段課程
            let hasConflict = false;
            let conflictDetails = [];
            const slots = time.split("、"); // 課程時間可能有多個時段

            // 預檢查所有時段，判斷是否有衝堂
            slots.forEach((slot) => {
                const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
                if (!match) {
                    console.warn(`無效的時間格式: ${slot}`);
                    return;
                }
                const day = match[1];
                const periods = match[2].split(",").map(Number); // 將節次轉換為數字陣列

                periods.forEach((p) => {
                    const cell = document.getElementById(`cell-${day}-${p}`);
                    if (cell && cell.textContent.trim() !== "") { // 檢查格子是否已經有課程
                        hasConflict = true;
                        conflictDetails.push(`星期${day} 第${p}節：${cell.textContent}`);
                    }
                });
            });

            if (hasConflict) {
                showNotification("課程衝堂，請選擇其他課程！\n\n已衝堂：\n" + conflictDetails.join("\n"), 'error');
                // 視覺上標示衝堂的格子
                slots.forEach((slot) => {
                    const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
                    if (!match) return;
                    const day = match[1];
                    const periods = match[2].split(",").map(Number);
                    periods.forEach((p) => {
                        const cell = document.getElementById(`cell-${day}-${p}`);
                        if (cell) {
                            cell.classList.add("conflict"); // 暫時添加衝堂樣式
                            setTimeout(() => { // 幾秒後移除，只作為視覺提示
                                cell.classList.remove("conflict");
                            }, 2000);
                        }
                    });
                });
                return; // 如果衝堂，則不加入課程
            }

            // 如果沒有衝堂，則將課程加入課表
            slots.forEach((slot) => {
                const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
                if (!match) return;
                const day = match[1];
                const periods = match[2].split(",").map(Number);
                periods.forEach((p) => {
                    const cell = document.getElementById(`cell-${day}-${p}`);
                    if (cell) {
                        cell.textContent = name;
                        cell.classList.add("highlight");
                        cell.dataset.courseName = name; // 將科目名稱儲存到 dataset
                        cell.dataset.courseCode = code; // 儲存課程代碼，用於移除
                        cell.onclick = removeCourse; // 綁定點擊事件來移除課程
                    }
                });
            });

            // 只有第一次加入該課程時才更新學分數並存入資料庫
            selectedCoursesOnTimetable[name] = { credit: parseInt(credit), code: code }; // 儲存科目名稱、學分數和代碼
            updateCreditDisplay();
            saveCourseToDatabase(code, name); // 儲存到資料庫，傳遞課程代碼和名稱
        }

        // 將無固定時段課程加入面板，並觸發資料庫儲存 (這是使用者拖曳時調用)
        function addNoFixedCourseAndSaveToDatabase(course) {
            const { name, credit, code } = course; // 取得課程代碼

            // 如果已經在清單中，提示使用者
            if (selectedCoursesOnTimetable.hasOwnProperty(name)) {
                showNotification(`課程「${name}」已在「無固定時段課程」清單中。`, 'warning');
                return;
            }

            // 先在前端顯示
            const courseDiv = document.createElement("div");
            courseDiv.className = "nofixed-course";
            courseDiv.dataset.courseName = name; // 儲存科目名稱
            courseDiv.dataset.courseCode = code; // 儲存課程代碼
            courseDiv.innerHTML = `
                <span>${name} (${credit} 學分數)</span>
                <button onclick="removeNoFixedCourse('${name}', '${code}')">移除</button>
            `;
            nofixedList.appendChild(courseDiv);

            selectedCoursesOnTimetable[name] = { credit: parseInt(credit), code: code }; // 儲存科目名稱、學分數和代碼
            updateCreditDisplay();
            
            // 觸發資料庫儲存
            saveCourseToDatabase(code, name);
        }

        // 移除固定時段課程
        function removeCourse(ev) {
            const cell = ev.target;
            // 檢查點擊的元素是否是帶有課程的格子
            if (!cell.classList.contains("highlight")) return;

            const name = cell.dataset.courseName; // 從 dataset 取得科目名稱
            const code = cell.dataset.courseCode; // 從 dataset 取得課程代碼
            if (!name || !code) return;

            if (!confirm(`確定要從課表移除「${name}」？`)) return;

            // 找到所有顯示該課程的格子並清空
            const cells = document.querySelectorAll(".timetable td[data-course-code]");
            cells.forEach((c) => {
                if (c.dataset.courseCode === code) { // 使用課程代碼來精確匹配
                    c.textContent = "";
                    c.classList.remove("highlight", "conflict");
                    delete c.dataset.courseName; // 移除 dataset 屬性
                    delete c.dataset.courseCode;
                    c.onclick = null; // 移除事件監聽器
                }
            });

            // 從 selectedCoursesOnTimetable 和資料庫中移除
            if (selectedCoursesOnTimetable.hasOwnProperty(name)) {
                delete selectedCoursesOnTimetable[name];
                updateCreditDisplay();
                deleteCourseFromDatabase(code); // 從資料庫刪除，傳遞課程代碼
            }
        }

        // 移除無固定時段課程
        function removeNoFixedCourse(name, code) { // 接收科目名稱和代碼
            if (!confirm(`確定要從「無固定時段課程」中移除「${name}」？`)) return;

            // 找到對應的課程 div 並移除
            const courseDiv = nofixedList.querySelector(`[data-course-code="${code}"]`); // 使用課程代碼選擇
            if (courseDiv) {
                nofixedList.removeChild(courseDiv);
            }

            // 從 selectedCoursesOnTimetable 和資料庫中移除
            if (selectedCoursesOnTimetable.hasOwnProperty(name)) {
                delete selectedCoursesOnTimetable[name];
                updateCreditDisplay();
                deleteCourseFromDatabase(code); // 從資料庫刪除，傳遞課程代碼
            }
        }

        // 顯示總學分數
        function updateCreditDisplay() {
            // 遍歷 selectedCoursesOnTimetable 物件的值 (每個值都是 { credit, code } 物件)
            const total = Object.values(selectedCoursesOnTimetable).reduce((sum, courseInfo) => sum + courseInfo.credit, 0);
            document.getElementById("credit-total").textContent = `已選學分數：${total} 學分數`;
        }

        // 從資料庫載入已選課程 (此函數已修正，不再觸發 saveSelectedCourse)
        function loadSelectedCoursesFromDatabase() {
            fetch("select_course.php?action=get_selected")
                .then((res) => {
                    if (!res.ok) {
                        return res.text().then(text => { throw new Error(`載入課表 HTTP error! status: ${res.status}, body: ${text}`); });
                    }
                    return res.json();
                })
                .then((data) => {
                    console.log("✅ 已選課程資料（從資料庫載入）：", data);
                    // 清空之前的選課狀態和課表顯示
                    selectedCoursesOnTimetable = {};
                    initializeTimetableGrid(); // 重新初始化課表網格
                    nofixedList.innerHTML = ''; // 清空無固定時段列表

                    if (data.status === 'success' && data.courses) {
                        data.courses.forEach((course) => {
                            const name = course.科目名稱;
                            const time = course.時間;
                            const credit = course.學分數;
                            const code = course.course_code; // 注意這裡使用 course_code

                            if (!name || !code) {
                                console.warn(`載入課程時資料不完整: ${JSON.stringify(course)}`);
                                return;
                            }

                            // 直接將課程添加到前端顯示，不觸發資料庫寫入
                            if (time && time.includes("無固定時段")) {
                                const courseDiv = document.createElement("div");
                                courseDiv.className = "nofixed-course";
                                courseDiv.dataset.courseName = name;
                                courseDiv.dataset.courseCode = code;
                                courseDiv.innerHTML = `
                                    <span>${name} (${credit} 學分數)</span>
                                    <button onclick="removeNoFixedCourse('${name}', '${code}')">移除</button>
                                `;
                                nofixedList.appendChild(courseDiv);
                            } else if (time) { // 處理固定時段課程
                                const slots = time.split("、");
                                // 這裡無需再檢查衝堂並彈出 alert，因為這些是已選課程
                                slots.forEach((slot) => {
                                    const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
                                    if (!match) {
                                        console.warn(`載入課程「${name}」時遇到無效時間格式: ${slot}`);
                                        return;
                                    }

                                    const day = match[1];
                                    const periods = match[2].split(",").map(Number);

                                    periods.forEach((p) => {
                                        const cell = document.getElementById(`cell-${day}-${p}`);
                                        if (cell) {
                                            // 載入時也檢查是否有衝突，但不阻止載入
                                            if (cell.textContent.trim() !== "" && cell.dataset.courseCode !== code) {
                                                console.warn(`載入課程「${name}」時發現衝堂於 星期${day} 第${p}節，已佔用: ${cell.textContent} (代碼: ${cell.dataset.courseCode})`);
                                                cell.classList.add("conflict"); // 標示為衝突
                                            }
                                            cell.textContent = name;
                                            cell.classList.add("highlight");
                                            cell.dataset.courseName = name;
                                            cell.dataset.courseCode = code;
                                            cell.onclick = removeCourse; // 綁定點擊事件來移除課程
                                        } else {
                                            console.warn(`載入課程「${name}」時，找不到單元格: cell-${day}-${p}`);
                                        }
                                    });
                                });
                            } else {
                                console.warn(`課程「${name}」沒有時間資訊或格式異常，無法顯示在課表。`);
                            }
                            // 無論是否顯示在課表上，都將課程添加到 selectedCoursesOnTimetable 用於學分計算
                            selectedCoursesOnTimetable[name] = { credit: parseInt(credit), code: code };
                        });
                    } else {
                         // 如果 data.status 不是 success 或者 courses 為空，顯示沒有已選課程
                         console.warn("未找到已選課程或載入失敗:", data.message);
                         // 清空顯示內容以反映沒有課程
                         nofixedList.innerHTML = '';
                    }
                    updateCreditDisplay(); // 載入所有課程後更新學分數顯示
                    updateSelectedCoursesDisplay(); // 更新已選課程列表（右側列表）
                    updateCourseStatus(); // 確保搜尋結果列表的按鈕狀態也更新
                })
                .catch((err) => {
                    console.error("❌ 載入已選課表發生錯誤：", err);
                    showNotification("無法載入已選課表，請稍後再試。錯誤訊息：\n" + err.message, 'error');
                });
        }

        // 將課程加入資料庫 (從拖曳或「加入」按鈕觸發)
        function saveCourseToDatabase(course_code, course_name) {
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=add`,
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
                if (data.status === 'success') {
                    showNotification(data.message);
                    console.log(`✅ 課程「${course_name}」(${course_code}) 已成功加入資料庫。`);
                    // 成功加入資料庫後，同步更新右側的「我的已選課程」列表
                    updateSelectedCoursesDisplay();
                } else if (data.status === 'warning') {
                    showNotification(data.message, 'warning');
                    console.warn(`⚠ 課程「${course_name}」(${course_code}) 已存在:`, data.message);
                }
                else {
                    showNotification(data.message, 'error');
                    console.error(`❌ 課程「${course_name}」(${course_code}) 加入資料庫失敗:`, data.message);
                }
                // 無論成功與否，都更新一下搜尋結果中的按鈕狀態
                updateCourseStatus();
            })
            .catch(error => {
                console.error(`❌ 課程「${course_name}」(${course_code}) 加入時發生網路錯誤:`, error);
                showNotification(`課程「${course_name}」加入時發生錯誤，請檢查網路連線。`, 'error');
            });
        }

        // 從資料庫刪除課程
        function deleteCourseFromDatabase(course_code) { 
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=drop`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showNotification(data.message);
                    console.log(`✅ 課程代碼 ${course_code} 已成功從資料庫移除。`);
                    // 資料庫更新成功後，同步更新右側的「我的已選課程」列表
                    updateSelectedCoursesDisplay();
                    // 更新搜尋結果中的按鈕狀態
                    updateCourseStatus();
                } else {
                    showNotification(`課程移除失敗: ${data.message}`, 'error');
                    console.error(`❌ 課程代碼 ${course_code} 從資料庫移除失敗:`, data.message);
                }
            })
            .catch(error => {
                console.error(`❌ 課程代碼 ${course_code} 移除時發生網路錯誤:`, error);
                showNotification(`課程移除時發生錯誤，請檢查網路連線。`, 'error');
            });
        }


        // 初始化
        window.addEventListener("DOMContentLoaded", () => {
            initializeTimetableGrid(); // 先初始化空的課表網格
            loadSelectedCoursesFromDatabase(); // 載入已選課程 (包含固定及無固定時段)
            triggerSearch(); // 載入可選課程
        });
    </script>
</body>
</html>
