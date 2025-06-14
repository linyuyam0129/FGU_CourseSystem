<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>輔助選課系統</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* --- 整體佈局與基礎 --- */
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

        /* --- 總學分顯示 --- */
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
    </style>
</head>
<body>
    <div class="header">
        輔助選課系統
        <div class="header-buttons">
            <button onclick="location.href='login.php'"><i class="fas fa-sign-out-alt"></i> 登出</button>
            <button onclick="location.href='index.php'"><i class="fas fa-chalkboard"></i> 畢業門檻狀態</button>
            <button onclick="location.href='Downloads.html'"><i class="fas fa-download"></i> 下載手冊</button>
        </div>
    </div>

    <div class="container">
        <div class="panel">
            <h3> 課程搜尋</h3>
            <p style="font-size: 14px; color: #555; margin-bottom: 15px; background-color: #e8f5e9; padding: 10px; border-radius: 5px;">
          💡提示：將課程從下方課程列表拖曳至右方課表即可加入。點擊我的課表中的課程即可移除。
    </p>
            <div class="search-controls">
                <input type="text" id="search-input" placeholder="輸入課程名稱 / 教師 / 代碼">
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
                    <option value="AE">經濟系 AE</option>
                </optgroup>
                <optgroup label="社會科學學院">
                    <option value="SO">社會科學學院 SO</option>
                    <option value="SC">心理系 SC</option>
                    <option value="PA">公事系 PA</option>
                    <option value="SY">社會系 SY</option>
                </optgroup>
                <optgroup label="人文學院">
                    <option value="HC">人文學院 HC</option>
                    <option value="LC">外文系 LC</option>
                    <option value="LE">中文系 LE</option>
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

            <p id="credit-total">已選學分：0 學分</p>
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
        let selectedCourses = {}; // 儲存已選課程的「課程名稱: 學分」對

        // 新增此函數
function fetchAndDisplaySelectedCourses() {
    fetch('fetch_timetable.php')
        .then(response => {
            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.error || `HTTP 錯誤！狀態碼: ${response.status}`);
                });
            }
            return response.json();
        })
        .then(courses => {
            const selectedCoursesList = document.getElementById('selected-courses-list');
            selectedCoursesList.innerHTML = ''; // 清空現有列表
            let totalCredits = 0;

            if (courses.length === 0) {
                selectedCoursesList.innerHTML = '<li class="no-courses">尚未選取任何課程。</li>';
            } else {
                courses.forEach(course => {
                    const li = document.createElement('li');
                    // 這裡需要確保 fetch_timetable.php 返回 '課程代碼'
                    li.innerHTML = `
                        <span>${course.課程名稱} - <span class="math-inline">\{course\.時間\} \(</span>{course.學分}學分)</span>
                        <button class="drop-button" data-course-code="${course.課程代碼}">刪除</button>
                    `;
                    selectedCoursesList.appendChild(li);
                    totalCredits += parseInt(course.學分);
                });
            }
            document.getElementById('total-credits').textContent = totalCredits;
        })
        .catch(error => {
            console.error('❌ 載入已選課程時發生錯誤:', error);
            document.getElementById('selected-courses-list').innerHTML = `<li class="error-message">載入已選課程失敗: ${error.message}. 請稍後再試。</li>`;
        });
}

        // 建立課表表格
        timeSlots.forEach((t, i) => {
            const row = document.createElement("tr");
            row.innerHTML = `<td>第${i + 1}節<br>${t}</td>`;
            days.forEach((day) => {
                // 為每個課表單元格添加 drop 和 dragover 事件監聽器
                row.innerHTML += `<td id="cell-${day}-${i + 1}" ondrop="drop(event)" ondragover="allowDrop(event)"></td>`;
            });
            tbody.appendChild(row);
        });

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
            table.innerHTML = `<tr><th>課程名稱</th><th>時間</th><th>教師</th><th>修別</th><th>學分</th><th>教室</th><th>通識課群</th></tr>`;
            courses.forEach(c => {
                const tr = document.createElement("tr");
                tr.draggable = true; // 設定為可拖曳
                tr.dataset.name = c.課程名稱;
                tr.dataset.time = c.時間;
                tr.dataset.credit = c.學分;
                tr.dataset.teacher = c.教師;
                tr.dataset.type = c.修別;
                tr.dataset.room = c.教室;
                tr.dataset.code = c.課程代碼; // 儲存課程代碼，用於選課和退選
                tr.ondragstart = drag; // 綁定拖曳開始事件

                // 根據課程代碼顯示通識課群
                const genEdGroup = c.通識課群 ? c.通識課群 : '—'; // 如果沒有通識課群則顯示 —

                tr.innerHTML = `
                    <td>${c.課程名稱}</td>
                    <td>${c.時間}</td>
                    <td>${c.教師}</td>
                    <td>${c.修別}</td>
                    <td>${c.學分}</td>
                    <td>${c.教室}</td>
                    <td>${genEdGroup}</td>`;
                table.appendChild(tr);
            });
            resultDiv.appendChild(table);
        }

        // 拖拉功能：允許放置
        function allowDrop(ev) {
            ev.preventDefault(); // 阻止默認行為，允許放置
            // 可選：當拖曳物體進入可放置區域時，添加視覺回饋
            // ev.target.style.backgroundColor = '#e6ffe6';
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

        // 拖曳結束時移除樣式 (可以在任何地方，但通常放在 drop 或 dragend 事件中)
        document.addEventListener('dragend', (ev) => {
            ev.target.classList.remove('dragging');
        });


        // 放入課表
        function drop(ev) {
            ev.preventDefault(); // 阻止默認行為

            // 可選：當拖曳物體離開可放置區域或放置後，移除視覺回饋
            // ev.target.style.backgroundColor = '';

            const courseData = JSON.parse(ev.dataTransfer.getData("text/plain"));
            const { name, time, credit, code } = courseData; // 取得課程代碼

            // 如果課程名稱為空，通常不應發生，但做個防範
            if (!name) {
                console.warn("嘗試拖曳無課程名稱的項目");
                return;
            }

            // 檢查課程是否已經選過
            if (selectedCourses.hasOwnProperty(name)) {
                alert(`課程「${name}」已在您的課表中或無固定時段清單中。`);
                return;
            }

            // 處理「無固定時段授課」的課程
            if (time && time.includes("無固定時段")) {
                addNoFixedCourse(courseData); // 將整個 courseData 傳入
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
                alert("課程衝堂，請選擇其他課程！\n\n已衝堂：\n" + conflictDetails.join("\n"));
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
                        cell.dataset.courseName = name; // 將課程名稱儲存到 dataset
                        cell.dataset.courseCode = code; // 儲存課程代碼，用於移除
                        cell.onclick = removeCourse; // 綁定點擊事件來移除課程
                    }
                });
            });

            // 只有第一次加入該課程時才更新學分並存入資料庫
            selectedCourses[name] = { credit: parseInt(credit), code: code }; // 儲存課程名稱、學分和代碼
            updateCreditDisplay();
            saveSelectedCourse(code, name); // 儲存到資料庫，傳遞課程代碼和名稱
        }

        function addSelectedCourse(course_code, course_name) {
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=add`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(`✅ 課程「${course_name}」已成功加入。`);
                    alert(`課程「${course_name}」加入成功！`);
                    // *** <-- 在這裡新增這行 --> ***
                    fetchAndDisplaySelectedCourses(); // 成功後重新整理已選課程列表
                } else {
                    console.error(`❌ 課程「${course_name}」加入失敗: ${data.message}`);
                    alert(`課程「${course_name}」加入失敗: ${data.message}`);
                    // 如果資料庫操作失敗，考慮是否需要從前端 UI 回滾該課程
                }
            })
            .catch(error => {
                console.error(`❌ 課程「${course_name}」(${course_code}) 加入時發生網路錯誤:`, error);
                alert(`課程「${course_name}」加入時發生錯誤，請檢查網路連線。`);
            });
        }

        function deleteSelectedCourse(course_code) { // 傳遞課程代碼進行刪除
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=drop`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(`✅ 課程代碼 ${course_code} 已成功從資料庫移除。`);
                    // *** <-- 在這裡新增這行 --> ***
                    alert(`課程移除成功！`); // 新增：成功提示
                    // *** <-- 在這裡新增這行 --> ***
                    fetchAndDisplaySelectedCourses(); // 成功後重新整理已選課程列表
                } else {
                    console.error(`❌ 課程代碼 ${course_code} 從資料庫移除失敗:`, data.message);
                    alert(`課程移除失敗: ${data.message}`);
                }
            })
            .catch(error => {
                console.error(`❌ 課程代碼 ${course_code} 移除時發生網路錯誤:`, error);
                alert(`課程移除時發生錯誤，請檢查網路連線。`);
            });
        }

        // *** <-- 在這裡新增以下整個函數 --> ***
    function fetchAndDisplaySelectedCourses() {
        fetch('fetch_timetable.php')
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.error || `HTTP 錯誤！狀態碼: ${response.status}`);
                    });
                }
                return response.json();
            })
            .then(courses => {
                const selectedCoursesList = document.getElementById('selected-courses-list');
                selectedCoursesList.innerHTML = ''; // 清空現有列表
                let totalCredits = 0;

                if (courses.length === 0) {
                    selectedCoursesList.innerHTML = '<li class="no-courses">尚未選取任何課程。</li>';
                } else {
                    courses.forEach(course => {
                        const li = document.createElement('li');
                        // 這裡需要確保 fetch_timetable.php 返回 '課程代碼'
                        li.innerHTML = `
                            <span>${course.課程名稱} - ${course.時間} (${course.學分}學分)</span>
                            <button class="drop-button" data-course-code="${course.課程代碼}">刪除</button>
                        `;
                        selectedCoursesList.appendChild(li);
                        totalCredits += parseInt(course.學分);
                    });
                }
                document.getElementById('total-credits').textContent = totalCredits;
            })
            .catch(error => {
                console.error('❌ 載入已選課程時發生錯誤:', error);
                document.getElementById('selected-courses-list').innerHTML = `<li class="error-message">載入已選課程失敗: ${error.message}. 請稍後再試。</li>`;
            });
    }
    
        // 將無固定時段課程加入面板
        function addNoFixedCourse(course) {
            const { name, credit, code } = course; // 取得課程代碼

            // 如果已經在清單中，提示使用者
            if (selectedCourses.hasOwnProperty(name)) {
                alert(`課程「${name}」已在「無固定時段課程」清單中。`);
                return;
            }

            const courseDiv = document.createElement("div");
            courseDiv.className = "nofixed-course";
            courseDiv.dataset.courseName = name; // 儲存課程名稱
            courseDiv.dataset.courseCode = code; // 儲存課程代碼
            courseDiv.innerHTML = `
                <span>${name} (${credit} 學分)</span>
                <button onclick="removeNoFixedCourse('${name}', '${code}')">移除</button>
            `;
            nofixedList.appendChild(courseDiv);

            selectedCourses[name] = { credit: parseInt(credit), code: code }; // 儲存課程名稱、學分和代碼
            updateCreditDisplay();
            saveSelectedCourse(code, name); // 儲存到資料庫
        }

        // 移除固定時段課程
        function removeCourse(ev) {
            const cell = ev.target;
            // 檢查點擊的元素是否是帶有課程的格子
            if (!cell.classList.contains("highlight")) return;

            const name = cell.dataset.courseName; // 從 dataset 取得課程名稱
            const code = cell.dataset.courseCode; // 從 dataset 取得課程代碼
            if (!name || !code) return;

            if (!confirm(`確定要從課表移除「${name}」？`)) return;

            // 找到所有顯示該課程的格子並清空
            const cells = document.querySelectorAll(".timetable td[data-course-name]");
            cells.forEach((c) => {
                if (c.dataset.courseCode === code) { // 使用課程代碼來精確匹配
                    c.textContent = "";
                    c.classList.remove("highlight", "conflict");
                    delete c.dataset.courseName; // 移除 dataset 屬性
                    delete c.dataset.courseCode;
                    c.onclick = null; // 移除事件監聽器
                }
            });

            // 從 selectedCourses 和資料庫中移除
            if (selectedCourses.hasOwnProperty(name)) {
                delete selectedCourses[name];
                updateCreditDisplay();
                deleteSelectedCourse(code); // 從資料庫刪除，傳遞課程代碼
            }
        }

        // 移除無固定時段課程
        function removeNoFixedCourse(name, code) { // 接收課程名稱和代碼
            if (!confirm(`確定要從「無固定時段課程」中移除「${name}」？`)) return;

            // 找到對應的課程 div 並移除
            const courseDiv = nofixedList.querySelector(`[data-course-code="${code}"]`); // 使用課程代碼選擇
            if (courseDiv) {
                nofixedList.removeChild(courseDiv);
            }

            // 從 selectedCourses 和資料庫中移除
            if (selectedCourses.hasOwnProperty(name)) {
                delete selectedCourses[name];
                updateCreditDisplay();
                deleteSelectedCourse(code); // 從資料庫刪除，傳遞課程代碼
            }
        }

        // 顯示總學分
        function updateCreditDisplay() {
            // 遍歷 selectedCourses 物件的值 (每個值都是 { credit, code } 物件)
            const total = Object.values(selectedCourses).reduce((sum, courseInfo) => sum + courseInfo.credit, 0);
            document.getElementById("credit-total").textContent = `已選學分：${total} 學分`;
        }

        // 從資料庫載入已選課程
        function loadSelectedCourses() {
            fetch("fetch_timetable.php")
                .then((res) => {
                    if (!res.ok) {
                        // 處理 HTTP 錯誤
                        return res.text().then(text => { throw new Error(`載入課表 HTTP error! status: ${res.status}, body: ${text}`); });
                    }
                    return res.json();
                })
                .then((data) => {
                    console.log("✅ 已選課程資料：", data);
                    // 清空之前的選課狀態，以確保從資料庫載入的資料是唯一的
                    selectedCourses = {};
                    tbody.innerHTML = ''; // 清空課表內容
                    nofixedList.innerHTML = ''; // 清空無固定時段列表

                    // 重新建立空的課表結構
                    timeSlots.forEach((t, i) => {
                        const row = document.createElement("tr");
                        row.innerHTML = `<td>第${i + 1}節<br>${t}</td>`;
                        days.forEach((day) => {
                            row.innerHTML += `<td id="cell-${day}-${i + 1}" ondrop="drop(event)" ondragover="allowDrop(event)"></td>`;
                        });
                        tbody.appendChild(row);
                    });


                    data.forEach((course) => {
                        const name = course.課程名稱;
                        const time = course.時間;
                        const credit = course.學分;
                        const code = course.課程代碼; // 假設 fetch_timetable.php 也返回課程代碼

                        if (!name || !code) return; // 課程名稱和代碼是必要的

                        // 如果課程名稱已存在，則不重複添加，這點在後端查詢時應該避免
                        // 但前端再做一次檢查也無妨
                        if (selectedCourses.hasOwnProperty(name)) {
                            console.warn(`重複載入課程: ${name}, 代碼: ${code}`);
                            return;
                        }

                        // 處理無固定時段課程
                        if (time && time.includes("無固定時段")) {
                            // 直接呼叫 addNoFixedCourse，它會處理添加到列表和 selectedCourses
                            addNoFixedCourse({ name, time, credit, code });
                        } else if (time) { // 處理固定時段課程
                            const slots = time.split("、");
                            let allCellsOccupied = true; // 假設所有時段都能被佔用

                            slots.forEach((slot) => {
                                const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
                                if (!match) {
                                    console.warn(`載入課程「${name}」時遇到無效時間格式: ${slot}`);
                                    allCellsOccupied = false; // 有無效格式，則可能無法完全佔用
                                    return;
                                }

                                const day = match[1];
                                const periods = match[2].split(",").map(Number);

                                periods.forEach((p) => {
                                    const cell = document.getElementById(`cell-${day}-${p}`);
                                    if (cell) {
                                        // 檢查是否衝堂（從資料庫載入時，不應發生衝堂，但仍可警示）
                                        if (cell.textContent.trim() !== "" && cell.dataset.courseCode !== code) {
                                            console.warn(`載入課程「${name}」時發現衝堂於 星期${day} 第${p}節，已佔用: ${cell.textContent}`);
                                            cell.classList.add("conflict"); // 標示為衝突
                                            // 可以選擇不載入此課程，或覆蓋，或通知用戶
                                            // 這裡暫時仍載入，但保持衝突標記
                                        }
                                        cell.textContent = name;
                                        cell.classList.add("highlight");
                                        cell.dataset.courseName = name;
                                        cell.dataset.courseCode = code; // 儲存課程代碼
                                        cell.onclick = removeCourse; // 綁定點擊事件來移除課程
                                    } else {
                                        console.warn(`載入課程「${name}」時，找不到單元格: cell-${day}-${p}`);
                                        allCellsOccupied = false;
                                    }
                                });
                            });
                            // 如果所有時段都成功載入，才加入 selectedCourses
                            if (allCellsOccupied) {
                                selectedCourses[name] = { credit: parseInt(credit), code: code };
                            } else {
                                console.error(`課程「${name}」部分時段載入失敗，可能導致學分計算不準確或顯示不完整。`);
                                // 即使有部分失敗，為了學分計算，先加入 selectedCourses
                                selectedCourses[name] = { credit: parseInt(credit), code: code };
                            }
                        } else {
                             console.warn(`課程「${name}」沒有時間資訊或格式異常，無法顯示。`);
                             // 即使沒有時間，如果課程名存在，也加入 selectedCourses 以計入學分
                             selectedCourses[name] = { credit: parseInt(credit), code: code };
                        }
                    });
                    updateCreditDisplay(); // 載入所有課程後更新學分顯示
                })
                .catch((err) => {
                    console.error("❌ 載入課表發生錯誤：", err);
                    alert("無法載入已選課表，請稍後再試。錯誤訊息：\n" + err.message);
                });
        }

        // 加入與移除課程到資料庫
        // select_course.php 會接收 course_code 和 action (add/drop)
        function saveSelectedCourse(course_code, course_name) { // 同時傳遞課程名稱用於日誌或提示
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=add`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(`✅ 課程「${course_name}」(${course_code}) 已成功加入資料庫。`);
                } else {
                    console.error(`❌ 課程「${course_name}」(${course_code}) 加入資料庫失敗:`, data.message);
                    alert(`課程「${course_name}」加入失敗: ${data.message}`);
                    // 如果資料庫操作失敗，考慮是否需要從前端 UI 回滾該課程
                }
            })
            .catch(error => {
                console.error(`❌ 課程「${course_name}」(${course_code}) 加入時發生網路錯誤:`, error);
                alert(`課程「${course_name}」加入時發生錯誤，請檢查網路連線。`);
            });
        }

        function deleteSelectedCourse(course_code) { // 傳遞課程代碼進行刪除
            fetch("select_course.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: `course_code=${encodeURIComponent(course_code)}&action=drop`,
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    console.log(`✅ 課程代碼 ${course_code} 已成功從資料庫移除。`);
                } else {
                    console.error(`❌ 課程代碼 ${course_code} 從資料庫移除失敗:`, data.message);
                    alert(`課程移除失敗: ${data.message}`);
                }
            })
            .catch(error => {
                console.error(`❌ 課程代碼 ${course_code} 移除時發生網路錯誤:`, error);
                alert(`課程移除時發生錯誤，請檢查網路連線。`);
            });
        }


        // 初始化
        window.addEventListener("DOMContentLoaded", () => {
            loadSelectedCourses(); // 載入已選課程 (包含固定及無固定時段)
            triggerSearch(); // 載入可選課程
        });
    </script>
</body>
</html>