<?php
require_once 'config.php'; // Kết nối database

// Thiết lập múi giờ
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Hàm kiểm tra trạng thái file
function checkFileStatus($conn) {
    $stmt = $conn->prepare("SELECT link, file FROM setting LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        return "Không tìm thấy thông tin trong bảng setting.";
    }

    $link = $setting['link'];
    $localFile = $setting['file'];

    // Kiểm tra link trực tuyến
    $ch = curl_init($link);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $linkStatus = ($httpCode == 200) ? "<span style='color: green;'>Hoạt động</span>" : "<span style='color: red;'>Không hoạt động</span>";

    // Kiểm tra file trên hosting
    $fileStatus = file_exists($localFile) ? "<span style='color: green;'>Tồn tại</span>" : "<span style='color: red;'>Không tồn tại</span>";

    // Ưu tiên lấy dữ liệu từ link, nếu không được thì lấy từ file
    if ($httpCode == 200) {
        $jsonData = file_get_contents($link);
    } else {
        $jsonData = file_exists($localFile) ? file_get_contents($localFile) : null;
    }

    if (!$jsonData) {
        return "Không thể lấy dữ liệu từ link ($linkStatus) hoặc file ($fileStatus).";
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return "Lỗi giải mã JSON: " . json_last_error_msg();
    }

    // Phân tích ngày bắt đầu và kết thúc
    $startDate = new DateTime($data[0]['date']);
    $endDate = new DateTime(end($data)['date']);
    $startDateFormatted = $startDate->format('d-m-Y');
    $endDateFormatted = $endDate->format('d-m-Y');

    // Trả về chuỗi thông tin hoàn chỉnh
    return "DATA trực tuyến: $linkStatus | DATA trên máy chủ: $fileStatus<br>Dữ liệu từ: <span class='date-green'>$startDateFormatted</span> đến <span class='date-green'>$endDateFormatted</span>";
}

// Hàm lấy thông tin xử lý trả thưởng
function getRewardProcessingInfo($conn) {
    $stmt = $conn->prepare("SELECT date FROM xuly WHERE status = 'ok' ORDER BY date DESC LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Lấy tổng số tiền từ cột money trong bảng xuly
    $totalMoneyStmt = $conn->prepare("SELECT SUM(money) as total FROM xuly");
    $totalMoneyStmt->execute();
    $totalMoney = $totalMoneyStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0; // Nếu không có dữ liệu thì trả về 0

    if ($result) {
        $date = new DateTime($result['date']);
        $dateFormatted = $date->format('d-m-Y');
        return "Đã xử lý trả thưởng kết quả quay ngày <span class='date-green-bold'>$dateFormatted</span><br><span style='font-size: 19px;'>Tiền tích lũy (2% trả thưởng): <span class='money-red-bold'>" . number_format($totalMoney) . " VNĐ</span></span>";
    }
    return "Chưa có thông tin xử lý trả thưởng.<br><span style='font-size: 18px;'>Tiền tích lũy (2% trả thưởng): <span class='money-red-bold'>" . number_format($totalMoney) . " VNĐ</span></span>";
}

// Hàm lấy kết quả xổ số mới nhất
function getLatestResult($conn) {
    $stmt = $conn->prepare("SELECT link, file FROM setting LIMIT 1"); // Thêm link vào truy vấn
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        return null;
    }

    $link = $setting['link'];
    $localFile = $setting['file'];

    // Ưu tiên lấy dữ liệu từ link, nếu không được thì lấy từ file
    $ch = curl_init($link);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        $jsonData = file_get_contents($link);
    } else {
        $jsonData = file_exists($localFile) ? file_get_contents($localFile) : null;
    }

    if (!$jsonData) {
        return null;
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    return end($data); // Lấy kết quả cuối cùng
}

// Hàm định dạng số với độ dài cố định
function padNumber($number, $length) {
    return str_pad($number, $length, '0', STR_PAD_LEFT);
}

?>

<!DOCTYPE html>
<html>
<head>
    <?php include 'head.php'; ?>
    <title>Trang Chủ</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
        }
        h2 {
            text-align: center;
            color: #333;
        }
        h3 {
            color: #333;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }
        th {
            background: #f4f4f4;
            color: #333;
        }
        .info-section {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            margin: 20px 0;
        }
        .data-info {
            width: 50%;
            text-align: left;
            font-size: 18px;
        }
        .notes {
            width: 50%;
            text-align: left;
            font-size: 22px;
            color: #300eed;
            border: 1px solid #ddd;
        }
        .two-columns {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }
        .two-columns > div {
            width: 48%;
        }
        .nut {
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 10px 0;
            display: inline-block;
        }
        .nut:hover {
            background: rgba(52, 152, 219, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .loading {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #3498db;
            text-align: center;
            z-index: 1000;
        }
        .loading::after {
            content: '';
            display: block;
            width: 30px;
            height: 30px;
            border: 4px solid #3498db;
            border-top: 4px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .bold-green {
            font-weight: bold;
            color: #006400;
        }
        .bold {
            font-weight: bold;
        }
        .top-1 {
            color: #ff0000;
            font-weight: bold;
        }
        .top-2 {
            color: #008000;
            font-weight: bold;
        }
        .top-3 {
            color: #ffa500;
            font-weight: bold;
        }
        .date-green {
            color: #27ae60;
        }
        .date-green-bold {
            color: #27ae60;
            font-weight: bold;
        }
        .money-red-bold {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-new {
            color: #8e44ad;
            font-weight: bold;
        }
        .status-win {
            color: #27ae60;
            font-weight: bold;
        }
        .status-lose {
            color: #e74c3c;
            font-weight: bold;
        }
        .status-cancelled {
            font-weight: bold;
        }
        .cancelled-row {
            text-decoration: line-through;
        }
    </style>
</head>
<body>
    <div id="loading" class="loading">Đang xử lý...</div>
    <div class="container">
        <!-- Phần thông tin và chú thích -->
        <div class="info-section">
            <div class="data-info">
                <h3>Thông tin dữ liệu</h3>
                <?php echo checkFileStatus($conn); ?>
                <br>
                <h3>Thông Tin Xử Lý Trả Thưởng Tự Động</h3>
                <?php echo getRewardProcessingInfo($conn); ?>
            </div>
            <div class="notes">
                <p>Tạo tài khoản mới có <b>10,000,000 Vnđ</b> trong tài khoản</p>
                <p>Hệ thống đánh lô, đề tự động vui, không mất tiền.</p>
                <p>Test nhân phẩm cá nhân của bạn xem may mắn đến đâu</p>
            </div>
        </div>

        <!-- Bảng tỷ lệ chơi game -->
        <h2>Tỷ Lệ Chơi Game</h2>
        <?php
        $giatienStmt = $conn->prepare("SELECT lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM giatien LIMIT 1");
        $giatienStmt->execute();
        $giatien = $giatienStmt->fetch(PDO::FETCH_ASSOC);

        $tyleStmt = $conn->prepare("SELECT lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM tyle LIMIT 1");
        $tyleStmt->execute();
        $tyle = $tyleStmt->fetch(PDO::FETCH_ASSOC);
        ?>
        <table>
            <tr>
                <th>Tên</th>
                <th>Lô</th>
                <th>Lô Xiên 2</th>
                <th>Lô Xiên 3</th>
                <th>Lô Xiên 4</th>
                <th>Lô Xiên 5</th>
                <th>Lô Xiên 6</th>
                <th>Đề</th>
                <th>Đề 3 Càng</th>
            </tr>
            <tr>
                <td>Giá tiền</td>
                <td><?php echo number_format($giatien['lo']); ?></td>
                <td><?php echo number_format($giatien['loxien2']); ?></td>
                <td><?php echo number_format($giatien['loxien3']); ?></td>
                <td><?php echo number_format($giatien['loxien4']); ?></td>
                <td><?php echo number_format($giatien['loxien5']); ?></td>
                <td><?php echo number_format($giatien['loxien6']); ?></td>
                <td><?php echo number_format($giatien['de']); ?></td>
                <td><?php echo number_format($giatien['de3cang']); ?></td>
            </tr>
            <tr>
                <td>Tỷ lệ</td>
                <td class="bold-green"><?php echo $tyle['lo']; ?></td>
                <td class="bold-green"><?php echo $tyle['loxien2']; ?></td>
                <td class="bold-green"><?php echo $tyle['loxien3']; ?></td>
                <td class="bold-green"><?php echo $tyle['loxien4']; ?></td>
                <td class="bold-green"><?php echo $tyle['loxien5']; ?></td>
                <td class="bold-green"><?php echo $tyle['loxien6']; ?></td>
                <td class="bold-green"><?php echo $tyle['de']; ?></td>
                <td class="bold-green"><?php echo $tyle['de3cang']; ?></td>
            </tr>
        </table>

        <!-- Hai bảng song song -->
        <div class="two-columns">
            <!-- Top người chơi -->
            <div>
                <h2>Top Người Chơi Nhiều Tiền Nhất</h2>
                <?php
                $topUsersStmt = $conn->prepare("SELECT username, money, luot_choi, win FROM users ORDER BY money DESC LIMIT 10");
                $topUsersStmt->execute();
                $topUsers = $topUsersStmt->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <table>
                    <tr>
                        <th>STT</th>
                        <th>Tên</th>
                        <th>Số Tiền</th>
                        <th>Lượt Chơi</th>
                        <th>Lần Thắng</th>
                    </tr>
                    <?php foreach ($topUsers as $index => $user): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td class="<?php 
                                if ($index == 0) echo 'top-1'; 
                                elseif ($index == 1) echo 'top-2'; 
                                elseif ($index == 2) echo 'top-3'; 
                                ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                            </td>
                            <td><?php echo number_format($user['money']); ?></td>
                            <td><?php echo $user['luot_choi']; ?></td>
                            <td><?php echo $user['win']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>

            <!-- Kết quả xổ số mới nhất -->
            <div>
                <h2>Kết Quả Xổ Số Mới Nhất</h2>
                <?php
                $latestResult = getLatestResult($conn);
                if ($latestResult):
                    $date = new DateTime($latestResult['date']);
                ?>
                <table>
                    <tr><th colspan="2">Ngày: <span class='date-green'><?php echo $date->format('d-m-Y'); ?></span></th></tr>
                    <tr><td>Đặc biệt</td><td class="bold"><?php echo padNumber($latestResult['special'], 5); ?></td></tr>
                    <tr><td>Giải nhất</td><td class="bold"><?php echo padNumber($latestResult['prize1'], 5); ?></td></tr>
                    <tr><td>Giải nhì</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 5); }, [$latestResult['prize2_1'], $latestResult['prize2_2']])); ?></td></tr>
                    <tr><td>Giải ba</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 5); }, [$latestResult['prize3_1'], $latestResult['prize3_2'], $latestResult['prize3_3'], $latestResult['prize3_4'], $latestResult['prize3_5'], $latestResult['prize3_6']])); ?></td></tr>
                    <tr><td>Giải tư</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 4); }, [$latestResult['prize4_1'], $latestResult['prize4_2'], $latestResult['prize4_3'], $latestResult['prize4_4']])); ?></td></tr>
                    <tr><td>Giải năm</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 4); }, [$latestResult['prize5_1'], $latestResult['prize5_2'], $latestResult['prize5_3'], $latestResult['prize5_4'], $latestResult['prize5_5'], $latestResult['prize5_6']])); ?></td></tr>
                    <tr><td>Giải sáu</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 3); }, [$latestResult['prize6_1'], $latestResult['prize6_2'], $latestResult['prize6_3']])); ?></td></tr>
                    <tr><td>Giải bảy</td><td class="bold"><?php echo implode(' - ', array_map(function($num) { return padNumber($num, 2); }, [$latestResult['prize7_1'], $latestResult['prize7_2'], $latestResult['prize7_3'], $latestResult['prize7_4']])); ?></td></tr>
                </table>
                <?php else: ?>
                    <p>Không có dữ liệu kết quả xổ số.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Lượt chơi mới -->
        <h2>Lượt Chơi Mới</h2>
        <?php
        $newBetsStmt = $conn->prepare("SELECT user, time, lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM cuoc WHERE status = 'NEW' ORDER BY time DESC LIMIT 10");
        $newBetsStmt->execute();
        $newBets = $newBetsStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <table>
            <tr>
                <th>Tên</th>
                <th>Thời Gian</th>
                <th>Con Số</th>
            </tr>
            <?php foreach ($newBets as $bet): ?>
                <tr>
                    <td><?php echo htmlspecialchars($bet['user']); ?></td>
                    <td><?php echo (new DateTime($bet['time']))->format('d-m-Y H:i:s'); ?></td>
                    <td>
                        <?php
                        $numbers = [];
                        if ($bet['lo']) $numbers[] = "Lô: " . $bet['lo'];
                        if ($bet['loxien2']) $numbers[] = "Lô xiên 2: " . $bet['loxien2'];
                        if ($bet['loxien3']) $numbers[] = "Lô xiên 3: " . $bet['loxien3'];
                        if ($bet['loxien4']) $numbers[] = "Lô xiên 4: " . $bet['loxien4'];
                        if ($bet['loxien5']) $numbers[] = "Lô xiên 5: " . $bet['loxien5'];
                        if ($bet['loxien6']) $numbers[] = "Lô xiên 6: " . $bet['loxien6'];
                        if ($bet['de']) $numbers[] = "Đề: " . $bet['de'];
                        if ($bet['de3cang']) $numbers[] = "Đề 3 càng: " . $bet['de3cang'];
                        echo implode('<br>', $numbers);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>

        <!-- Lịch sử đặt cược -->
        <h2>Lịch Sử Đặt Cược</h2>
        <?php
        $historyStmt = $conn->prepare("SELECT id, user, date, time, lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang, money, status FROM cuoc ORDER BY time DESC LIMIT 10");
        $historyStmt->execute();
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        <table>
            <tr>
                <th>Lượt Chơi</th>
                <th>Người chơi</th>
                <th>Kỳ Quay Thưởng</th>
                <th>Thời Điểm Đặt Cược</th>
                <th>Con Số</th>
                <th>Tổng Đặt Cược</th>
                <th>Trạng Thái</th>
            </tr>
            <?php foreach ($history as $entry): ?>
                <tr class="<?php echo ($entry['status'] === 'CANCELLED') ? 'cancelled-row' : ''; ?>">
                    <td><?php echo ($entry['status'] === 'CANCELLED') ? '<del>' . $entry['id'] . '</del>' : $entry['id']; ?></td>
                    <td><?php echo ($entry['status'] === 'CANCELLED') ? '<del>' . htmlspecialchars($entry['user']) . '</del>' : htmlspecialchars($entry['user']); ?></td>
                    <td><?php echo ($entry['status'] === 'CANCELLED') ? '<del>' . (new DateTime($entry['date']))->format('d-m-Y') . '</del>' : (new DateTime($entry['date']))->format('d-m-Y'); ?></td>
                    <td><?php echo ($entry['status'] === 'CANCELLED') ? '<del>' . (new DateTime($entry['time']))->format('d-m-Y H:i:s') . '</del>' : (new DateTime($entry['time']))->format('d-m-Y H:i:s'); ?></td>
                    <td>
                        <?php
                        $numbers = [];
                        if ($entry['lo']) $numbers[] = "Lô: " . $entry['lo'];
                        if ($entry['loxien2']) $numbers[] = "Lô xiên 2: " . $entry['loxien2'];
                        if ($entry['loxien3']) $numbers[] = "Lô xiên 3: " . $entry['loxien3'];
                        if ($entry['loxien4']) $numbers[] = "Lô xiên 4: " . $entry['loxien4'];
                        if ($entry['loxien5']) $numbers[] = "Lô xiên 5: " . $entry['loxien5'];
                        if ($entry['loxien6']) $numbers[] = "Lô xiên 6: " . $entry['loxien6'];
                        if ($entry['de']) $numbers[] = "Đề: " . $entry['de'];
                        if ($entry['de3cang']) $numbers[] = "Đề 3 càng: " . $entry['de3cang'];
                        $numbersOutput = implode('<br>', $numbers);
                        echo ($entry['status'] === 'CANCELLED') ? '<del>' . $numbersOutput . '</del>' : $numbersOutput;
                        ?>
                    </td>
                    <td><?php echo ($entry['status'] === 'CANCELLED') ? '<del>' . number_format($entry['money']) . '</del>' : number_format($entry['money']); ?></td>
                    <td>
                        <?php
                        switch ($entry['status']) {
                            case 'NEW':
                                echo "<span class='status-new'>Mới</span>";
                                break;
                            case 'WIN':
                                echo "<span class='status-win'>Thắng</span>";
                                break;
                            case 'LOSE':
                                echo "<span class='status-lose'>Thua</span>";
                                break;
                            case 'CANCELLED':
                                echo "<span class='status-cancelled'>Hủy</span>";
                                break;
                            default:
                                echo htmlspecialchars($entry['status']);
                                break;
                        }
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <script>
        function toggleLoading(showLoading = true) {
            const loading = document.getElementById('loading');
            const container = document.querySelector('.container');

            if (showLoading) {
                loading.style.display = 'block';
                container.style.display = 'none';
            } else {
                loading.style.display = 'none';
                container.style.display = 'block';
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            toggleLoading(true); // Hiển thị loading khi tải trang
            setTimeout(() => toggleLoading(false), 100); // Ẩn loading sau 100ms
        });
    </script>
</body>
</html>