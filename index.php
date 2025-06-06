<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: login.php");
  exit();
}

$user_id = $_SESSION['user_id'];
$sql_user = "SELECT name, student_id, department, user_group FROM users WHERE id = ?";
$stmt_user = $conn->prepare($sql_user);
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
$user = $result_user->fetch_assoc();

$_SESSION['name'] = $user['name'];
$_SESSION['student_id'] = $user['student_id'];
$_SESSION['department'] = $user['department'];
$_SESSION['user_group'] = $user['user_group'];

$sql_courses = "SELECT * FROM completed_courses WHERE user_id = ?";
$stmt_courses = $conn->prepare($sql_courses);
$stmt_courses->bind_param("i", $user_id);
$stmt_courses->execute();
$courses_result = $stmt_courses->get_result();

$total_credits = 0;
$completed_courses = [];
while ($row = $courses_result->fetch_assoc()) {
  $completed_courses[] = $row;
  $total_credits += $row['credits'];
}

$sql_missing = "SELECT * FROM course_list 
WHERE 修別 = '必' AND CONVERT(`課程代碼` USING utf8mb4) COLLATE utf8mb4_unicode_ci NOT IN (
  SELECT CONVERT(course_code USING utf8mb4) COLLATE utf8mb4_unicode_ci FROM completed_courses WHERE user_id = ?
)";
$stmt_missing = $conn->prepare($sql_missing);
$stmt_missing->bind_param("i", $user_id);
$stmt_missing->execute();
$missing_result = $stmt_missing->get_result();
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>畢業門檻狀態</title>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Segoe UI', 'Noto Sans TC', sans-serif;
      background-color: #f1f8e9;
      margin: 0;
      color: #333;
    }
    .header {
      background-color: #00796b;
      color: white;
      padding: 20px;
      text-align: center;
      font-size: 26px;
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
      padding: 6px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: bold;
      transition: 0.2s ease;
    }
    .header-buttons button:hover {
      background-color: #00796b;
      color: white;
    }
    .container {
      max-width: 1100px;
      margin: 40px auto;
      background: white;
      padding: 30px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .info {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      background-color: #e8f5e9;
      padding: 20px;
      border-radius: 10px;
      margin-bottom: 30px;
      font-size: 16px;
    }
    .info div {
      flex: 1 1 45%;
      margin-bottom: 10px;
    }
    .input-section {
      background: #ffffff;
      border-left: 5px solid #fbc02d;
      border-radius: 10px;
      padding: 20px;
      margin-bottom: 30px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .input-section p {
      font-weight: bold;
      margin-bottom: 10px;
    }
    .input-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .input-group select,
    .input-group input {
      padding: 10px;
      font-size: 14px;
      border-radius: 6px;
      border: 1px solid #ccc;
      background: #f9f9f9;
      flex: 1 1 200px;
    }
    .input-group button {
      background-color: #388e3c;
      color: white;
      border: none;
      padding: 10px 16px;
      border-radius: 6px;
      font-weight: bold;
      cursor: pointer;
      transition: 0.3s ease;
    }
    .credit {
      background: #fff3e0;
      padding: 20px;
      text-align: center;
      font-weight: bold;
      font-size: 18px;
      border-radius: 10px;
      color: #d84315;
      margin-bottom: 30px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .courses {
      display: flex;
      justify-content: space-between;
      gap: 30px;
    }
    .course-list {
      width: 48%;
      background: #f1f8e9;
      padding: 20px;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    .course-list h2 {
      border-bottom: 2px solid #a5d6a7;
      padding-bottom: 6px;
      margin-bottom: 12px;
      color: #2e7d32;
      font-size: 18px;
    }
    .course-list ul {
      list-style: none;
      padding-left: 0;
      margin: 0;
    }
    .course-list li {
      margin-bottom: 10px;
      background: #ffffff;
      padding: 10px;
      border-left: 5px solid #aed581;
      border-radius: 6px;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
  </style>
</head>
<body>
  <div class="header">
    畢業門檻狀態
    <div class="header-buttons">
      <button onclick="location.href='login.php'">登出</button>
      <button onclick="location.href='course.php'">輔助選課</button>
      <button onclick="location.href='Downloads.html'">下載手冊</button>
    </div>
  </div>

  <div class="container">
    <div class="info">
      <div>👤 姓名：<?= htmlspecialchars($_SESSION['name']) ?></div>
      <div>🎓 學號：<?= htmlspecialchars($_SESSION['student_id']) ?></div>
      <div>🏫 系所：<?= htmlspecialchars($_SESSION['department']) ?></div>
      <div>🧩 組別：<?= htmlspecialchars($_SESSION['user_group']) ?></div>
    </div>

    <div class="input-section">
      <p>📌 請選擇課程類型並輸入您選修過的課號</p>
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
        <input type="text" id="course_code" placeholder="輸入已修過的課號(例如GE111只需要輸入111)">
        <button onclick="addCourse()">確認</button>
      </div>
    </div>

    <div class="credit">
      已修學分數：<?= $total_credits ?> 學分<br>
      畢業門檻需修滿 128 學分
      通識教育32學分 院跟系則看各自規定
    </div>

    <div class="courses">
      <div class="course-list">
        <h2>✅ 已完成課程</h2>
        <ul>
          <?php foreach ($completed_courses as $row): ?>
            <li>
              <strong><?= htmlspecialchars($row['course_name']) ?></strong>（<?= htmlspecialchars($row['course_code']) ?>）<br>
              學分：<?= $row['credits'] ?>｜完成學期：<?= htmlspecialchars($row['semester']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="course-list">
        <h2>📋 未完成課程</h2>
        <ul>
          <?php while ($row = $missing_result->fetch_assoc()): ?>
            <li>
              <strong><?= htmlspecialchars($row['課程名稱']) ?></strong>（<?= htmlspecialchars($row['課程代碼']) ?>）｜學分：<?= $row['學分'] ?>
            </li>
          <?php endwhile; ?>
        </ul>
      </div>
    </div>
  </div>

  <script>
    function addCourse() {
      const code = document.getElementById("course_code").value;
      const type = document.getElementById("course_type").value;

      fetch("add_course.php", {
        method: "POST",
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `course_code=${code}&course_type=${type}`
      }).then(() => location.reload());
    }
  </script>
</body>
</html>

<?php
$stmt_user->close();
$stmt_courses->close();
$stmt_missing->close();
$conn->close();
?>
