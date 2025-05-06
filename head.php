<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php

// Bắt đầu session
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];

    // Lấy thông tin người dùng từ cơ sở dữ liệu bằng PDO
    try {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra xem có lấy được thông tin user không
        if (!$user || empty($user)) {
            // User không tồn tại, tự động logout
            session_unset(); // Xóa tất cả biến session
            session_destroy(); // Hủy session
            echo "<div class='header-error'>";
            echo "Người dùng không tồn tại hoặc thông tin bị lỗi! Vui lòng đăng nhập lại.";
            echo "<br><br>";
            echo "<button class='header-btn' onclick='location.href=\"login.php\"'>Đăng nhập</button>";
            echo "</div>";
            echo "<script>setTimeout(function() { window.location.href = 'login.php'; }, 3000);</script>";
            exit();
        }
    } catch (PDOException $e) {
        // Nếu có lỗi truy vấn, coi như không lấy được thông tin user
        session_unset(); // Xóa tất cả biến session
        session_destroy(); // Hủy session
        echo "<div class='header-error'>";
        echo "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage() . "<br>";
        echo "Vui lòng đăng nhập lại.";
        echo "<br><br>";
        echo "<button class='header-btn' onclick='location.href=\"login.php\"'>Đăng nhập</button>";
        echo "</div>";
        echo "<script>setTimeout(function() { window.location.href = 'login.php'; }, 3000);</script>";
        exit();
    }

    // Hiển thị nội dung cho người đã đăng nhập
    echo "<div class='header-container'>";
    echo "<div class='header-left'>";
    echo "<button class='header-btn' onclick='location.href=\"index.php\"'>Trang chủ</button>";
    echo "<button class='header-btn' onclick='location.href=\"datcuoc.php\"'>Đặt cược</button>";
    echo "<button class='header-btn' onclick='location.href=\"account.php\"'>Tài khoản</button>";
         echo "<button class='header-btn' onclick='location.href=\"bxh.php\"'>Xếp hạng người chơi</button>";
      echo "<button class='header-btn' onclick='location.href=\"history.php\"'>Toàn bộ lịch sử chơi</button>";
                
    echo "</div>";

    // Thông tin người dùng
    echo "<div class='header-right'>";
    echo "<div class='header-user-info'>";
    echo "<span class='header-username'>Xin chào, <span class='header-username-bold'>" . htmlspecialchars($username) . "</span></span>";
    echo "<button class='header-btn header-logout-btn' onclick='logout()'>Logout</button>";
    echo "</div>";

    $formattedMoney = number_format($user['money'], 0, ',', '.');
    echo "<div class='header-stats'>";
    echo "<span class='header-money'>Số tiền: <span class='header-money-amount'>" . htmlspecialchars($formattedMoney) . "</span> <span class='header-currency'>Vnđ</span></span>";
    echo "<span class='header-plays'>Lượt chơi: <span class='header-plays-count'>" . htmlspecialchars($user['luot_choi']) . "</span></span>";
    echo "<span class='header-wins'>Lần thắng: <span class='header-wins-count'>" . htmlspecialchars($user['win']) . "</span></span>";
    echo "</div>";
    echo "</div>";
    echo "</div>";
} else {
    // Hiển thị nội dung cho người chưa đăng nhập
    echo "<div class='header-container'>";
    echo "<div class='header-left'>";
    echo "<button class='header-btn' onclick='location.href=\"index.php\"'>Trang chủ</button>";
     echo "<button class='header-btn' onclick='location.href=\"bxh.php\"'>Xếp hạng người chơi</button>";
      echo "<button class='header-btn' onclick='location.href=\"history.php\"'>Toàn bộ lịch sử chơi</button>";
    echo "</div>";

    echo "<div class='header-right header-right-guest'>";
    echo "<button class='header-btn' onclick='location.href=\"login.php\"'>Đăng nhập</button>";
    echo "<button class='header-btn' onclick='location.href=\"register.php\"'>Đăng ký</button>";
    echo "</div>";
    echo "</div>";
}
?>

<style>
    /* Container chính của header */
    .header-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 20px;
        background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }

    /* Phần bên trái (nút điều hướng) */
    .header-left {
        display: flex;
        gap: 10px;
    }

    /* Phần bên phải (thông tin user hoặc nút đăng nhập/đăng ký) */
    .header-right {
        display: flex;
        flex-direction: column;
        align-items: flex-end;
        gap: 5px;
    }

    /* Khi chưa đăng nhập, căn ngang nút Đăng nhập và Đăng ký */
    .header-right-guest {
        flex-direction: row;
        gap: 10px;
        align-items: center;
    }

    /* Thông tin user */
    .header-user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-username {
        font-size: 20px;
    }

    .header-username-bold {
        font-weight: bold;
        color: #006400; /* Màu xanh đậm */
    }

    /* Thông tin thống kê (số tiền, lượt chơi, lần thắng) */
    .header-stats {
        display: flex;
        gap: 20px;
        font-size: 16px;
    }

    .header-money-amount {
        color: #e74c3c; /* Màu đỏ */
        font-weight: bold;
    }

    .header-currency {
        color: #27ae60; /* Màu xanh lá */
    }

    .header-plays-count {
        color: #2980b9; /* Màu xanh dương */
        font-weight: bold;
    }

    .header-wins-count {
        color: #8e44ad; /* Màu tím */
        font-weight: bold;
    }

    /* Nút */
    .header-btn {
        background: rgba(52, 152, 219, 0.9);
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 16px;
        text-decoration: none;
    }

    .header-btn:hover {
        background: rgba(52, 152, 219, 1);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    }

    /* Thông báo lỗi */
    .header-error {
        text-align: center;
        margin-top: 20px;
        font-family: 'Arial', sans-serif;
        color: #333;
    }
</style>

<script>
    function logout() {
        if (confirm("Bạn chắc chắn muốn đăng xuất?")) {
            window.location.href = 'logout.php';
        }
    }
</script>
