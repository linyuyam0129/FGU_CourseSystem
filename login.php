<?php session_start(); ?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>佛光大學選課輔助系統</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body, html {
      height: 100%;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(to right, #e0f2f1, #f1f8e9);
    }

    .container {
      display: flex;
      height: 100vh;
    }

    .image-section {
      width: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      background-color: #e3f2fd;
    }

    .image-section img {
      width: 90%;
      max-width: 500px;
      border-radius: 20px;
    }

    .form-section {
      width: 50%;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 30px;
    }

    .login-form {
      background-color: #ffffff;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      width: 100%;
      max-width: 420px;
      position: relative;
      animation: fadeIn 1s ease;
    }

    .login-form h2 {
      text-align: center;
      margin-bottom: 25px;
      color: #2e7d32;
    }

    .login-form input {
      width: 100%;
      padding: 12px;
      margin-bottom: 18px;
      border-radius: 10px;
      border: 1px solid #ccc;
      transition: border-color 0.3s;
    }

    .login-form input:focus {
      border-color: #66bb6a;
      outline: none;
    }

    .login-form button {
      width: 100%;
      padding: 12px;
      background-color: #43a047;
      color: white;
      border: none;
      border-radius: 10px;
      font-size: 16px;
      cursor: pointer;
      transition: background-color 0.3s, transform 0.1s;
    }

    .login-form button:hover {
      background-color: #388e3c;
    }

    .login-form button:active {
      transform: scale(0.98);
    }

    .error-message {
      background-color: #ffcdd2;
      color: #c62828;
      padding: 10px;
      border-radius: 8px;
      text-align: center;
      margin-top: 15px;
      margin-bottom: 5px;
      display: none;
      animation: slideDown 0.4s ease forwards;
    }

    .info-links {
      text-align: center;
      margin-top: 15px;
      font-size: 14px;
    }

    .info-links a {
      color: #388e3c;
      text-decoration: none;
      margin: 0 5px;
    }

    .info-links a:hover {
      text-decoration: underline;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-10px); }
      to   { opacity: 1; transform: translateY(0); display: block; }
    }

    @media (max-width: 768px) {
      .container {
        flex-direction: column;
      }

      .image-section {
        display: none;
      }

      .form-section {
        width: 100%;
      }
    }
  </style>
</head>
<body>

<div class="container">
  <div class="image-section">
    <img src="圖片/login.png" alt="Login Image">
  </div>

  <div class="form-section">
  <form class="login-form" id="loginForm" action="login_process.php" method="POST">
    <h1 style="font-size: 28px; color: #00796b; margin-bottom: 10px; text-align: center;">佛光大學選課推薦系統</h1>
    <h2>登入系統</h2>
    <input type="text" id="username" name="student_id" placeholder="請輸入學號" required>
    <input type="password" id="password" name="password" placeholder="請輸入密碼" required>
    
    <button type="submit">登入</button>

    <!-- ✅ 錯誤訊息放在按鈕下方 -->
    <?php if (isset($_SESSION['login_error'])): ?>
      <div class="error-message" id="errorMessage" style="display: block;">
        <?php echo $_SESSION['login_error']; unset($_SESSION['login_error']); ?>
      </div>
    <?php else: ?>
      <div class="error-message" id="errorMessage"></div>
    <?php endif; ?>

    <div class="info-links">
      <a href="forgot_password.php">忘記密碼</a> |
      <a href="register.php">註冊新帳號</a>
    </div>
  </form>
</div>


<script>
  const loginForm = document.getElementById('loginForm');
  const username = document.getElementById('username');
  const password = document.getElementById('password');
  const errorMessage = document.getElementById('errorMessage');

  loginForm.addEventListener('submit', function(e) {
    if (username.value.trim() === '' || password.value.trim() === '') {
      e.preventDefault();
      errorMessage.textContent = '學號和密碼皆為必填！';
      errorMessage.style.display = 'block';
    }
  });
</script>

</body>
</html>
