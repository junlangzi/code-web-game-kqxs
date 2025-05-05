<?php
session_start(); // Bắt đầu phiên làm việc

// Hủy tất cả các biến phiên
$_SESSION = array();

// Nếu bạn muốn hủy cookie phiên, hãy sử dụng đoạn mã sau:
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Cuối cùng, hủy phiên
session_destroy();

// Chuyển hướng về trang đăng nhập
header("Location: login.php");
exit;
?>
