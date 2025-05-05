<?php
$servername = "localhost"; // Hoặc địa chỉ IP của server database
$username = "username"; // Thay bằng username database của bạn
$password = "password"; // Thay bằng password database của bạn
$dbname = "dbname"; // Thay bằng tên database của bạn

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    //echo "Kết nối thành công"; // Dùng cho debug
} catch(PDOException $e) {
    echo "Kết nối thất bại: " . $e->getMessage();
    die(); // Dừng script nếu không kết nối được database
}
?>