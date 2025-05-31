<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>忘記密碼 - 佛光大學選課推薦系統</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Noto Sans TC', sans-serif;
            background: linear-gradient(to right, #e0f7fa, #f8f9fa);
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .form-container {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #00796b;
        }

        form input {
            width: 100%;
            padding: 12px;
            margin: 10px 0;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 16px;
        }

        form button {
            width: 100%;
            padding: 12px;
            background-color: #00796b;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        form button:hover {
            background-color: #004d40;
        }

        .link {
            display: block;
            text-align: center;
            margin-top: 15px;
        }

        .link a {
            text-decoration: none;
            color: #00796b;
        }

        .link a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .form-container {
                padding: 30px 20px;
            }

            h2 {
                font-size: 20px;
            }

            form input, form button {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>

<div class="form-container">
    <h2>🔒 忘記密碼</h2>
    <form action="reset_password.php" method="POST">
        <input type="email" name="email" placeholder="請輸入註冊時的電子郵件" required>
        <button type="submit">寄送重設連結</button>
    </form>
    <div class="link">
        <a href="login.php">← 回到登入頁</a>
    </div>
</div>

</body>
</html>
