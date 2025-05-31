<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>選課拖拉整合版</title>
  <style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #f1f8e9; color: #333; margin: 0; }
    .header {
      background-color: #00796b;
      color: white;
      padding: 20px;
      text-align: center;
      font-size: 28px;
      font-weight: bold;
      position: relative;
    }
    .header-buttons {
      position: absolute;
      right: 20px;
      top: 20px;
    }
    .header-buttons button {
      background-color: #ffffff;
      color: #00796b;
      border: 1px solid #00796b;
      margin-left: 10px;
      padding: 6px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      transition: 0.2s ease;
    }
    .header-buttons button:hover {
      background-color: #00796b;
      color: white;
    }
    .container { display: flex; gap: 20px; padding: 20px; flex-wrap: wrap; }
    .panel { flex: 1; background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); min-width: 300px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
    th { background-color: #ffd54f; }
    td:first-child { background-color: #b2dfdb; font-weight: bold; }
    .highlight { background-color: #e0f7fa; cursor: pointer; }
    .conflict { background-color: #ffccbc !important; }
    .scrollable { max-height: 400px; overflow-y: auto; }
    .draggable-course {
      background: #fff3e0;
      border: 1px solid #ffb300;
      padding: 6px 10px;
      margin: 4px;
      border-radius: 8px;
      cursor: grab;
      display: inline-block;
    }
    .draggable-course:active {
      cursor: grabbing;
    }
  </style>
</head>
<body>
<div class="header">
  輔助選課系統
  <div class="header-buttons">
    <button onclick="location.href='login.php'">登出</button>
    <button onclick="location.href='index.php'">回首頁</button>
    <button onclick="location.href='Downloads.html'">下載手冊</button>
  </div>
</div>
<div class="container">
  <div class="panel">
    <h3>🔍 課程搜尋</h3>
    <div style="display: flex; gap: 10px; margin-bottom: 10px; flex-wrap: wrap;">
      <input type="text" id="search-input" placeholder="課程名稱 / 教師 / 代碼" style="flex: 2; padding: 6px;">
      <select id="filter-type" style="flex: 1; padding: 6px;">
        <option value="">全部修別</option>
        <option value="必修">必修</option>
        <option value="選修">選修</option>
        <option value="通識">通識</option>
      </select>
      <select id="filter-day" style="flex: 1; padding: 6px;">
        <option value="">全部星期</option>
        <option value="一">星期一</option>
        <option value="二">星期二</option>
        <option value="三">星期三</option>
        <option value="四">星期四</option>
        <option value="五">星期五</option>
      </select>
      <select id="filter-dept" style="flex: 1; padding: 6px;">
  <option value="">全部學院</option>

  <optgroup label="創意與科技學院">
    <option value="CT">創科院 CT</option>
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
  </optgroup>

  <optgroup label="社會科學學院">
    <option value="SO">社科院 SO</option>
    <option value="AE">經濟系 AE</option>
    <option value="SC">心理系 SC</option>
    <option value="PA">公事系 PA</option>
    <option value="SY">社會系 SY</option>
  </optgroup>

  <optgroup label="人文學院">
    <option value="HC">人文院 HC</option>
    <option value="LC">外文系 LC</option>
    <option value="HI">中文系 HI</option>
  </optgroup>

  <optgroup label="資訊應用學系">
    <option value="CS">資應系 CS</option>
  </optgroup>

  <optgroup label="傳播學院 / 系所">
    <option value="PM">產媒系 PM</option>
    <option value="CN">傳播系 CN</option>
  </optgroup>

  <optgroup label="其他 / 跨領域">
    <option value="CA">文資系 CA</option>
    <option value="AR">建築系 AR</option>
  </optgroup>
</select>
    </div>
    <div id="search-results" class="scrollable"></div>
  </div>

  <div class="panel">
    <h3>📜我的課表</h3>
    <table class="timetable">
      <thead>
        <tr><th>節次</th><th>一</th><th>二</th><th>三</th><th>四</th><th>五</th></tr>
      </thead>
      <tbody id="timetable-body"></tbody>
    </table>
    <p id="credit-total" style="margin-top: 10px; font-weight: bold; text-align: right;">已選學分：0 學分</p>
  </div>

<script>
const timeSlots = [
  "09:10-10:00", "10:20-11:10", "11:20-12:10",
  "13:10-14:00", "14:10-15:00", "15:20-16:10", "16:20-17:10", "17:20-18:10",
  "18:20-19:10", "19:20-20:10", "20:20-21:10"
];
const days = ["一", "二", "三", "四", "五"];
const tbody = document.getElementById("timetable-body");
let selectedCourses = {};

// 建立課表表格
timeSlots.forEach((t, i) => {
  const row = document.createElement('tr');
  row.innerHTML = `<td>第${i + 1}節<br>${t}</td>`;
  days.forEach(day => {
    row.innerHTML += `<td id="cell-${day}-${i + 1}" ondrop="drop(event)" ondragover="allowDrop(event)" onclick="removeCourse(event)"></td>`;
  });
  tbody.appendChild(row);
});

// 綁定搜尋欄位事件
['search-input', 'filter-type', 'filter-day', 'filter-dept'].forEach(id => {
  document.getElementById(id).addEventListener('input', triggerSearch);
  document.getElementById(id).addEventListener('change', triggerSearch);
});

// 搜尋課程並顯示
function triggerSearch() {
  const keyword = document.getElementById('search-input').value.trim();
  const type = document.getElementById('filter-type').value;
  const day = document.getElementById('filter-day').value;
  const dept = document.getElementById('filter-dept').value;

  fetch(`search_course.php?keyword=${encodeURIComponent(keyword)}&type=${encodeURIComponent(type)}&day=${encodeURIComponent(day)}&dept=${encodeURIComponent(dept)}`)
    .then(res => res.json())
    .then(displayResults);
}

function displayResults(courses) {
  const resultDiv = document.getElementById('search-results');
  resultDiv.innerHTML = '';
  if (courses.length === 0) {
    resultDiv.innerHTML = '<p>查無結果</p>';
    return;
  }

  const table = document.createElement('table');
  table.innerHTML = `<tr><th>課程名稱</th><th>時間</th><th>教師</th><th>修別</th><th>學分</th><th>教室</th></tr>`;
  courses.forEach(c => {
    const tr = document.createElement('tr');
    tr.draggable = true;
    tr.dataset.name = c.課程名稱;
    tr.dataset.time = c.時間;
    tr.dataset.credit = c.學分;
    tr.ondragstart = drag;
    tr.innerHTML = `
      <td>${c.課程名稱}</td>
      <td>${c.時間}</td>
      <td>${c.教師}</td>
      <td>${c.修別}</td>
      <td>${c.學分}</td>
      <td>${c.教室}</td>`;
    table.appendChild(tr);
  });
  resultDiv.appendChild(table);
}

// 拖拉功能
function allowDrop(ev) {
  ev.preventDefault();
}

function drag(ev) {
  const name = ev.target.dataset.name;
  const time = ev.target.dataset.time;
  const credit = ev.target.dataset.credit;
  ev.dataTransfer.setData("text/plain", `${name}|${time}|${credit}`);
}

// 放入課表
function drop(ev) {
  ev.preventDefault();
  const data = ev.dataTransfer.getData("text/plain");
  const [name, time, credit] = data.split('|');
  const slots = time.split('、');

  let hasConflict = false;
  let conflictDetails = [];

  slots.forEach(slot => {
    const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
    if (!match) return;
    const day = match[1];
    const periods = match[2].split(',');
    periods.forEach(p => {
      const cell = document.getElementById(`cell-${day}-${p}`);
      if (cell && cell.textContent.trim() !== '') {
        hasConflict = true;
        cell.classList.add('conflict');
        conflictDetails.push(`星期${day} 第${p}節：${cell.textContent}`);
      }
    });
  });

  if (hasConflict) {
    alert('課程衝堂，請選擇其他課程！\n\n已衝堂：\n' + conflictDetails.join('\n'));
    return;
  }

  slots.forEach(slot => {
    const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
    if (!match) return;
    const day = match[1];
    const periods = match[2].split(',');
    periods.forEach(p => {
      const cell = document.getElementById(`cell-${day}-${p}`);
      if (cell) {
        cell.textContent = name;
        cell.classList.add('highlight');
        cell.setAttribute('onclick', 'removeCourse(event)');
      }
    });
  });

  const firstTime = !selectedCourses.hasOwnProperty(name);
  selectedCourses[name] = parseInt(credit);
  updateCreditDisplay();
  updateSelectedList();
  if (firstTime) saveSelectedCourse(name);
}

// 移除課程
function removeCourse(ev) {
  if (!ev.target.classList.contains('highlight')) return;
  const name = ev.target.textContent;
  if (!confirm(`確定要從課表移除「${name}」？`)) return;

  const cells = document.querySelectorAll('.timetable td');
  cells.forEach(cell => {
    if (cell.textContent === name) {
      cell.textContent = '';
      cell.classList.remove('highlight', 'conflict');
    }
  });

  deleteSelectedCourse(name);
  delete selectedCourses[name];
  updateCreditDisplay();
  updateSelectedList();
}

// 顯示總學分
function updateCreditDisplay() {
  const total = Object.values(selectedCourses).reduce((sum, cr) => sum + cr, 0);
  document.getElementById('credit-total').textContent = `已選學分：${total} 學分`;
}

// 顯示已選課（可改成顯示在畫面上）
function updateSelectedList() {
  // 這邊可以顯示 selectedCourses 到某個 div，若你想加側欄或清單
}

// 從資料庫載入已選課程
  function loadSelectedCourses() {
  fetch('fetch_timetable.php')
    .then(res => {
      if (!res.ok) throw new Error('載入課表資料失敗');
      return res.json();
    })
    .then(data => {
      console.log("✅ 已選課程資料：", data);

      data.forEach(course => {
        const name = course.課程名稱;
        const time = course.時間;
        const credit = course.學分;

        if (!time || !name) return;

        const slots = time.split('、');
        slots.forEach(slot => {
          const match = slot.match(/星期([一二三四五])\s*([\d,]+)/);
          if (!match) return;

          const day = match[1];
          const periods = match[2].split(',');

          periods.forEach(p => {
            const cell = document.getElementById(`cell-${day}-${p}`);
            if (cell) {
              cell.textContent = name;
              cell.classList.add('highlight');
              cell.setAttribute('onclick', 'removeCourse(event)');
            }
          });
        });

        selectedCourses[name] = parseInt(credit);
      });

      updateCreditDisplay(); // 更新學分顯示
    })
    .catch(err => {
      console.error("❌ 載入課表發生錯誤：", err);
      alert("無法載入課表，請稍後再試。");
    });
}
// 加入與移除課程到資料庫
function saveSelectedCourse(name) {
  fetch('select_course.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `course_name=${encodeURIComponent(name)}&action=add`
  });
}

function deleteSelectedCourse(name) {
  fetch('select_course.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `course_name=${encodeURIComponent(name)}&action=drop`
  });
}

// 初始化
window.addEventListener('DOMContentLoaded', () => {
  loadSelectedCourses(); // ← 載入已選課程
  triggerSearch();       // ← 載入可選課程
});
</script>

</body>
</html>
