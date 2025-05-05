<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php

session_start(); // Bắt đầu session

$usernameError = '';
$passwordError = '';
$success = '';
$alreadyLoggedIn = '';
$show_form = true; // Biến để kiểm soát hiển thị form

// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (isset($_SESSION['username'])) {
    header("location: index.php"); // Chuyển hướng ngay nếu đã đăng nhập
    exit;
} elseif (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    $remember = isset($_POST['remember']); // Kiểm tra xem checkbox "Lưu mật khẩu" được chọn không

    // Kiểm tra username bằng PDO
    try {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $usernameError = "Tên người dùng không tồn tại!";
        } else {
            if (!password_verify($password, $user['password'])) {
                $passwordError = "Mật khẩu không chính xác!";
            } else {
                $success = "Đăng nhập thành công! Sẽ chuyển về trang chủ sau 2s";
                $_SESSION['username'] = $username;
                $_SESSION['type'] = $user['type'];
                $show_form = false; // Ẩn form
            
                // Nếu người dùng chọn "Lưu mật khẩu", lưu vào cookie
                if ($remember) {
                    setcookie('login_username', $username, time() + (30 * 24 * 60 * 60), "/"); // Lưu 30 ngày
                    setcookie('login_password', $password, time() + (30 * 24 * 60 * 60), "/"); // Lưu 30 ngày
                } else {
                    // Nếu không chọn "Lưu mật khẩu", xóa cookie nếu có
                    setcookie('login_username', '', time() - 3600, "/");
                    setcookie('login_password', '', time() - 3600, "/");
                }
            }
        }
    } catch (PDOException $e) {
        echo "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
        exit();
    }
}

// Lấy thông tin từ cookie nếu có
$saved_username = isset($_COOKIE['login_username']) ? htmlspecialchars($_COOKIE['login_username']) : '';
$saved_password = isset($_COOKIE['login_password']) ? htmlspecialchars($_COOKIE['login_password']) : '';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập</title>
    <style>
        /* CSS (Copy từ file trước) */
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            padding: 40px;
            color: #2c3e50;
            line-height: 1.6;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .login-container {
            background: #ffffff;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
            width: 400px;
            text-align: center;
        }

        h2 {
            color: #1e90ff;
            font-size: 2.5em;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        label {
            font-weight: 600;
            color: #34495e;
            margin-bottom: 10px;
            display: block;
            font-size: 1.1em;
        }

        input[type="text"],
        input[type="password"] {
            width: 90%;
            padding: 12px 15px;
            border: 2px solid #ecf0f1;
            border-radius: 10px;
            font-size: 1em;
            background: #f9f9f9;
            transition: border-color 0.3s ease, background 0.3s ease;
            margin: 0 auto;
            display: block;
        }

        input[type="text"]:focus,
        input[type="password"]:focus {
            border-color: #3498db;
            background: #ffffff;
            outline: none;
        }

        .form-actions {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
        }

        .form-actions input[type="submit"] {
            background: #3498db;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            text-transform: uppercase;
            transition: background 0.3s ease, transform 0.2s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .form-actions input[type="submit"]:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .register-link {
            color: #3498db;
            font-size: 1.1em;
            font-weight: 600;
            text-decoration: none;
        }

        .register-link:hover {
            text-decoration: underline;
        }

        .error {
            color: #e74c3c;
            margin-top: 15px;
            font-size: 1.1em;
            font-weight: 500;
            text-align: center;
        }

        .success {
            color: #27ae60;
            margin-top: 30px;
            font-size: 1.1em;
            font-weight: 500;
            text-align: center;
        }

        .remember-checkbox {
            margin-bottom: 20px;
            text-align: left;
            display: flex;
            align-items: center;
        }

        .remember-checkbox input {
            margin-right: 10px;
        }

        .remember-checkbox label {
            font-weight: 500;
            color: #34495e;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <?php if ($show_form): ?>
            <h2 class="tieu-de-chinh">Đăng nhập</h2>
        <?php endif; ?>

        <?php
        if ($alreadyLoggedIn) {
            echo '<div class="success">' . $alreadyLoggedIn . '</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "index.php";
                }, 2000);
            </script>';
        } elseif ($success) {
            echo '<div class="success">' . $success . '</div>';
            echo '<script>
                setTimeout(function() {
                    window.location.href = "index.php";
                }, 2000);
            </script>';
        } elseif ($usernameError) {
            echo '<div class="error">' . $usernameError . '</div>';
        } elseif ($passwordError) {
            echo '<div class="error">' . $passwordError . '</div>';
        }
        ?>

        <?php if ($show_form): ?>
            <form action="" method="post">
                <div class="form-group">
                    <label>Tên người dùng:</label>
                    <input type="text" name="username" value="<?php echo $saved_username; ?>" required>
                </div>
                <div class="form-group">
                    <label>Mật khẩu:</label>
                    <input type="password" name="password" value="<?php echo $saved_password; ?>" required>
                </div>
                <div class="remember-checkbox">
                    <input type="checkbox" id="remember" name="remember" <?php echo $saved_username ? 'checked' : ''; ?>>
                    <label for="remember">Lưu mật khẩu</label>
                </div>
                <div class="form-actions">
                    <input type="submit" value="Đăng nhập" name="submit">
                </div>
            </form>
            <p>Bạn chưa có tài khoản, hãy <a href="register.php" class="register-link">Đăng ký</a></p>
                        <p><a href="index.php" class="register-link">Về trang chủ</a></p>

        <?php endif; ?>
    </div>
</body>
</html>