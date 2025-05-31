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
<title>佛光大學選課系統</title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC&display=swap" rel="stylesheet">
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
<style>
        body {
            font-family: 'Noto Sans TC', sans-serif;
            background: linear-gradient(to right, #e8f5e9, #fff);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 960px;
            margin: auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #388e3c;
        }
        .info {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            background-color: #f1f8e9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .info div {
            flex: 1 1 45%;
            margin-bottom: 10px;
            font-size: 18px;
        }
        .button-group {
            text-align: right;
            margin-bottom: 20px;
        }
        .button-group button {
            background-color: #ffffff;
            color: #388e3c;
            border: 2px solid #388e3c;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            margin-left: 10px;
            cursor: pointer;
            transition: background-color 0.3s, color 0.3s;
        }
        .button-group button:hover {
            background-color: #388e3c;
            color: white;
}
        .input-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #fffde7;
            border-left: 6px solid #fbc02d;
            border-radius: 6px;
        }
        .input-section p {
            font-weight: bold;
            margin-bottom: 12px;
        }
        .input-group {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .input-group select,
        .input-group input[type="text"],
        .input-group button {
            padding: 10px;
            font-size: 16px;
            border-radius: 5px;
            border: 1px solid #ccc;
        }
        .input-group button {
            background-color: #43a047;
            color: white;
            border: none;
            cursor: pointer;
        }
        .credit {
            background: #f1f8e9;
            text-align: center;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 6px;
            margin-bottom: 25px;
            color: #d84315;
        }
        .courses {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .course-list {
            flex: 1 1 45%;
        }
        .course-list h2 {
            border-bottom: 2px solid #ccc;
            padding-bottom: 5px;
        }
        .course-list ul {
            list-style: none;
            padding-left: 20px;
        }
        .course-list li {
            margin-bottom: 10px;
        }
        .course-name {
            font-weight: bold;
        }
        .course-credits,
        .course-status {
            font-size: 14px;
            color: #666;
        }
</style>
</head>
<body>
<div class="container">
<div class="button-group">
    佛光大學選課系統
    <button onclick="location.href='login.php'">登出</button>
    <button onclick="location.href='course.php'">輔助選課</button>
    <button onclick="location.href='Downloads.html'">下載手冊</button>
</div>

<h1>畢業門檻狀態</h1>

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
            <option value="GE">通識課程GE</option>
            <option value="CS">資應系CS</option>
            <option value="MA">管院MA</option>
            <!-- 可再擴充其他代碼 -->
        </select>
        <input type="text" id="course_code" placeholder="輸入已修過的課號">
        <button onclick="addCourse()">確認</button>
    </div>
</div>

<div class="credit">
    已修學分數：<span id="credits"><?= $total_credits ?></span> 學分<br>
    <p>畢業門檻需修滿 128 學分</p>
</div>

<div class="courses">
    <div class="course-list">
        <h2>✅ 已完成課程</h2>
        <ul>
        <?php foreach ($completed_courses as $row): ?>
            <li>
                <span class="course-name"><?= htmlspecialchars($row['course_name']) ?></span>（<?= htmlspecialchars($row['course_code']) ?>）<br>
                <span class="course-credits">學分：<?= htmlspecialchars($row['credits']) ?></span>
                <span class="course-status">完成學期：<?= htmlspecialchars($row['semester']) ?></span>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>

    <div class="course-list">
        <h2>📋 未完成課程</h2>
        <ul>
        <?php while ($row = $missing_result->fetch_assoc()): ?>
            <li>
                <span class="course-name"><?= htmlspecialchars($row['課程名稱']) ?></span>（<?= htmlspecialchars($row['課程代碼']) ?>）
                <span class="course-credits">｜學分：<?= htmlspecialchars($row['學分']) ?></span>
            </li>
        <?php endwhile; ?>
        </ul>
    </div>
</div>
</div>
</body>
</html>

<?php
$stmt_user->close();
$stmt_courses->close();
$stmt_missing->close();
$conn->close();
?>
