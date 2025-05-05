<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php

session_start(); // Bắt đầu session

$usernameError = '';
$passwordError = '';
$success = '';
$alreadyLoggedIn = '';

// Kiểm tra xem người dùng đã đăng nhập hay chưa
if (isset($_SESSION['username'])) {
    $alreadyLoggedIn = "Bạn đã có tài khoản! Sẽ chuyển về trang chủ sau 2s";
} elseif (isset($_POST['submit'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Kiểm tra username bằng PDO
    try {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $usernameError = "Tên người dùng đã tồn tại!";
        } else {
            // Kiểm tra mật khẩu
            if (strlen($password) < 6 || strlen($password) > 15) {
                $passwordError = "Mật khẩu phải từ 6 đến 15 ký tự!";
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);

                // Chèn dữ liệu vào bảng users (bao gồm cột win mặc định là 0)
                $query = "INSERT INTO users (username, password, money, type, win) VALUES (:username, :password, :money, :type, :win)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':password', $password_hash, PDO::PARAM_STR);
                $money = 10000000;
                $stmt->bindParam(':money', $money, PDO::PARAM_INT);
                $type = 'user';
                $stmt->bindParam(':type', $type, PDO::PARAM_STR);
                $win = 0; // Giá trị mặc định cho cột win
                $stmt->bindParam(':win', $win, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $success = "Bạn đã đăng ký thành công! Sẽ chuyển về trang đăng nhập sau 2s";
                } else {
                    $usernameError = "Có lỗi xảy ra khi đăng ký!";
                }
            }
        }
    } catch (PDOException $e) {
        $usernameError = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
    }
}

if (isset($_POST['checkUsername'])) {
    $username = $_POST['username'];

    try {
        $query = "SELECT * FROM users WHERE username = :username";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            echo json_encode(['status' => 'taken', 'message' => 'Tên người dùng đã tồn tại!']);
        } else {
            echo json_encode(['status' => 'available', 'message' => 'Tên người dùng khả dụng!']);
        }
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi kiểm tra: ' . $e->getMessage()]);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký</title>
    <style>
        /* CSS cho toàn bộ trang */
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        /* Animation cho tiêu đề */
        @keyframes doiMauChu {
            0% { background-position: 0% 50%; }
            100% { background-position: 200% 50%; }
        }

        .tieu-de-chinh {
            text-align: center;
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 30px;
            line-height: 1.2;
            background: linear-gradient(to right, #4e54c8 0%, #8f94fb 30%, #4e54c8 60%, #8f94fb 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-shadow: 0px 0px 1px rgba(0,0,0,0.1);
            background-size: 200% auto;
            animation: doiMauChu 3s linear infinite;
        }

        /* Bảng trạng thái */
        .bang-trang-thai {
            background: white;
            padding: 20px;
            border-radius: 10px;
            max-width: 400px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 100%;
            box-sizing: border-box;
            position: relative;
        }

        /* Nút */
        .nut {
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 10px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: block;
            width: 200px;
            margin: 0 auto;
        }

        .nut:hover:not(:disabled) {
            background: rgba(52, 152, 219, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .nut:disabled {
            background: #cccccc;
            cursor: not-allowed;
        }

        /* Các trường nhập liệu */
        .truong-nhap {
            width: calc(100% - 22px);
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-bottom: 15px;
            box-sizing: border-box;
        }

        /* Thông báo nổi */
        .thong-bao-noi {
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 1.1em;
            text-align: center;
            opacity: 0;
            animation: fadeInOut 3s ease-in-out forwards;
        }

        .thanh-cong {
            background-color: #c6efce;
            color: #3e8e41;
        }

        .loi {
            background-color: #f2dede;
            color: #a94442;
        }

        .trang-thai-username {
            float: right;
        }

        .dang-nhap-link {
            text-align: right;
            margin-top: 10px;
        }

        /* Animation cho thông báo nổi */
        @keyframes fadeInOut {
            0% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
            20% { opacity: 1; transform: translateX(-50%) translateY(0); }
            80% { opacity: 1; transform: translateX(-50%) translateY(0); }
            100% { opacity: 0; transform: translateX(-50%) translateY(-20px); }
        }
    </style>
</head>
<body>
    <div class="bang-trang-thai">
        <h1 class="tieu-de-chinh">Đăng ký</h1>
        <?php if ($alreadyLoggedIn || $success || $usernameError || $passwordError) { ?>
            <?php if ($alreadyLoggedIn) { ?>
                <div class="thong-bao-noi thanh-cong">
                    <?php echo $alreadyLoggedIn; ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'index.php';
                        }, 2000);
                    </script>
                </div>
            <?php } elseif ($success) { ?>
                <div class="thong-bao-noi thanh-cong">
                    <?php echo $success; ?>
                    <script>
                        setTimeout(function() {
                            window.location.href = 'login.php';
                        }, 2000);
                    </script>
                </div>
            <?php } elseif ($usernameError) { ?>
                <div class="thong-bao-noi loi">
                    <?php echo $usernameError; ?>
                </div>
            <?php } elseif ($passwordError) { ?>
                <div class="thong-bao-noi loi">
                    <?php echo $passwordError; ?>
                </div>
            <?php } ?>
        <?php } else { ?>
            <form action="" method="post">
                <div>
                    <label for="username" style="display:block; margin-bottom:5px;">Username:</label>
                    <input type="text" id="username" name="username" required class="truong-nhap" oninput="checkUsername(this.value)">
                    <span id="usernameStatus" class="trang-thai-username"></span>
                </div>
                <div>
                    <label for="password" style="display:block; margin-bottom:5px;">Mật khẩu:</label>
                    <input type="password" id="password" name="password" required class="truong-nhap">
                </div>
                <button class="nut" type="submit" name="submit" id="registerButton" disabled>Đăng ký</button> <br>
                <div class="dang-nhap-link"> 
                    Bạn đã có tài khoản, hãy <a href="login.php">đăng nhập</a> 
                </div> <br>
                            <center>   <a href="index.php" class="register-link">Homepage</a></center> 

            </form>
        <?php } ?>
    </div>

    <script>
        function checkUsername(username) {
            if (username.trim() === '') {
                document.getElementById("usernameStatus").innerHTML = '';
                document.getElementById("registerButton").disabled = true;
                return;
            }

            var xhr = new XMLHttpRequest();
            xhr.open("POST", "", true);
            xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
            xhr.onload = function() {
                try {
                    var response = JSON.parse(this.responseText);
                    document.getElementById("usernameStatus").innerHTML = response.message;
                    document.getElementById("usernameStatus").style.color = response.status === 'taken' ? 'red' : 'green';
                    document.getElementById("registerButton").disabled = (response.status === 'taken');
                } catch (e) {
                    document.getElementById("usernameStatus").innerHTML = 'Lỗi kiểm tra username!';
                    document.getElementById("usernameStatus").style.color = 'red';
                    document.getElementById("registerButton").disabled = true;
                }
            };
            xhr.send("checkUsername=true&username=" + encodeURIComponent(username));
        }

        // Kích hoạt nút đăng ký khi trang tải nếu username ban đầu trống
        document.addEventListener("DOMContentLoaded", function() {
            document.getElementById("registerButton").disabled = true;
        });
    </script>
</body>
</html>