<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php
session_start();

// Kiểm tra xem người dùng đã đăng nhập chưa
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$pageTitle = "Quản lý tài khoản"; // Tiêu đề trang
$success = '';
$error = '';

// Lấy thông tin người dùng
try {
    $stmt = $conn->prepare("SELECT username, money, luot_choi, win FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}

// Xử lý thay đổi mật khẩu
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Lấy mật khẩu hiện tại từ cơ sở dữ liệu
    try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Kiểm tra mật khẩu hiện tại
        if (!password_verify($current_password, $user_data['password'])) {
            $error = "Mật khẩu hiện tại không đúng!";
        } elseif (strlen($new_password) < 6 || strlen($new_password) > 15) {
            $error = "Mật khẩu mới phải từ 6 đến 15 ký tự!";
        } elseif ($new_password !== $confirm_password) {
            $error = "Mật khẩu mới và xác nhận mật khẩu không khớp!";
        } else {
            // Cập nhật mật khẩu mới
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = :password WHERE username = :username");
            $stmt->bindParam(':password', $new_password_hash, PDO::PARAM_STR);
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $success = "Thay đổi mật khẩu thành công!";
            } else {
                $error = "Có lỗi xảy ra khi thay đổi mật khẩu!";
            }
        }
    } catch (PDOException $e) {
        $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
    }
}

// Lấy lịch sử giao dịch từ bảng cuoc, tối đa 15 dòng, bao gồm cột status
try {
    $stmt = $conn->prepare("SELECT id, time, date, money, ketqua, status FROM cuoc WHERE user = :username ORDER BY time DESC LIMIT 15");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi truy vấn lịch sử giao dịch: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<?php include 'head.php'; ?>
<title>Quản lý tài khoản</title>
<body>
    <div class="account-container">
        <div class="account-two-columns">
            <!-- Bên trái: Quản lý tài khoản -->
            <div class="account-left">
                <div class="account-bang-trang-thai">
                    <h1 class="account-tieu-de-chinh">Quản lý tài khoản</h1>

                    <!-- Thông báo -->
                    <?php if ($success || $error) { ?>
                        <div class="account-thong-bao-noi <?php echo $success ? 'account-thanh-cong' : 'account-loi'; ?>">
                            <?php echo $success ?: $error; ?>
                        </div>
                    <?php } ?>

                    <!-- Thông tin tài khoản -->
                    <div class="account-thong-tin-tai-khoan">
                        <p><span class="account-label">Username:</span> <span class="account-value"><?php echo htmlspecialchars($user['username']); ?></span></p>
                        <p><span class="account-label">Số dư:</span> <span class="account-value"><?php echo number_format($user['money'], 0, ',', '.') . " Vnđ"; ?></span></p>
                        <p><span class="account-label">Lượt chơi / Thắng:</span> 
                            <span class="account-value">
                                Lượt chơi: <span class="account-luot-choi"><?php echo htmlspecialchars($user['luot_choi']); ?></span> / 
                                Lượt thắng: <span class="account-luot-thang"><?php echo htmlspecialchars($user['win']); ?></span>
                            </span>
                        </p>
                    </div>

                    <!-- Form thay đổi mật khẩu -->
                    <form action="" method="post">
                        <h3 class="account-form-title">Thay đổi mật khẩu</h3>
                        <div class="account-form-group">
                            <label for="current_password">Mật khẩu hiện tại:</label>
                            <input type="password" id="current_password" name="current_password" required class="account-truong-nhap">
                        </div>
                        <div class="account-form-group">
                            <label for="new_password">Mật khẩu mới:</label>
                            <input type="password" id="new_password" name="new_password" required class="account-truong-nhap">
                        </div>
                        <div class="account-form-group">
                            <label for="confirm_password">Xác nhận mật khẩu mới:</label>
                            <input type="password" id="confirm_password" name="confirm_password" required class="account-truong-nhap">
                        </div>
                        <button class="account-nut" type="submit" name="change_password">Thay đổi mật khẩu</button>
                    </form>
                </div>
            </div>

            <!-- Bên phải: Lịch sử giao dịch -->
            <div class="account-right">
                <div class="account-transaction-history">
                    <h2 class="account-transaction-title">Lịch sử giao dịch</h2>
                    <table class="account-transaction-table">
                        <tr>
                            <th>ID giao dịch</th>
                            <th>Giờ giao dịch</th>
                            <th>Kỳ quay thưởng</th>
                            <th>Tiền cược</th>
                            <th>Trúng thưởng</th>
                        </tr>
                        <?php if (!empty($transactions)): ?>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                    <td><?php echo (new DateTime($transaction['time']))->format('d-m-Y H:i:s'); ?></td>
                                    <td><?php echo (new DateTime($transaction['date']))->format('d-m-Y'); ?></td>
                                    <td>
                                        <?php
                                        if ($transaction['status'] === 'CANCELLED') {
                                            echo "<span class='account-money-loss'><del>-" . number_format($transaction['money'], 0, ',', '.') . "</del></span>";
                                        } else {
                                            echo "<span class='account-money-loss'>-" . number_format($transaction['money'], 0, ',', '.') . "</span>";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Kiểm tra nếu status chứa "WIN" (không phân biệt hoa/thường)
                                        if (stripos($transaction['status'], 'WIN') !== false) {
                                            if (is_numeric($transaction['ketqua']) && $transaction['ketqua'] >= 0) {
                                                $winAmount = $transaction['ketqua'] * 49;
                                                echo "<span class='account-money-win'>+" . number_format($winAmount, 0, ',', '.') . "</span>";
                                            } else {
                                                echo "<span class='account-status-error'>Lỗi dữ liệu</span>";
                                            }
                                        } elseif ($transaction['status'] === 'NEW') {
                                            echo "<span class='account-status-new'>Mới</span>";
                                        } elseif ($transaction['status'] === 'CANCELLED') {
                                            echo "<span class='account-status-cancelled'>Hủy</span>";
                                        } elseif ($transaction['status'] === 'LOSE') {
                                            echo "<span class='account-status-lose'>Thua</span>";
                                        } else {
                                            echo "<span class='account-status-unknown'>Không xác định</span>";
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">Chưa có giao dịch nào.</td></tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
        }

        .account-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px;
        }

        .account-two-columns {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .account-left, .account-right {
            width: 48%;
        }

        .account-bang-trang-thai {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }
        .account-bang-trang-thai:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .account-tieu-de-chinh {
            text-align: center;
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 30px;
            background: linear-gradient(to right, #4e54c8 0%, #8f94fb 30%, #4e54c8 60%, #8f94fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 200% auto;
            animation: doiMauChu 3s linear infinite;
        }
        @keyframes doiMauChu {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        .account-nut {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            padding: 12px 40px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: block;
            width: 200px;
            margin: 20px auto 0;
            font-size: 1.1em;
            font-weight: 600;
        }
        .account-nut:hover {
            background: linear-gradient(135deg, #2980b9 0%, #1f6391 100%);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.25);
        }

        .account-truong-nhap {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #ccc;
            margin-top: 5px;
            box-sizing: border-box;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        .account-truong-nhap:focus {
            border-color: #3498db;
            outline: none;
            box-shadow: 0 0 5px rgba(52, 152, 219, 0.5);
        }

        .account-thong-bao-noi {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 1.1em;
            text-align: center;
            opacity: 0;
            animation: fadeInOut 3s ease-in-out forwards;
        }
        .account-thanh-cong {
            background: #c6efce;
            color: #3e8e41;
        }
        .account工商-loi {
            background: #f2dede;
            color: #a94442;
        }
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            20% { opacity: 1; transform: translateX(-50%) translateY(0); }
            80% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }

        .account-thong-tin-tai-khoan {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            border: 1px solid #e0e0e0;
        }
        .account-thong-tin-tai-khoan p {
            margin: 10px 0;
            font-size: 1.05em;
        }
        .account-label {
            font-weight: 600;
            color: #2c3e50;
            display: inline-block;
            width: 140px;
        }
        .account-value {
            color: #34495e;
        }
        .account-luot-choi {
            color: #e74c3c;
            font-weight: bold;
        }
        .account-luot-thang {
            color: #27ae60;
            font-weight: bold;
        }

        .account-form-title {
            font-size: 1.4em;
            color: #2c3e50;
            margin-bottom: 15px;
            text-align: center;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .account-form-group {
            margin-bottom: 20px;
        }
        .account-form-group label {
            display: block;
            font-size: 1em;
            color: #34495e;
            margin-bottom: 5px;
            font-weight: 500;
        }

        .account-transaction-history {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .account-transaction-title {
            font-size: 1.8em;
            color: #2c3e50;
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .account-transaction-table {
            width: 100%;
            border-collapse: collapse;
        }
        .account-transaction-table th, .account-transaction-table td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        .account-transaction-table th {
            background: #f4f4f4;
            color: #333;
        }
        .account-money-loss {
            color: #e74c3c;
            font-weight: bold;
        }
        .account-money-win {
            color: #27ae60;
            font-weight: bold;
        }
        .account-status-new {
            color: #f1c40f;
            font-weight: bold;
        }
        .account-status-cancelled {
            color: #e74c3c;
            font-weight: bold;
        }
        .account-status-lose {
            color: #7f8c8d;
            font-weight: bold;
        }
        .account-status-unknown {
            color: #95a5a6;
            font-weight: bold;
        }
        .account-status-error {
            color: #e74c3c;
            font-weight: bold;
        }
    </style>
</body>
</html>