<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>佛光大學選課輔助系統 - 註冊</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body, html {
      height: 100%;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(to right, #e0f7fa, #f8f9fa);
      color: #333;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .form-section {
      width: 100%;
      max-width: 500px;
      padding: 40px;
      background-color: #ffffff;
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      animation: fadeIn 1s ease;
    }

    .register-form h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #33691e;
    }

    .register-form input,
    .register-form select {
      width: 100%;
      padding: 12px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 10px;
      font-size: 16px;
      transition: border-color 0.3s;
    }

    .register-form input:focus,
    .register-form select:focus {
      border-color: #8bc34a;
      outline: none;
    }

    .register-form button {
      width: 100%;
      padding: 12px;
      background-color: #558b2f;
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.1s ease;
    }

    .register-form button:hover {
      background-color: #33691e;
    }

    .register-form button:active {
      transform: scale(0.98);
    }

    .error-message {
      background-color: #ffcdd2;
      color: #c62828;
      padding: 10px;
      border-radius: 8px;
      text-align: center;
      display: none;
    }

    .login-link {
      text-align: center;
      margin-top: 15px;
      font-size: 14px;
    }

    .login-link a {
      color: #388e3c;
      text-decoration: none;
    }

    .login-link a:hover {
      text-decoration: underline;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body>

<div class="form-section">
  <form class="register-form" action="register_process.php" method="POST" onsubmit="return validatePassword()">
    <h2>註冊帳號</h2>
    <input type="text" name="student_id" placeholder="學號" required>
    <input type="text" name="name" placeholder="姓名" required>
    <select id="college" name="college" required onchange="updateDepartments()">
      <option value="">選擇學院</option>
      <option value="CT">創科院</option>
      <option value="CB">佛教學院</option>
      <option value="HS">樂活院</option>
      <option value="MA">管理學院</option>
      <option value="SO">社科院</option>
      <option value="HC">人文學院</option>
    </select>
    <select id="department" name="department" required disabled>
      <option value="">選擇系所</option>
    </select>
    <select id="group" name="group" required disabled>
      <option value="">選擇組別</option>
    </select>
    <input type="password" name="password" id="password" placeholder="密碼" required>
    <input type="password" id="confirm-password" placeholder="確認密碼" required>
    <input type="email" name="email" placeholder="電子郵件" required>
    <button type="submit">註冊</button>
    <p class="error-message" id="error-message">密碼與確認密碼不符！</p>
    <div class="login-link">
      <p>已經有帳戶？ <a href="login.php">登入</a></p>
    </div>
  </form>
</div>

<script>
  function validatePassword() {
    const password = document.getElementById("password").value;
    const confirmPassword = document.getElementById("confirm-password").value;
    const errorMessage = document.getElementById("error-message");
    if (password !== confirmPassword) {
      errorMessage.style.display = "block";
      return false;
    } else {
      errorMessage.style.display = "none";
      return true;
    }
  }

  function updateDepartments() {
    const college = document.getElementById("college").value;
    const department = document.getElementById("department");
    const group = document.getElementById("group");

    department.innerHTML = '<option value="">選擇系所</option>';
    group.innerHTML = '<option value="">選擇組別</option>';
    group.disabled = true;

    const departments = {
      "CT": ["資應系", "傳播系", "產媒系", "建築系", "文資系"],
      "CB": ["佛教系"],
      "HS": ["樂活系", "蔬食系"],
      "MA": ["管理系", "經濟系", "運健系"],
      "SO": ["公事系", "社會系", "心理系"],
      "HC": ["中文系", "外文系", "歷史系"]
    };

    if (departments[college]) {
      department.disabled = false;
      departments[college].forEach(dep => {
        const option = document.createElement("option");
        option.value = dep;
        option.text = dep;
        department.appendChild(option);
      });
    } else {
      department.disabled = true;
    }
  }

  function updateGroups() {
    const department = document.getElementById("department").value;
    const group = document.getElementById("group");

    group.innerHTML = '<option value="">選擇組別</option>';

    const groups = {
      "資應系": ["系統組", "遊戲組", "動畫組"],
      "傳播系": ["流音組", "廣公組", "數媒組"],
      "產媒系": ["產品組", "媒體組"],
      "建築系": ["建築組"],
      "文資系": ["文資組"],
      "佛教系": ["經典組", "應用組"],
      "樂活系": ["永續組", "健康組"],
      "蔬食系": ["素食營養組", "健康餐飲組"],
      "管理系": ["健管組", "經管組", "休閒遊憩組"],
      "經濟系": ["財經組", "國際組"],
      "運健系": ["運動健康組", "體育管理組"],
      "公事系": ["國際組", "行政組"],
      "社會系": ["社會組"],
      "心理系": ["臨床組", "教育組"],
      "中文系": ["中文組"],
      "外文系": ["英韓組", "英日組", "英法組"],
      "歷史系": ["A組", "B組"]
    };

    if (groups[department]) {
      group.disabled = false;
      groups[department].forEach(grp => {
        const option = document.createElement("option");
        option.value = grp;
        option.text = grp;
        group.appendChild(option);
      });
    } else {
      group.disabled = true;
    }
  }

  document.getElementById("department").addEventListener("change", updateGroups);
</script>

</body>
</html>
