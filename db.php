<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "school_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => '資料庫連接失敗: ' . $conn->connect_error], JSON_UNESCAPED_UNICODE);
    exit();
}
?>