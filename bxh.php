<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php

session_start();

// Thiết lập số lượng dòng mỗi trang
$rowsPerPage = 30;

// Lấy số trang hiện tại từ URL, mặc định là trang 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Tính offset cho truy vấn SQL
$offset = ($page - 1) * $rowsPerPage;

// Lấy tổng số user để tính tổng số trang
try {
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $totalStmt->execute();
    $totalUsers = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalUsers / $rowsPerPage);
} catch (PDOException $e) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}

// Lấy thông tin xếp hạng của user đang đăng nhập (nếu có)
$rank = 0;
if (isset($_SESSION['username'])) {
    $username = $_SESSION['username'];
    try {
        $rankStmt = $conn->prepare("
            SELECT COUNT(*) + 1 as rank 
            FROM users u1 
            WHERE u1.money > (SELECT money FROM users u2 WHERE u2.username = :username)
        ");
        $rankStmt->bindParam(':username', $username, PDO::PARAM_STR);
        $rankStmt->execute();
        $rank = $rankStmt->fetch(PDO::FETCH_ASSOC)['rank'];
    } catch (PDOException $e) {
        $error = "Lỗi truy vấn xếp hạng: " . $e->getMessage();
    }
}

// Lấy danh sách users cho bảng xếp hạng
try {
    $stmt = $conn->prepare("
        SELECT username, time_create, money, luot_choi, win 
        FROM users 
        ORDER BY money DESC 
        LIMIT :offset, :rowsPerPage
    ");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="vi">
<?php include 'head.php'; ?>
<title>Bảng xếp hạng người chơi</title>
<body>
    <div class="bxh-container">
        <!-- Thông báo xếp hạng nếu user đã đăng nhập -->
        <?php if (isset($_SESSION['username'])): ?>
            <div class="bxh-welcome">
                <h2>Xin chào, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h2>
                <p>Bạn đang xếp thứ <?php echo $rank; ?> / <?php echo $totalUsers; ?> người chơi.</p>
            </div>
        <?php endif; ?>

        <!-- Bảng xếp hạng -->
        <h1 class="bxh-title">Bảng xếp hạng người chơi</h1>
        <table class="bxh-table">
            <thead>
                <tr>
                    <th>Số thứ tự</th>
                    <th>Tên</th>
                    <th>Thời gian tạo tài khoản</th>
                    <th>Số tiền</th>
                    <th>Lượt chơi</th>
                    <th>Số lần thắng</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($users)): ?>
                    <?php foreach ($users as $index => $user): ?>
                        <?php $stt = $offset + $index + 1; ?>
                        <tr>
                            <td class="<?php 
                                if ($stt == 1) echo 'bxh-top-1'; 
                                elseif ($stt == 2) echo 'bxh-top-2'; 
                                elseif ($stt == 3) echo 'bxh-top-3'; 
                            ?>">
                                <?php echo $stt; ?>
                            </td>
                            <td class="<?php 
                                if ($stt == 1) echo 'bxh-top-1'; 
                                elseif ($stt == 2) echo 'bxh-top-2'; 
                                elseif ($stt == 3) echo 'bxh-top-3'; 
                            ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td><?php echo (new DateTime($user['time_create']))->format('d-m-Y H:i:s'); ?></td>
                            <td><?php echo number_format($user['money'], 0, ',', '.'); ?></td>
                            <td><?php echo htmlspecialchars($user['luot_choi']); ?></td>
                            <td><?php echo htmlspecialchars($user['win']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6">Không có dữ liệu người chơi.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="bxh-pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="bxh-page-link">Trang trước</a>
            <?php endif; ?>
            <span>Trang <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="bxh-page-link">Trang sau</a>
            <?php endif; ?>
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

        .bxh-container {
            max-width: 1200px;
            margin: 20px auto;
        }

        .bxh-welcome {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .bxh-welcome h2 {
            color: #2c3e50;
            margin: 0 0 10px;
        }
        .bxh-welcome p {
            color: #34495e;
            font-size: 1.1em;
        }

        .bxh-title {
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

        .bxh-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .bxh-table th, .bxh-table td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: center;
        }
        .bxh-table th {
            background: #f4f4f4;
            color: #333;
            font-weight: 600;
        }
        .bxh-top-1 {
            color: #e74c3c; /* Đỏ */
            font-weight: bold;
        }
        .bxh-top-2 {
            color: #27ae60; /* Xanh lá */
            font-weight: bold;
        }
        .bxh-top-3 {
            color: #f1c40f; /* Vàng */
            font-weight: bold;
        }

        .bxh-pagination {
            text-align: center;
            margin-top: 20px;
        }
        .bxh-page-link {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 5px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s ease;
        }
        .bxh-page-link:hover {
            background: #2980b9;
        }
    </style>
</body>
</html>