<?php
// install.php (phiên bản tự tạo schema)

error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

define('CONFIG_FILE', 'config.php');
define('ADMIN_FILE', 'admin.php');
//define('SQL_FILE', 'data.sql'); // Không dùng nữa
define('INDEX_FILE', 'index.php'); // Trang chủ để redirect về

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$errors = [];
$success_message = '';
$install_data = $_SESSION['install_data'] ?? [];

// Hàm kiểm tra quyền ghi file (chỉ cần cho config.php, admin.php, install.php)
function check_writable($files) {
    $not_writable = [];
    foreach ($files as $file) {
        if (!is_writable(dirname($file)) || (file_exists($file) && !is_writable($file))) {
            $not_writable[] = $file;
        }
    }
    return $not_writable;
}

// *** HÀM MỚI: Tự tạo cấu trúc CSDL và chèn dữ liệu ban đầu ***
function create_database_schema($conn) {
    try {
        $conn->beginTransaction();

        // --- Tạo bảng cuoc ---
        $conn->exec("
            CREATE TABLE `cuoc` (
              `id` int(20) NOT NULL,
              `date` date DEFAULT NULL,
              `user` varchar(255) DEFAULT NULL,
              `time` datetime DEFAULT NULL,
              `lo` varchar(255) DEFAULT NULL,
              `loxien2` varchar(255) DEFAULT NULL,
              `loxien3` varchar(255) DEFAULT NULL,
              `loxien4` varchar(255) DEFAULT NULL,
              `loxien5` varchar(255) DEFAULT NULL,
              `loxien6` varchar(255) DEFAULT NULL,
              `de` varchar(255) DEFAULT NULL,
              `de3cang` varchar(255) DEFAULT NULL,
              `money` decimal(20,0) DEFAULT NULL,
              `status` mediumtext DEFAULT NULL,
              `ketqua` text NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");
        $conn->exec("ALTER TABLE `cuoc` ADD PRIMARY KEY (`id`);");
        $conn->exec("ALTER TABLE `cuoc` MODIFY `id` int(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1000100;");

        // --- Tạo bảng giatien ---
        $conn->exec("
            CREATE TABLE `giatien` (
              `lo` mediumint(225) DEFAULT NULL,
              `loxien2` mediumint(255) DEFAULT NULL,
              `loxien3` mediumint(255) DEFAULT NULL,
              `loxien4` mediumint(255) DEFAULT NULL,
              `loxien5` mediumint(255) DEFAULT NULL,
              `loxien6` mediumint(255) DEFAULT NULL,
              `de` mediumint(255) DEFAULT NULL,
              `de3cang` mediumint(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");
        // Chèn dữ liệu giatien
        $conn->exec("
            INSERT INTO `giatien` (`lo`, `loxien2`, `loxien3`, `loxien4`, `loxien5`, `loxien6`, `de`, `de3cang`) VALUES
            (20000, 20000, 20000, 20000, 20000, 20000, 1000, 1000);
        ");

        // --- Tạo bảng setting ---
        $conn->exec("
            CREATE TABLE `setting` (
              `link` mediumtext DEFAULT NULL,
              `file` mediumtext DEFAULT NULL,
              `fee` decimal(10,3) DEFAULT 0.000
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");
        // Chèn dữ liệu setting
        $conn->exec("
            INSERT INTO `setting` (`link`, `file`, `fee`) VALUES
            ('https://raw.githubusercontent.com/khiemdoan/vietnam-lottery-xsmb-analysis/refs/heads/main/data/xsmb.json', '../public_html/data/xsmb.json', 0.020);
        ");

        // --- Tạo bảng timeupdate ---
        $conn->exec("
            CREATE TABLE `timeupdate` (
              `date` date DEFAULT NULL,
              `time` time DEFAULT NULL,
              `number` int(11) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");

        // --- Tạo bảng tyle ---
        $conn->exec("
            CREATE TABLE `tyle` (
              `lo` varchar(255) DEFAULT NULL,
              `loxien2` varchar(255) DEFAULT NULL,
              `loxien3` varchar(255) DEFAULT NULL,
              `loxien4` varchar(255) DEFAULT NULL,
              `loxien5` varchar(255) DEFAULT NULL,
              `loxien6` varchar(255) DEFAULT NULL,
              `de` varchar(255) DEFAULT NULL,
              `de3cang` varchar(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");
        // Chèn dữ liệu tyle
        $conn->exec("
            INSERT INTO `tyle` (`lo`, `loxien2`, `loxien3`, `loxien4`, `loxien5`, `loxien6`, `de`, `de3cang`) VALUES
            ('3.5', '10', '70', '400', '4000', '10000', '70', '400');
        ");

        // --- Tạo bảng users ---
        $conn->exec("
            CREATE TABLE `users` (
              `id` int(11) NOT NULL,
              `username` varchar(255) NOT NULL,
              `password` varchar(255) NOT NULL,
              `type` enum('user','admin') DEFAULT 'user',
              `time_create` timestamp NOT NULL DEFAULT current_timestamp(),
              `money` decimal(30,0) DEFAULT 0,
              `luot_choi` int(11) DEFAULT 0,
              `win` decimal(20,0) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");
        $conn->exec("ALTER TABLE `users` ADD PRIMARY KEY (`id`);");
        $conn->exec("ALTER TABLE `users` ADD UNIQUE KEY `username` (`username`);");
        $conn->exec("ALTER TABLE `users` MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;"); // Bắt đầu từ 1

        // --- Tạo bảng xuly ---
        $conn->exec("
            CREATE TABLE `xuly` (
              `date` date DEFAULT NULL,
              `time` datetime DEFAULT NULL,
              `id_list` text NOT NULL,
              `status` text DEFAULT NULL,
              `money` mediumint(20) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_vietnamese_ci;
        ");

        $conn->commit();
        return true;
    } catch (PDOException $e) {
        $conn->rollBack();
        // Ném lại exception để hàm gọi xử lý
        throw new Exception("Lỗi tạo cấu trúc CSDL: " . $e->getMessage());
    }
}

// Hàm cập nhật file config
function update_config($db_host, $db_name, $db_user, $db_pass) {
    $config_content = @file_get_contents(CONFIG_FILE);
    if ($config_content === false) {
        throw new Exception("Không thể đọc file " . CONFIG_FILE);
    }

    // Thay thế các giá trị placeholder (giả sử chúng nằm trong dấu ngoặc kép)
    $config_content = preg_replace('/\$servername\s*=\s*"[^"]*";/', '$servername = "' . addslashes($db_host) . '";', $config_content);
    $config_content = preg_replace('/\$username\s*=\s*"[^"]*";/', '$username = "' . addslashes($db_user) . '";', $config_content);
    $config_content = preg_replace('/\$password\s*=\s*"[^"]*";/', '$password = "' . addslashes($db_pass) . '";', $config_content);
    $config_content = preg_replace('/\$dbname\s*=\s*"[^"]*";/', '$dbname = "' . addslashes($db_name) . '";', $config_content);

    if (@file_put_contents(CONFIG_FILE, $config_content) === false) {
        throw new Exception("Không thể ghi vào file " . CONFIG_FILE . ". Vui lòng kiểm tra quyền ghi.");
    }
}

// Hàm cập nhật mật khẩu bảo vệ admin.php
function update_admin_protection($admin_protection_password) {
    $admin_content = @file_get_contents(ADMIN_FILE);
     if ($admin_content === false) {
        throw new Exception("Không thể đọc file " . ADMIN_FILE);
    }

    // Tìm và thay thế dòng kiểm tra mật khẩu cũ (giả định là "password")
    $search_pattern = '/if\s*\(\s*\$password\s*===\s*"password"\s*\)\s*{/';
    $replace_pattern = 'if ($password === "' . addslashes($admin_protection_password) . '") {';

    $count = 0;
    $new_admin_content = preg_replace($search_pattern, $replace_pattern, $admin_content, 1, $count);

    if ($count === 0) {
         throw new Exception("Không tìm thấy dòng mật khẩu cần thay thế trong " . ADMIN_FILE . ". Vui lòng kiểm tra lại file hoặc cập nhật thủ công.");
    }

    if (@file_put_contents(ADMIN_FILE, $new_admin_content) === false) {
        throw new Exception("Không thể ghi vào file " . ADMIN_FILE . ". Vui lòng kiểm tra quyền ghi.");
    }
}

// Hàm tạo user admin
function create_admin_user($conn, $admin_username, $admin_password) {
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
    try {
        // Sử dụng prepared statement để an toàn hơn
        $stmt = $conn->prepare("INSERT INTO users (username, password, type) VALUES (:username, :password, 'admin')");
        $stmt->bindParam(':username', $admin_username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->execute();
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            throw new Exception("Tên đăng nhập admin '" . htmlspecialchars($admin_username) . "' đã tồn tại. Vui lòng chọn tên khác.");
        } else {
            throw new Exception("Lỗi tạo user admin: " . $e->getMessage());
        }
    }
}

// --- Xử lý POST request (tương tự như trước, chỉ bỏ phần liên quan đến data.sql) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if ($step == 1 && isset($_POST['submit_db'])) {
        // Lưu thông tin vào session và chuyển sang bước 2
        $install_data['db_host'] = trim($_POST['db_host']);
        $install_data['db_name'] = trim($_POST['db_name']);
        $install_data['db_user'] = trim($_POST['db_user']);
        $install_data['db_pass'] = $_POST['db_pass']; // Không trim pass

        if (empty($install_data['db_host']) || empty($install_data['db_name']) || empty($install_data['db_user'])) {
             $errors[] = "Vui lòng nhập đầy đủ thông tin kết nối CSDL.";
        } else {
            try {
                // Thử kết nối để kiểm tra
                $test_conn = new PDO(
                    "mysql:host={$install_data['db_host']};dbname={$install_data['db_name']}",
                    $install_data['db_user'],
                    $install_data['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION] // Thêm dòng này
                );
                 $_SESSION['install_data'] = $install_data;
                 header("Location: install.php?step=2");
                 exit();
            } catch(PDOException $e) {
                // Kiểm tra xem lỗi có phải là "Unknown database" không
                 if (strpos($e->getMessage(), 'Unknown database') !== false) {
                     // Thử kết nối chỉ với host để kiểm tra user/pass
                     try {
                         $test_conn_host_only = new PDO(
                             "mysql:host={$install_data['db_host']}",
                              $install_data['db_user'],
                              $install_data['db_pass'],
                              [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                         );
                          // Kết nối host thành công, nghĩa là DB chưa tồn tại
                          // Tạo DB
                          $test_conn_host_only->exec("CREATE DATABASE `{$install_data['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
                          $_SESSION['install_data'] = $install_data;
                          header("Location: install.php?step=2");
                          exit();

                     } catch(PDOException $e_host) {
                          // Lỗi ngay cả khi kết nối host -> user/pass sai hoặc host sai
                          $errors[] = "Kết nối CSDL thất bại: " . $e_host->getMessage() . ". Vui lòng kiểm tra Host, Username, Password.";
                     }
                 } else {
                     // Lỗi kết nối khác
                     $errors[] = "Kết nối CSDL thất bại: " . $e->getMessage();
                 }
            }
        }

    } elseif ($step == 2 && isset($_POST['submit_admin'])) {
        $install_data['admin_username'] = trim($_POST['admin_username']);
        $install_data['admin_password'] = $_POST['admin_password'];
        $install_data['admin_password_confirm'] = $_POST['admin_password_confirm'];
        $install_data['admin_protection_password'] = $_POST['admin_protection_password'];

        // Kiểm tra input
        if (empty($install_data['admin_username']) || empty($install_data['admin_password']) || empty($install_data['admin_protection_password'])) {
            $errors[] = "Vui lòng nhập đầy đủ thông tin tài khoản Admin và mật khẩu bảo vệ.";
        } elseif ($install_data['admin_password'] !== $install_data['admin_password_confirm']) {
            $errors[] = "Mật khẩu Admin và xác nhận mật khẩu không khớp.";
        } elseif (strlen($install_data['admin_password']) < 6) {
             $errors[] = "Mật khẩu Admin phải có ít nhất 6 ký tự.";
        } elseif (strlen($install_data['admin_protection_password']) < 6) {
             $errors[] = "Mật khẩu bảo vệ trang Admin phải có ít nhất 6 ký tự.";
        } else {
            // Kiểm tra quyền ghi file cần thiết (config.php, admin.php, install.php)
            $writable_check = check_writable([CONFIG_FILE, ADMIN_FILE, __FILE__]);
            if (!empty($writable_check)) {
                 $errors[] = "Script không có quyền ghi/xóa các file sau: " . implode(', ', $writable_check) . ". Vui lòng cấp quyền phù hợp.";
            } elseif (!file_exists(CONFIG_FILE)) {
                 $errors[] = "Không tìm thấy file " . CONFIG_FILE . ".";
            } elseif (!file_exists(ADMIN_FILE)) {
                 $errors[] = "Không tìm thấy file " . ADMIN_FILE . ".";
            } else {
                // Bắt đầu quá trình cài đặt thực sự
                try {
                    // 1. Cập nhật file config.php
                    update_config(
                        $install_data['db_host'],
                        $install_data['db_name'],
                        $install_data['db_user'],
                        $install_data['db_pass']
                    );

                    // 2. Kết nối lại bằng thông tin mới
                    $conn = new PDO(
                        "mysql:host={$install_data['db_host']};dbname={$install_data['db_name']}",
                        $install_data['db_user'],
                        $install_data['db_pass'],
                         [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                    );
                    $conn->exec("SET NAMES 'utf8mb4'");

                    // *** 3. TẠO CẤU TRÚC CSDL ***
                    create_database_schema($conn);

                    // 4. Tạo user admin
                    create_admin_user($conn, $install_data['admin_username'], $install_data['admin_password']);

                    // 5. Cập nhật mật khẩu bảo vệ admin.php
                    update_admin_protection($install_data['admin_protection_password']);

                    // Lưu session và chuyển bước 3 (hoàn tất)
                    $_SESSION['install_data'] = $install_data;
                    $_SESSION['install_success'] = true;
                    header("Location: install.php?step=3");
                    exit();

                } catch (Exception $e) {
                    $errors[] = "Quá trình cài đặt thất bại: " . $e->getMessage();
                }
            }
        }
         // Nếu có lỗi, ở lại bước 2

    } elseif ($step == 3 && isset($_POST['finish_install'])) {
        // Bước cuối cùng: Xóa file install.php và redirect
        if (isset($_SESSION['install_success']) && $_SESSION['install_success'] === true) {
            // Không cần xóa data.sql nữa
            $deleted_install = @unlink(__FILE__); // Xóa chính file này

            // Xóa session
            session_unset();
            session_destroy();

             if (!$deleted_install) {
                 echo "<p style='color:orange;'>Cảnh báo: Không thể tự động xóa file install.php. Vui lòng xóa thủ công.</p>";
                 echo "<p>Bạn sẽ được chuyển hướng về trang chủ sau 5 giây...</p>";
                 echo "<meta http-equiv='refresh' content='5;url=" . INDEX_FILE . "'>";
             } else {
                header("Location: " . INDEX_FILE);
                exit();
             }
        } else {
             header("Location: install.php");
             exit();
        }
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt Hệ thống</title>
    <style>
        body { font-family: sans-serif; line-height: 1.6; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 20px auto; background: #fff; padding: 30px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { text-align: center; color: #333; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input[type="text"],
        .form-group input[type="password"] { width: calc(100% - 22px); padding: 10px; border: 1px solid #ddd; border-radius: 3px; }
        .button { display: block; width: 100%; background-color: #5cb85c; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; text-align: center; margin-top: 20px; }
        .button:hover { background-color: #4cae4c; }
        .error { background-color: #f2dede; color: #a94442; padding: 10px; border: 1px solid #ebccd1; border-radius: 4px; margin-bottom: 15px; }
        .success { background-color: #dff0d8; color: #3c763d; padding: 15px; border: 1px solid #d6e9c6; border-radius: 4px; margin-bottom: 20px; text-align: center; }
        .warning { background-color: #fcf8e3; color: #8a6d3b; padding: 10px; border: 1px solid #faebcc; border-radius: 4px; margin-bottom: 15px; }
        ul { padding-left: 20px; }
        code { background: #eee; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>

<div class="container">
    <h1>Cài đặt Hệ thống</h1>

    <?php if (!empty($errors)): ?>
        <div class="error">
            <strong>Đã xảy ra lỗi:</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($step == 1): ?>
        <h2>Bước 1: Cấu hình Cơ sở dữ liệu</h2>
        <p>Vui lòng cung cấp thông tin kết nối đến MySQL/MariaDB của bạn. Nếu Database chưa tồn tại, script sẽ cố gắng tạo nó.</p>
        <form action="install.php?step=1" method="post">
            <div class="form-group">
                <label for="db_host">Database Host:</label>
                <input type="text" id="db_host" name="db_host" value="<?php echo htmlspecialchars($install_data['db_host'] ?? 'localhost'); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_name">Database Name:</label>
                <input type="text" id="db_name" name="db_name" value="<?php echo htmlspecialchars($install_data['db_name'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_user">Database Username:</label>
                <input type="text" id="db_user" name="db_user" value="<?php echo htmlspecialchars($install_data['db_user'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="db_pass">Database Password:</label>
                <input type="password" id="db_pass" name="db_pass">
            </div>
            <button type="submit" name="submit_db" class="button">Kiểm tra & Tiếp tục</button>
        </form>

    <?php elseif ($step == 2): ?>
        <h2>Bước 2: Tạo Tài khoản Admin & Bảo mật</h2>
         <div class="warning">
            <strong>Kiểm tra quyền ghi file:</strong>
            <?php
                $writable_check = check_writable([CONFIG_FILE, ADMIN_FILE, __FILE__]);
                if (empty($writable_check)) {
                    echo "<span style='color:green;'>Tốt! Script có đủ quyền ghi/xóa các file cần thiết.</span>";
                } else {
                     echo "<span style='color:red;'>Lỗi! Script không có quyền ghi/xóa các file sau: " . implode(', ', $writable_check) . ". Vui lòng cấp quyền phù hợp trước khi tiếp tục.</span>";
                }
            ?>
             <br><strong>Kiểm tra sự tồn tại file:</strong>
              <?php
                $missing_files = [];
                // Không cần kiểm tra data.sql nữa
                if (!file_exists(CONFIG_FILE)) $missing_files[] = CONFIG_FILE;
                if (!file_exists(ADMIN_FILE)) $missing_files[] = ADMIN_FILE;
                if(empty($missing_files)) {
                    echo "<span style='color:green;'>Tốt! Các file " . CONFIG_FILE . ", " . ADMIN_FILE . " đã tồn tại.</span>";
                } else {
                    echo "<span style='color:red;'>Lỗi! Không tìm thấy các file sau: " . implode(', ', $missing_files) . ". Vui lòng đảm bảo chúng tồn tại cùng thư mục với install.php.</span>";
                }
              ?>
        </div>
        <p>Tạo tài khoản quản trị viên và thiết lập mật khẩu để bảo vệ trang <code>admin.php</code>.</p>
        <form action="install.php?step=2" method="post">
            <div class="form-group">
                <label for="admin_username">Tên đăng nhập Admin:</label>
                <input type="text" id="admin_username" name="admin_username" value="<?php echo htmlspecialchars($install_data['admin_username'] ?? 'admin'); ?>" required>
            </div>
            <div class="form-group">
                <label for="admin_password">Mật khẩu Admin (ít nhất 6 ký tự):</label>
                <input type="password" id="admin_password" name="admin_password" required>
            </div>
            <div class="form-group">
                <label for="admin_password_confirm">Xác nhận Mật khẩu Admin:</label>
                <input type="password" id="admin_password_confirm" name="admin_password_confirm" required>
            </div>
            <hr style="margin: 20px 0;">
             <div class="form-group">
                <label for="admin_protection_password">Mật khẩu Bảo vệ Trang Admin (khác mật khẩu đăng nhập, ít nhất 6 ký tự):</label>
                <input type="password" id="admin_protection_password" name="admin_protection_password" required>
                 <small>Mật khẩu này sẽ được dùng để thay thế mật khẩu mặc định trong file <code><?php echo ADMIN_FILE; ?></code>.</small>
            </div>
            <button type="submit" name="submit_admin" class="button" <?php if(!empty($writable_check) || !empty($missing_files)) echo 'disabled style="background-color: #ccc;"'; ?>>Bắt đầu Cài đặt</button>
             <?php if(!empty($writable_check) || !empty($missing_files)) echo '<p style="color:red; text-align:center;">Vui lòng khắc phục các lỗi về quyền ghi/thiếu file trước khi cài đặt.</p>'; ?>
        </form>

     <?php elseif ($step == 3 && isset($_SESSION['install_success']) && $_SESSION['install_success'] === true): ?>
        <h2>Bước 3: Hoàn tất Cài đặt</h2>
        <div class="success">
            <strong>Cài đặt thành công!</strong>
        </div>
        <p>Hệ thống đã được cài đặt và cấu hình. Vui lòng lưu lại các thông tin quan trọng sau:</p>
        <ul>
            <li><strong>Tên đăng nhập Admin:</strong> <code><?php echo htmlspecialchars($install_data['admin_username']); ?></code></li>
            <li><strong>Mật khẩu Admin:</strong> (Là mật khẩu bạn đã nhập - Hãy ghi nhớ)</li>
            <li><strong>Link đăng nhập Admin:</strong> <a href="<?php echo ADMIN_FILE; ?>" target="_blank"><?php echo ADMIN_FILE; ?></a></li>
            <li><strong>Mật khẩu bảo vệ trang Admin:</strong> <code><?php echo htmlspecialchars($install_data['admin_protection_password']); ?></code> (Nhập mật khẩu này khi được yêu cầu truy cập <?php echo ADMIN_FILE; ?>)</li>
        </ul>
        <div class="warning">
            <strong>QUAN TRỌNG:</strong>
            <ul>
                <li>Tuyệt đối không chia sẻ mật khẩu Admin và mật khẩu bảo vệ trang Admin cho bất kỳ ai.</li>
                <li>Để đảm bảo an toàn, file cài đặt (<code>install.php</code>) sẽ bị xóa khi bạn bấm nút "Hoàn tất".</li>
            </ul>
        </div>
        <form action="install.php?step=3" method="post">
            <button type="submit" name="finish_install" class="button">Hoàn tất & Xóa file cài đặt</button>
        </form>

    <?php else: ?>
        <?php
            if ($step !== 1) {
                 header("Location: install.php");
                 exit();
            }
        ?>
         <p>Có lỗi xảy ra hoặc bạn đang truy cập trang không hợp lệ.</p>
         <a href="install.php" class="button" style="background-color:#f0ad4e; text-decoration:none;">Bắt đầu lại</a>
    <?php endif; ?>

</div>

</body>
</html>