<?php
$servername = "fgumysqlserver.mysql.database.azure.com";
$username = "fguadmin";
$password = "brenden@0129";
$dbname = "school_db";

// 建立 SSL 加密連線
$conn = mysqli_init();
mysqli_ssl_set($conn, NULL, NULL, NULL, NULL, NULL);  // 使用預設憑證
mysqli_real_connect($conn, $servername, $username, $password, $dbname, 3306, NULL, MYSQLI_CLIENT_SSL);
