<?php
// Thông tin kết nối CSDL
$servername = "localhost";   // Máy chủ CSDL, thường là localhost
$dbuser     = "root";        // Tên người dùng MySQL (mặc định là root)
$dbpass     = "";            // Mật khẩu MySQL (nếu có thì điền vào đây)
$dbname     = "parking_system"; // Tên CSDL bạn đang dùng

// Kết nối tới MySQL
$conn = new mysqli($servername, $dbuser, $dbpass, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die(" Kết nối CSDL thất bại: " . $conn->connect_error);
}

// Đặt charset UTF-8 để tránh lỗi tiếng Việt
$conn->set_charset("utf8mb4");
?>
