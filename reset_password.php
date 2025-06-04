<?php
require 'db.php';  // 引入資料庫連接檔案

$email = $_POST['email'];

// 檢查電子郵件是否存在
$sql = "SELECT id FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 1) {
    // 生成重設碼
    $reset_code = bin2hex(random_bytes(16));

    // 儲存重設碼到資料庫
    $sql_update = "UPDATE users SET reset_code = ? WHERE email = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ss", $reset_code, $email);

    if ($stmt_update->execute()) {
        // 顯示成功訊息並跳轉
        echo "<script>
            alert('密碼重設連結已寄出，請至信箱查收！即將跳轉至登入頁面。');
            setTimeout(function() {
                window.location.href = 'login.php';
            }, 1500);
        </script>";
    } else {
        echo "<script>alert('更新重設碼時發生錯誤，請稍後再試'); window.history.back();</script>";
    }
} else {
    echo "<script>alert('此電子郵件未註冊！'); window.history.back();</script>";
}

$stmt->close();
$conn->close();
?>
