<?php
// Kết nối cơ sở dữ liệu
require_once 'config.php'; // Sử dụng biến $conn từ config.php

// Thiết lập số lượng dòng mỗi trang
$rowsPerPage = 30;

// Lấy số trang hiện tại từ URL, mặc định là trang 1
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

// Tính offset cho truy vấn SQL
$offset = ($page - 1) * $rowsPerPage;

// Lấy tổng số giao dịch trong 90 ngày để tính tổng số trang
try {
    $totalStmt = $conn->prepare("SELECT COUNT(*) as total FROM cuoc WHERE time >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
    $totalStmt->execute();
    $totalTransactions = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalTransactions / $rowsPerPage);
} catch (PDOException $e) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}

// Lấy danh sách giao dịch từ bảng cuoc trong 90 ngày
try {
    $stmt = $conn->prepare("
        SELECT id, date, time, user, lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang, money, status, ketqua 
        FROM cuoc 
        WHERE time >= DATE_SUB(NOW(), INTERVAL 90 DAY) 
        ORDER BY time DESC 
        LIMIT :offset, :rowsPerPage
    ");
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':rowsPerPage', $rowsPerPage, PDO::PARAM_INT);
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Lỗi truy vấn cơ sở dữ liệu: " . $e->getMessage();
}

// Hàm xử lý dữ liệu số chơi
function formatBetNumbers($data) {
    if (!$data) return '-';
    $items = explode(';', $data);
    $result = [];
    foreach ($items as $item) {
        if (preg_match('/^([\d;-]+)\((\d+)\)$/', trim($item), $matches)) {
            $numbers = $matches[1]; // Chỉ lấy chuỗi số
            $result[] = $numbers;
        }
    }
    return implode('; ', $result);
}
?>

<!DOCTYPE html>
<html lang="vi">
<?php include 'head.php'; ?>
<title>Lịch sử giao dịch</title>
<body>
    <div class="history-container">
        <h1 class="history-title">Toàn bộ lịch sử chơi</h1>
        <p class="history-note">*Lưu ý: Dữ liệu chỉ được lưu trong vòng 90 ngày.</p>

        <!-- Bảng lịch sử giao dịch -->
        <table class="history-table">
            <thead>
                <tr>
                    <th>Số GD</th>
                    <th>Kỳ quay</th>
                    <th>Giờ chơi</th>
                    <th>Tài khoản</th>
                    <th>Lô</th>
                    <th>Lô xiên<br>2</th>
                    <th>Lô xiên<br>3</th>
                    <th>Lô xiên<br>4</th>
                    <th>Lô xiên<br>5</th>
                    <th>Lô xiên<br>6</th>
                    <th>Đề</th>
                    <th>Đề 3 càng</th>
                    <th>Số tiền</th>
                    <th>Kết quả</th>
                    <th>Phí (2%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($transactions)): ?>
                    <?php foreach ($transactions as $transaction): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                            <td><?php echo (new DateTime($transaction['date']))->format('d-m-Y'); ?></td>
                            <td><?php echo (new DateTime($transaction['time']))->format('d-m-Y H:i:s'); ?></td>
                            <td><?php echo htmlspecialchars($transaction['user']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['lo']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['loxien2']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['loxien3']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['loxien4']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['loxien5']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['loxien6']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['de']); ?></td>
                            <td><?php echo formatBetNumbers($transaction['de3cang']); ?></td>
                            <td><?php echo number_format($transaction['money'], 0, ',', '.'); ?></td>
                            <td>
                                <?php
                                // Hiển thị trạng thái với màu sắc
                                switch ($transaction['status']) {
                                    case 'LOSE':
                                        echo "<span class='history-status-lose'>Thua</span>";
                                        break;
                                    case 'NEW':
                                        echo "<span class='history-status-new'>Mới</span>";
                                        break;
                                    case 'CANCELLED':
                                        echo "<span class='history-status-cancelled'>Hủy</span>";
                                        break;
                                    case 'WIN':
                                        echo "<span class='history-status-win'>Thắng</span>";
                                        break;
                                    default:
                                        echo htmlspecialchars($transaction['status']);
                                        break;
                                }
                                ?>
                            </td>
                            <td><?php echo $transaction['ketqua'] ? number_format($transaction['money'] * 0.02, 0, ',', '.') : '0'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="15">Không có giao dịch nào trong 90 ngày qua.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <!-- Phân trang -->
        <div class="history-pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>" class="history-page-link">Trang trước</a>
            <?php endif; ?>
            <span>Trang <?php echo $page; ?> / <?php echo $totalPages; ?></span>
            <?php if ($page < $totalPages): ?>
                <a href="?page=<?php echo $page + 1; ?>" class="history-page-link">Trang sau</a>
            <?php endif; ?>
        </div>
    </div>

    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 30px 20px; /* Lui xuống 30px cho head */
            min-height: 100vh;
        }

        .history-container {
            max-width: 1400px; /* Giữ chiều rộng vừa phải */
            margin: 0 auto;
        }

        .history-title {
            text-align: center;
            font-size: 2em;
            font-weight: 700;
            margin-bottom: 15px;
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

        .history-note {
            text-align: center;
            color: #e74c3c;
            font-style: italic;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .history-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            font-size: 0.85em;
        }
        .history-table th, .history-table td {
            border: 1px solid #ddd;
            padding: 6px;
            text-align: center;
            min-width: 60px; /* Giảm min-width thêm để nhỏ hơn */
        }
        .history-table th {
            background: #f4f4f4;
            color: #333;
            font-weight: 600;
        }
        .history-table td {
            vertical-align: middle;
        }

        .history-pagination {
            text-align: center;
            margin-top: 15px;
            font-size: 0.9em;
        }
        .history-page-link {
            display: inline-block;
            padding: 6px 12px;
            margin: 0 3px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background 0.3s ease;
        }
        .history-page-link:hover {
            background: #2980b9;
        }

        /* Định dạng màu sắc cho trạng thái */
        .history-status-lose {
            color: #e74c3c; /* Đỏ */
            font-weight: bold;
        }
        .history-status-new {
            color: #8e44ad; /* Tím */
            font-weight: bold;
        }
        .history-status-cancelled {
            color: #f1c40f; /* Vàng */
            font-weight: bold;
        }
        .history-status-win {
            color: #27ae60; /* Xanh lá */
            font-weight: bold;
        }
    </style>
</body>
</html>