<?php
session_start();

try {
    require_once 'config.php'; // Include file cấu hình database
} catch (Exception $e) {
    echo "Lỗi include config.php: " . $e->getMessage();
    die(); // Dừng script nếu không include được
}

// Kiểm tra xem $conn có tồn tại không
if (!isset($conn)) {
    echo "Biến \$conn không được khởi tạo. Kiểm tra config.php.";
    die();
}

// Kiểm tra quyền admin và password
$passwordError = ''; // Biến lưu thông báo lỗi mật khẩu
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    if (isset($_POST['submit'])) {
        $password = $_POST['password'];
        if ($password === "password") {
            $_SESSION['admin'] = true;

            if (isset($_SESSION['username'])) {
                $username = $_SESSION['username'];
                try {
                    $stmt = $conn->prepare("SELECT type FROM users WHERE username = :username");
                    $stmt->bindParam(':username', $username);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($user && $user['type'] != 'admin') {
                        echo "<p style='color: red;'>Bạn không có quyền truy cập trang này.</p>";
                        session_destroy();
                        unset($_SESSION['admin']);
                        exit();
                    }
                } catch (PDOException $e) {
                    echo "Lỗi truy vấn database: " . $e->getMessage();
                    exit();
                }
            } else {
                header("Location: login.php");
                exit();
            }
        } else {
            $passwordError = "Mật khẩu không chính xác!";
        }
    } else {
        // Form xác nhận mật khẩu giống hệt login.php
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Xác nhận Admin</title>
            <style>
                /* CSS cho toàn bộ trang */
                body {
                    background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
                    font-family: 'Arial', sans-serif;
                    margin: 0;
                    padding: 20px;
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
                    margin: 50px auto;
                    max-width: 400px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    width: 100%;
                    box-sizing: border-box;
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

                .nut:hover {
                    background: rgba(52, 152, 219, 1);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
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

                /* Thông báo */
                .thong-bao {
                    background-color: #f0f0f0;
                    padding: 10px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }

                .thanh-cong {
                    background-color: #c6efce;
                    color: #3e8e41;
                    padding: 10px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }

                .loi {
                    background-color: #f2dede;
                    color: #a94442;
                    padding: 10px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }

                .dang-ky-link {
                    text-align: right;
                    margin-top: 10px;
                }
            </style>
        </head>
        <body>
            <div class="bang-trang-thai">
                <h1 class="tieu-de-chinh">Xác nhận Admin</h1>
                <?php if ($passwordError) { ?>
                    <div class="loi"><?php echo $passwordError; ?></div>
                <?php } ?>
                <form action="" method="post">
                    <div>
                        <label for="password" style="display:block; margin-bottom:5px;">Mật khẩu:</label>
                        <input type="password" id="password" name="password" required class="truong-nhap">
                    </div>
                    <button class="nut" type="submit" name="submit">Xác nhận</button>
                    <div class="dang-ky-link">
                        Quay lại <a href="login.php">đăng nhập</a>
                    </div>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit();
    }
} else {
    if (isset($_SESSION['username'])) {
        $username = $_SESSION['username'];
        try {
            $stmt = $conn->prepare("SELECT type FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $user['type'] != 'admin') {
                echo "<p style='color: red;'>Bạn không có quyền truy cập trang này.</p>";
                session_destroy();
                unset($_SESSION['admin']);
                exit();
            }
        } catch (PDOException $e) {
            echo "Lỗi truy vấn database: " . $e->getMessage();
            exit();
        }
    } else {
        header("Location: login.php");
        exit();
    }
}

// Xử lý AJAX request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'update_all') {
    header('Content-Type: application/json'); // Trả về JSON

    $link = isset($_POST['link']) ? trim($_POST['link']) : 0;
    $file = isset($_POST['file']) ? trim($_POST['file']) : 0;
    $fee = isset($_POST['fee']) ? trim($_POST['fee']) : 0;

    $lo = isset($_POST['lo']) ? trim($_POST['lo']) : 0;
    $loxien2 = isset($_POST['loxien2']) ? trim($_POST['loxien2']) : 0;
    $loxien3 = isset($_POST['loxien3']) ? trim($_POST['loxien3']) : 0;
    $loxien4 = isset($_POST['loxien4']) ? trim($_POST['loxien4']) : 0;
    $loxien5 = isset($_POST['loxien5']) ? trim($_POST['loxien5']) : 0;
    $loxien6 = isset($_POST['loxien6']) ? trim($_POST['loxien6']) : 0;
    $de = isset($_POST['de']) ? trim($_POST['de']) : 0;
    $de3cang = isset($_POST['de3cang']) ? trim($_POST['de3cang']) : 0;

    $lo_tyle = isset($_POST['lo_tyle']) ? trim($_POST['lo_tyle']) : 0;
    $loxien2_tyle = isset($_POST['loxien2_tyle']) ? trim($_POST['loxien2_tyle']) : 0;
    $loxien3_tyle = isset($_POST['loxien3_tyle']) ? trim($_POST['loxien3_tyle']) : 0;
    $loxien4_tyle = isset($_POST['loxien4_tyle']) ? trim($_POST['loxien4_tyle']) : 0;
    $loxien5_tyle = isset($_POST['loxien5_tyle']) ? trim($_POST['loxien5_tyle']) : 0;
    $loxien6_tyle = isset($_POST['loxien6_tyle']) ? trim($_POST['loxien6_tyle']) : 0;
    $de_tyle = isset($_POST['de_tyle']) ? trim($_POST['de_tyle']) : 0;
    $de3cang_tyle = isset($_POST['de3cang_tyle']) ? trim($_POST['de3cang_tyle']) : 0;

    try {
        $stmt = $conn->prepare("UPDATE setting SET link = :link, file = :file, fee = :fee");
        $stmt->bindParam(':link', $link);
        $stmt->bindParam(':file', $file);
        $stmt->bindParam(':fee', $fee);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE giatien SET lo = :lo, loxien2 = :loxien2, loxien3 = :loxien3, loxien4 = :loxien4, loxien5 = :loxien5, loxien6 = :loxien6, de = :de, de3cang = :de3cang");
        $stmt->bindParam(':lo', $lo);
        $stmt->bindParam(':loxien2', $loxien2);
        $stmt->bindParam(':loxien3', $loxien3);
        $stmt->bindParam(':loxien4', $loxien4);
        $stmt->bindParam(':loxien5', $loxien5);
        $stmt->bindParam(':loxien6', $loxien6);
        $stmt->bindParam(':de', $de);
        $stmt->bindParam(':de3cang', $de3cang);
        $stmt->execute();

        $stmt = $conn->prepare("UPDATE tyle SET lo = :lo_tyle, loxien2 = :loxien2_tyle, loxien3 = :loxien3_tyle, loxien4 = :loxien4_tyle, loxien5 = :loxien5_tyle, loxien6 = :loxien6_tyle, de = :de_tyle, de3cang = :de3cang_tyle");
        $stmt->bindParam(':lo_tyle', $lo_tyle);
        $stmt->bindParam(':loxien2_tyle', $loxien2_tyle);
        $stmt->bindParam(':loxien3_tyle', $loxien3_tyle);
        $stmt->bindParam(':loxien4_tyle', $loxien4_tyle);
        $stmt->bindParam(':loxien5_tyle', $loxien5_tyle);
        $stmt->bindParam(':loxien6_tyle', $loxien6_tyle);
        $stmt->bindParam(':de_tyle', $de_tyle);
        $stmt->bindParam(':de3cang_tyle', $de3cang_tyle);
        $stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'Cập nhật tất cả thành công']);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Lỗi cập nhật: ' . $e->getMessage()]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html>
    <?php include 'head.php'; ?><br><br><br>

<head>
    <title>Admin Panel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            margin: 0;
            padding: 20px;
        }
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .algorithm-form {
            margin-top: 20px;
            border: 1px solid #ddd;
            padding: 10px;
        }
        .algorithm-item {
            float: left;
            width: 50%;
            box-sizing: border-box;
            padding: 5px 10px;
        }
        .algorithm-item label {
            display: inline-block;
            width: 150px;
            text-align: left;
            margin-right: 2px;
            font-size: 0.95em;
        }
        .algorithm-item input[type="text"] {
            width: 100px;
            font-size: 0.9em;
            padding: 3px;
        }
        .algorithm-item-column {
            display: flex;
            flex-direction: column;
            margin-bottom: 8px;
        }
        .algorithm-item-column label {
            width: 150px;
            text-align: left;
            margin-bottom: 3px;
            margin-right: 2px;
            font-size: 0.95em;
        }
        .algorithm-item-column input[type="text"] {
            font-size: 0.9em;
            padding: 3px;
        }
        .algorithm-item-column input[name="link"] {
            width: 650px; /* Ô Link: 650px */
        }
        .algorithm-item-column input[name="file"] {
            width: 250px; /* Ô File: 250px */
        }
        .algorithm-item-column input[name="fee"] {
            width: 40px; /* Ô Fee: 30px */
        }
        .settings-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
        }
        .settings-column {
            width: 48%;
            box-sizing: border-box;
        }
        .settings-column h2 {
            margin-bottom: 10px;
        }
        .button-container button {
            background-color: #4CAF50;
            border: none;
            color: white;
            padding: 8px 16px;
            font-size: 1.0em;
            cursor: pointer;
            border-radius: 5px;
        }
        .button-container button:hover {
            background-color: #367c39;
        }
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            text-align: center;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-radius: 50%;
            border-top: 5px solid #3498db;
            animation: spin 1s linear infinite;
            margin-bottom: 10px;
        }
        .loading-text {
            font-size: 1.2em;
            color: #333;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .status-message {
            margin-top: 10px;
            font-size: 1.1em;
        }
        .pagination {
            margin-top: 10px;
            text-align: center;
        }
        .pagination a {
            color: #3498db;
            padding: 8px 16px;
            text-decoration: none;
            transition: background-color 0.3s;
        }
        .pagination a:hover {
            background-color: #ddd;
        }
        .pagination a.active {
            background-color: #3498db;
            color: white;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <div class="loading-text">Đang cập nhật...</div>
    </div>

    <h1>Cài đặt hệ thống</h1>

    <form id="algorithmForm" class="algorithm-form">
        <input type="hidden" name="action" value="update_all">

        <div class="settings-container">
            <div class="settings-column">
                <h2>Cài đặt Giá tiền</h2>
                <?php
                try {
                    $stmt = $conn->prepare("SELECT lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM giatien");
                    $stmt->execute();
                    $giatien = $stmt->fetch(PDO::FETCH_ASSOC);

                    $lo = $giatien['lo'] ?? 0;
                    $loxien2 = $giatien['loxien2'] ?? 0;
                    $loxien3 = $giatien['loxien3'] ?? 0;
                    $loxien4 = $giatien['loxien4'] ?? 0;
                    $loxien5 = $giatien['loxien5'] ?? 0;
                    $loxien6 = $giatien['loxien6'] ?? 0;
                    $de = $giatien['de'] ?? 0;
                    $de3cang = $giatien['de3cang'] ?? 0;

                    echo "<div class='algorithm-item'><label for='lo'>Lô:</label><input type='text' name='lo' id='lo' value='" . htmlspecialchars($lo) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien2'>Lô xiên 2:</label><input type='text' name='loxien2' id='loxien2' value='" . htmlspecialchars($loxien2) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien3'>Lô xiên 3:</label><input type='text' name='loxien3' id='loxien3' value='" . htmlspecialchars($loxien3) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien4'>Lô xiên 4:</label><input type='text' name='loxien4' id='loxien4' value='" . htmlspecialchars($loxien4) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien5'>Lô xiên 5:</label><input type='text' name='loxien5' id='loxien5' value='" . htmlspecialchars($loxien5) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien6'>Lô xiên 6:</label><input type='text' name='loxien6' id='loxien6' value='" . htmlspecialchars($loxien6) . "'></div>";
                    echo "<div class='algorithm-item'><label for='de'>Đề:</label><input type='text' name='de' id='de' value='" . htmlspecialchars($de) . "'></div>";
                    echo "<div class='algorithm-item'><label for='de3cang'>Đề 3 càng:</label><input type='text' name='de3cang' id='de3cang' value='" . htmlspecialchars($de3cang) . "'></div>";
                } catch (PDOException $e) {
                    echo "Lỗi truy vấn database: " . $e->getMessage();
                }
                ?>
            </div>

            <div class="settings-column">
                <h2>Cài đặt Tỷ lệ</h2>
                <?php
                try {
                    $stmt = $conn->prepare("SELECT lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM tyle");
                    $stmt->execute();
                    $tyle = $stmt->fetch(PDO::FETCH_ASSOC);

                    $lo_tyle = $tyle['lo'] ?? 0;
                    $loxien2_tyle = $tyle['loxien2'] ?? 0;
                    $loxien3_tyle = $tyle['loxien3'] ?? 0;
                    $loxien4_tyle = $tyle['loxien4'] ?? 0;
                    $loxien5_tyle = $tyle['loxien5'] ?? 0;
                    $loxien6_tyle = $tyle['loxien6'] ?? 0;
                    $de_tyle = $tyle['de'] ?? 0;
                    $de3cang_tyle = $tyle['de3cang'] ?? 0;

                    echo "<div class='algorithm-item'><label for='lo_tyle'>Lô:</label><input type='text' name='lo_tyle' id='lo_tyle' value='" . htmlspecialchars($lo_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien2_tyle'>Lô xiên 2:</label><input type='text' name='loxien2_tyle' id='loxien2_tyle' value='" . htmlspecialchars($loxien2_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien3_tyle'>Lô xiên 3:</label><input type='text' name='loxien3_tyle' id='loxien3_tyle' value='" . htmlspecialchars($loxien3_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien4_tyle'>Lô xiên 4:</label><input type='text' name='loxien4_tyle' id='loxien4_tyle' value='" . htmlspecialchars($loxien4_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien5_tyle'>Lô xiên 5:</label><input type='text' name='loxien5_tyle' id='loxien5_tyle' value='" . htmlspecialchars($loxien5_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='loxien6_tyle'>Lô xiên 6:</label><input type='text' name='loxien6_tyle' id='loxien6_tyle' value='" . htmlspecialchars($loxien6_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='de_tyle'>Đề:</label><input type='text' name='de_tyle' id='de_tyle' value='" . htmlspecialchars($de_tyle) . "'></div>";
                    echo "<div class='algorithm-item'><label for='de3cang_tyle'>Đề 3 càng:</label><input type='text' name='de3cang_tyle' id='de3cang_tyle' value='" . htmlspecialchars($de3cang_tyle) . "'></div>";
                } catch (PDOException $e) {
                    echo "Lỗi truy vấn database: " . $e->getMessage();
                }
                ?>
            </div>
        </div>

        <h2>Cài đặt Data và Phí dịch vụ</h2>
        <div class="settings-column">
            <?php
            try {
                $stmt = $conn->prepare("SELECT link, file, fee FROM setting");
                $stmt->execute();
                $setting = $stmt->fetch(PDO::FETCH_ASSOC);

                $link = $setting['link'] ?? 0;
                $file = $setting['file'] ?? 0;
                $fee = $setting['fee'] ?? 0;

                echo "<div class='algorithm-item-column'><label for='link'>Link:</label><input type='text' name='link' id='link' value='" . htmlspecialchars($link) . "'></div>";
                echo "<div class='algorithm-item-column'><label for='file'>File:</label><input type='text' name='file' id='file' value='" . htmlspecialchars($file) . "'></div>";
                echo "<div class='algorithm-item-column'><label for='fee'>Fee:</label><input type='text' name='fee' id='fee' value='" . htmlspecialchars($fee) . "'></div>";
            } catch (PDOException $e) {
                echo "Lỗi truy vấn database: " . $e->getMessage();
            }
            ?>
        </div>

        <div class="button-container">
            <button type="submit">Cập nhật Tất cả</button>
        </div>
        <div id="statusMessage" class="status-message"></div>
    </form>

    <h2>Danh sách lượt chơi mới (Status: NEW)</h2>
    <?php
    try {
        $stmt = $conn->prepare("SELECT id, user, date, time, lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang, money, status FROM cuoc WHERE status = 'NEW' ORDER BY time DESC");
        $stmt->execute();
        $newCuoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($newCuoc) {
            echo "<table>";
            echo "<tr><th>Lượt đặt</th><th>Người đặt</th><th>Đặt thưởng ngày</th><th>Thời gian đặt cược</th><th>Số đặt</th><th>Tổng tiền</th><th>Trạng thái</th></tr>";
            foreach ($newCuoc as $row) {
                $formattedId = str_pad($row['id'], 7, '0', STR_PAD_LEFT);

                // Tạo chuỗi "Số đặt" từ các cột lo, loxien2, ..., de3cang
                $betNumbers = [];
                $types = [
                    'lo' => 'Lô',
                    'loxien2' => 'Lô xiên 2',
                    'loxien3' => 'Lô xiên 3',
                    'loxien4' => 'Lô xiên 4',
                    'loxien5' => 'Lô xiên 5',
                    'loxien6' => 'Lô xiên 6',
                    'de' => 'Đề',
                    'de3cang' => 'Đề 3 càng'
                ];
                foreach ($types as $key => $label) {
                    if (!empty($row[$key])) {
                        $betNumbers[] = "$label: " . htmlspecialchars($row[$key]);
                    }
                }
                $betNumbersStr = empty($betNumbers) ? 'Không có' : implode('; ', $betNumbers);

                echo "<tr>";
                echo "<td>" . htmlspecialchars($formattedId) . "</td>";
                echo "<td>" . htmlspecialchars($row['user']) . "</td>";
                echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($row['date']))) . "</td>";
                echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                echo "<td>" . $betNumbersStr . "</td>";
                echo "<td>" . number_format($row['money'], 0, ',', '.') . " Vnđ</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Không có lượt chơi mới nào.</p>";
        }
    } catch (PDOException $e) {
        echo "Lỗi truy vấn database: " . $e->getMessage();
    }
    ?>

    <h2>Lịch sử Lượt chơi</h2>
    <?php
    $limit = 20;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $start = ($page - 1) * $limit;

    try {
        $stmt = $conn->prepare("SELECT date, user, time, money, status FROM cuoc WHERE status != 'NEW' ORDER BY date DESC LIMIT :start, :limit");
        $stmt->bindParam(':start', $start, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $historyCuoc = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->prepare("SELECT COUNT(*) FROM cuoc WHERE status != 'NEW'");
        $stmt->execute();
        $totalRows = $stmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        if ($historyCuoc) {
            echo "<table><tr><th>Date</th><th>User</th><th>Time</th><th>Money</th><th>Status</th></tr>";
            foreach ($historyCuoc as $row) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                echo "<td>" . htmlspecialchars($row['user']) . "</td>";
                echo "<td>" . htmlspecialchars($row['time']) . "</td>";
                echo "<td>" . htmlspecialchars($row['money']) . "</td>";
                echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='pagination'>";
            for ($i = 1; $i <= $totalPages; $i++) {
                $active = ($i == $page) ? " class='active'" : "";
                echo "<a href='admin.php?page=$i'$active>$i</a> ";
            }
            echo "</div>";
        } else {
            echo "<p>Không có lịch sử lượt chơi.</p>";
        }
    } catch (PDOException $e) {
        echo "Lỗi truy vấn database: " . $e->getMessage();
    }
    ?>

    <script>
        document.getElementById('algorithmForm').addEventListener('submit', function(e) {
            e.preventDefault(); // Ngăn submit form mặc định

            // Hiển thị loading
            document.getElementById('loading').style.display = 'block';
            document.getElementById('statusMessage').innerHTML = '';

            // Lấy dữ liệu từ form
            const formData = new FormData(this);

            // Gửi AJAX request
            fetch('admin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                // Ẩn loading
                document.getElementById('loading').style.display = 'none';

                // Hiển thị thông báo
                const statusMessage = document.getElementById('statusMessage');
                statusMessage.innerHTML = data.message;
                statusMessage.style.color = data.status === 'success' ? 'green' : 'red';

                // Cập nhật giá trị trên giao diện (không cần reload)
                if (data.status === 'success') {
                    document.getElementById('link').value = formData.get('link');
                    document.getElementById('file').value = formData.get('file');
                    document.getElementById('fee').value = formData.get('fee');
                    document.getElementById('lo').value = formData.get('lo');
                    document.getElementById('loxien2').value = formData.get('loxien2');
                    document.getElementById('loxien3').value = formData.get('loxien3');
                    document.getElementById('loxien4').value = formData.get('loxien4');
                    document.getElementById('loxien5').value = formData.get('loxien5');
                    document.getElementById('loxien6').value = formData.get('loxien6');
                    document.getElementById('de').value = formData.get('de');
                    document.getElementById('de3cang').value = formData.get('de3cang');
                    document.getElementById('lo_tyle').value = formData.get('lo_tyle');
                    document.getElementById('loxien2_tyle').value = formData.get('loxien2_tyle');
                    document.getElementById('loxien3_tyle').value = formData.get('loxien3_tyle');
                    document.getElementById('loxien4_tyle').value = formData.get('loxien4_tyle');
                    document.getElementById('loxien5_tyle').value = formData.get('loxien5_tyle');
                    document.getElementById('loxien6_tyle').value = formData.get('loxien6_tyle');
                    document.getElementById('de_tyle').value = formData.get('de_tyle');
                    document.getElementById('de3cang_tyle').value = formData.get('de3cang_tyle');
                }
            })
            .catch(error => {
                document.getElementById('loading').style.display = 'none';
                document.getElementById('statusMessage').innerHTML = 'Lỗi: ' + error.message;
                document.getElementById('statusMessage').style.color = 'red';
            });
        });
    </script>
</body>
</html>