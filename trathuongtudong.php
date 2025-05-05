<?php
// Kết nối cơ sở dữ liệu qua file config.php
require_once 'config.php';

// Bắt đầu session (nếu cần)
session_start();

// Thiết lập múi giờ GMT+7
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Hàm cập nhật bảng timeupdate
function updateTimeLog($conn) {
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i:s');

    try {
        $stmt = $conn->prepare("SELECT number FROM timeupdate WHERE date = :date LIMIT 1");
        $stmt->bindParam(':date', $currentDate);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $newNumber = $row['number'] + 1;
            $updateStmt = $conn->prepare("UPDATE timeupdate SET time = :time, number = :number WHERE date = :date");
            $updateStmt->bindParam(':time', $currentTime);
            $updateStmt->bindParam(':number', $newNumber);
            $updateStmt->bindParam(':date', $currentDate);
            $updateStmt->execute();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO timeupdate (date, time, number) VALUES (:date, :time, 1)");
            $insertStmt->bindParam(':date', $currentDate);
            $insertStmt->bindParam(':time', $currentTime);
            $insertStmt->execute();
        }

        $thresholdDate = date('Y-m-d', strtotime('-7 days'));
        $deleteStmt = $conn->prepare("DELETE FROM timeupdate WHERE date < :threshold");
        $deleteStmt->bindParam(':threshold', $thresholdDate);
        $deleteStmt->execute();
        error_log("Đã xóa các bản ghi trong timeupdate trước $thresholdDate");
    } catch (PDOException $e) {
        error_log("Lỗi khi cập nhật bảng timeupdate: " . $e->getMessage());
    }
}

// Hàm kiểm tra và lấy dữ liệu
function getData($conn, $currentDate) {
    $stmt = $conn->prepare("SELECT link, file FROM setting LIMIT 1");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$setting) {
        return ['error' => 'Không tìm thấy thông tin trong bảng setting'];
    }

    $link = $setting['link'];
    $localFile = $setting['file'];

    $jsonData = null;
    $ch = curl_init($link);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response !== false) {
        $jsonData = $response;
    } else {
        if (file_exists($localFile)) {
            $jsonData = file_get_contents($localFile);
        } else {
            return ['error' => "Không thể truy cập link ($link) và file local ($localFile) không tồn tại"];
        }
    }

    if ($jsonData === null || $jsonData === false) {
        return ['error' => 'Không thể lấy dữ liệu từ link hoặc file'];
    }

    $dataArray = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['error' => 'Lỗi giải mã JSON: ' . json_last_error_msg()];
    }

    if (!is_array($dataArray)) {
        return ['error' => 'Dữ liệu không phải là mảng JSON', 'raw_data' => $dataArray];
    }

    $currentData = null;
    foreach ($dataArray as $item) {
        if (!isset($item['date'])) {
            continue;
        }
        $dataDate = new DateTime($item['date']);
        $dataDateStr = $dataDate->format('Y-m-d');
        if ($dataDateStr === $currentDate) {
            $currentData = $item;
            break;
        }
    }

    if ($currentData === null) {
        return ['error' => "Không tìm thấy dữ liệu cho ngày ($currentDate) trong mảng", 'raw_data' => $dataArray];
    }

    $requiredFields = [
        'date', 'special', 'prize1', 'prize2_1', 'prize2_2', 'prize3_1', 'prize3_2', 'prize3_3', 'prize3_4',
        'prize3_5', 'prize3_6', 'prize4_1', 'prize4_2', 'prize4_3', 'prize4_4', 'prize5_1', 'prize5_2',
        'prize5_3', 'prize5_4', 'prize5_5', 'prize5_6', 'prize6_1', 'prize6_2', 'prize6_3', 'prize7_1',
        'prize7_2', 'prize7_3', 'prize7_4'
    ];
    $missingFields = [];
    foreach ($requiredFields as $field) {
        if (!isset($currentData[$field])) {
            $missingFields[] = $field;
        }
    }

    if (!empty($missingFields)) {
        return [
            'error' => 'Dữ liệu thiếu các trường bắt buộc: ' . implode(', ', $missingFields),
            'raw_data' => $currentData
        ];
    }

    return $currentData;
}

// Hàm chuẩn hóa số
function standardizeNumber($number, $length) {
    $numberStr = strval($number);
    return str_pad($numberStr, $length, '0', STR_PAD_LEFT);
}

// Hàm lấy 2 số cuối hoặc 3 số cuối
function getLastNumbers($number, $digits) {
    return substr(strval($number), -$digits);
}

// Hàm hiển thị kết quả quay số
function displayResults($data) {
    $output = "<h2>Kết quả quay số ngày " . (new DateTime($data['date']))->format('d/m/Y') . "</h2>";
    $output .= "<table border='1' style='margin: 20px auto; border-collapse: collapse; width: 80%;'>";
    $output .= "<tr><th>Giải</th><th>Số trúng thưởng</th></tr>";
    $output .= "<tr><td>Đặc biệt</td><td>" . standardizeNumber($data['special'], 5) . "</td></tr>";
    $output .= "<tr><td>Giải nhất</td><td>" . standardizeNumber($data['prize1'], 5) . "</td></tr>";
    $output .= "<tr><td>Giải nhì</td><td>" . standardizeNumber($data['prize2_1'], 5) . " - " . standardizeNumber($data['prize2_2'], 5) . "</td></tr>";
    $output .= "<tr><td>Giải ba</td><td>" . implode(' - ', array_map(function($num) { return standardizeNumber($num, 5); }, [
        $data['prize3_1'], $data['prize3_2'], $data['prize3_3'], $data['prize3_4'], $data['prize3_5'], $data['prize3_6']
    ])) . "</td></tr>";
    $output .= "<tr><td>Giải tư</td><td>" . implode(' - ', array_map(function($num) { return standardizeNumber($num, 4); }, [
        $data['prize4_1'], $data['prize4_2'], $data['prize4_3'], $data['prize4_4']
    ])) . "</td></tr>";
    $output .= "<tr><td>Giải năm</td><td>" . implode(' - ', array_map(function($num) { return standardizeNumber($num, 4); }, [
        $data['prize5_1'], $data['prize5_2'], $data['prize5_3'], $data['prize5_4'], $data['prize5_5'], $data['prize5_6']
    ])) . "</td></tr>";
    $output .= "<tr><td>Giải sáu</td><td>" . implode(' - ', array_map(function($num) { return standardizeNumber($num, 3); }, [
        $data['prize6_1'], $data['prize6_2'], $data['prize6_3']
    ])) . "</td></tr>";
    $output .= "<tr><td>Giải bảy</td><td>" . implode(' - ', array_map(function($num) { return standardizeNumber($num, 2); }, [
        $data['prize7_1'], $data['prize7_2'], $data['prize7_3'], $data['prize7_4']
    ])) . "</td></tr>";
    $output .= "</table>";
    return $output;
}

// Hàm xử lý trả thưởng tự động
function processAutoReward($conn) {
    $currentDate = new DateTime();
    $thresholdDate91 = $currentDate->modify('-91 days')->format('Y-m-d');
    $thresholdDate2 = (clone $currentDate)->modify('-2 days')->format('Y-m-d');

    $deleteCuocStmt = $conn->prepare("DELETE FROM cuoc WHERE date < :threshold");
    $deleteCuocStmt->bindParam(':threshold', $thresholdDate91);
    $deleteCuocStmt->execute();
    error_log("Đã xóa các bản ghi trong cuoc trước $thresholdDate91");

    $cancelStmt = $conn->prepare("SELECT id, user, money FROM cuoc WHERE status = 'NEW' AND date <= :threshold");
    $cancelStmt->bindParam(':threshold', $thresholdDate2);
    $cancelStmt->execute();
    $oldBets = $cancelStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldBets as $bet) {
        $betId = $bet['id'];
        $username = $bet['user'];
        $betMoney = $bet['money'];

        if (empty($username) || $betMoney <= 0) {
            $updateBet = $conn->prepare("UPDATE cuoc SET status = 'CANCELLED' WHERE id = :id");
            $updateBet->bindParam(':id', $betId);
            $updateBet->execute();
            continue;
        }

        $conn->beginTransaction();
        try {
            $updateUserMoney = $conn->prepare("UPDATE users SET money = money + :money WHERE username = :username");
            $updateUserMoney->bindParam(':money', $betMoney, PDO::PARAM_STR);
            $updateUserMoney->bindParam(':username', $username);
            $updateUserMoney->execute();

            if ($updateUserMoney->rowCount() == 0) {
                throw new Exception("Không tìm thấy user: $username trong bảng users hoặc không cập nhật được money.");
            }

            $updateBet = $conn->prepare("UPDATE cuoc SET status = 'CANCELLED' WHERE id = :id");
            $updateBet->bindParam(':id', $betId);
            $updateBet->execute();

            $conn->commit();
            error_log("Đã hủy và hoàn tiền $betMoney cho user $username (Bet ID: $betId)");
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Lỗi hủy cược Bet ID $betId: " . $e->getMessage());
        }
    }

    $stmt = $conn->prepare("SELECT DISTINCT date FROM cuoc WHERE status = 'NEW' ORDER BY date ASC");
    $stmt->execute();
    $dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dates)) {
        return ['message' => 'Không có cược nào với status NEW để xử lý.', 'results' => ''];
    }

    $messages = [];
    $results = '';

    foreach ($dates as $date) {
        $checkStmt = $conn->prepare("SELECT status FROM xuly WHERE date = :date AND status = 'OK' LIMIT 1");
        $checkStmt->bindParam(':date', $date);
        $checkStmt->execute();
        $processed = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($processed) {
            $messages[] = "Ngày $date: Đã xử lý xong kết quả ngày hôm nay. Chờ hôm sau.";
            continue;
        }

        $data = getData($conn, $date);
        if (isset($data['error'])) {
            $messages[] = "Ngày $date: " . $data['error'];
            continue;
        }

        $dataDate = new DateTime($data['date']);
        $dataDateStr = $dataDate->format('Y-m-d');

        $prizes = [
            'special' => standardizeNumber($data['special'], 5),
            'prize1' => standardizeNumber($data['prize1'], 5),
            'prize2' => [standardizeNumber($data['prize2_1'], 5), standardizeNumber($data['prize2_2'], 5)],
            'prize3' => [
                standardizeNumber($data['prize3_1'], 5), standardizeNumber($data['prize3_2'], 5),
                standardizeNumber($data['prize3_3'], 5), standardizeNumber($data['prize3_4'], 5),
                standardizeNumber($data['prize3_5'], 5), standardizeNumber($data['prize3_6'], 5)
            ],
            'prize4' => [
                standardizeNumber($data['prize4_1'], 4), standardizeNumber($data['prize4_2'], 4),
                standardizeNumber($data['prize4_3'], 4), standardizeNumber($data['prize4_4'], 4)
            ],
            'prize5' => [
                standardizeNumber($data['prize5_1'], 4), standardizeNumber($data['prize5_2'], 4),
                standardizeNumber($data['prize5_3'], 4), standardizeNumber($data['prize5_4'], 4),
                standardizeNumber($data['prize5_5'], 4), standardizeNumber($data['prize5_6'], 4)
            ],
            'prize6' => [
                standardizeNumber($data['prize6_1'], 3), standardizeNumber($data['prize6_2'], 3),
                standardizeNumber($data['prize6_3'], 3)
            ],
            'prize7' => [
                standardizeNumber($data['prize7_1'], 2), standardizeNumber($data['prize7_2'], 2),
                standardizeNumber($data['prize7_3'], 2), standardizeNumber($data['prize7_4'], 2)
            ]
        ];

        $loNumbers = [];
        foreach ($prizes as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $num) {
                    $loNumbers[] = getLastNumbers($num, 2);
                }
            } else {
                $loNumbers[] = getLastNumbers($value, 2);
            }
        }

        $result = processBets($conn, $dataDateStr, $prizes, $loNumbers);
        $messages[] = "Ngày $dataDateStr: $result";
        $results .= displayResults($data);
    }

    return ['message' => implode('<br>', $messages), 'results' => $results];
}

// Hàm xử lý trả thưởng cho từng ngày
function processBets($conn, $dataDateStr, $prizes, $loNumbers) {
    $stmt = $conn->prepare("SELECT * FROM cuoc WHERE status = 'NEW' AND date = :date");
    $stmt->bindParam(':date', $dataDateStr);
    $stmt->execute();
    $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $tyleStmt = $conn->prepare("SELECT * FROM tyle LIMIT 1");
    $tyleStmt->execute();
    $tyle = $tyleStmt->fetch(PDO::FETCH_ASSOC);

    $giatienStmt = $conn->prepare("SELECT * FROM giatien LIMIT 1");
    $giatienStmt->execute();
    $giatien = $giatienStmt->fetch(PDO::FETCH_ASSOC);

    $settingStmt = $conn->prepare("SELECT fee FROM setting LIMIT 1");
    $settingStmt->execute();
    $setting = $settingStmt->fetch(PDO::FETCH_ASSOC);
    $feeRate = $setting['fee'] ?? 0.02;

    $processedIds = [];
    $totalDailyFee = 0;

    foreach ($bets as $bet) {
        $username = $bet['user'];
        $betId = $bet['id'];
        $betMoney = $bet['money'];

        if (empty($username)) {
            error_log("Bet ID: $betId có user rỗng hoặc null. Dữ liệu bản ghi: " . json_encode($bet));
            $updateBet = $conn->prepare("UPDATE cuoc SET status = 'LOSE' WHERE id = :id");
            $updateBet->bindParam(':id', $betId);
            $updateBet->execute();
            $processedIds[] = $betId;
            continue;
        }

        $totalWin = 0;
        $totalFee = 0;
        $winningNumbers = [];

        $conn->beginTransaction();

        try {
            $types = ['lo', 'loxien2', 'loxien3', 'loxien4', 'loxien5', 'loxien6', 'de', 'de3cang'];
            foreach ($types as $type) {
                if (!empty($bet[$type])) {
                    $entries = explode(';', $bet[$type]); // Tách các số trong cột (ví dụ: "24-89(1);12-34(2)")
                    foreach ($entries as $entry) {
                        if (empty($entry)) continue;

                        // Tách số và điểm từ định dạng "số(điểm)"
                        preg_match('/^(.+?)\s*\((\d+)\)$/', $entry, $matches);
                        if (count($matches) < 3) continue;
                        $betNumbers = $matches[1];
                        $points = (int)$matches[2];

                        $winAmount = 0;
                        $times = 0;

                        if ($type === 'lo') {
                            $betNumber = trim($betNumbers); // Lấy số lô (ví dụ: "12")
                            $times = array_count_values($loNumbers)[$betNumber] ?? 0; // Đếm số lần trúng
                            if ($times > 0) {
                                $winAmount = $points * $tyle['lo'] * $giatien['lo'] * $times;
                                $winningNumbers[] = "$betNumber($times)"; // Ghi lại số trúng và số lần trúng
                            }
                        } elseif ($type === 'de') {
                            if ($betNumbers === getLastNumbers($prizes['special'], 2)) {
                                $times = 1;
                                $winAmount = $points * $tyle['de'] * $giatien['de'];
                                $winningNumbers[] = $betNumbers;
                            }
                        } elseif ($type === 'de3cang') {
                            if ($betNumbers === getLastNumbers($prizes['special'], 3)) {
                                $times = 1;
                                $winAmount = $points * $tyle['de3cang'] * $giatien['de3cang'];
                                $winningNumbers[] = $betNumbers;
                            }
                        } elseif (strpos($type, 'loxien') === 0) {
                            $betArray = explode('-', $betNumbers); // Tách các số trong bộ xiên
                            $betCount = count($betArray); // Số lượng số trong bộ xiên
                            $matches = array_intersect($betArray, $loNumbers); // Các số trùng với kết quả

                            // Kiểm tra xem tất cả số trong bộ xiên có xuất hiện trong kết quả không
                            if (count($matches) == $betCount) {
                                // Đếm số lần xuất hiện của từng số trong $loNumbers
                                $loCounts = array_count_values($loNumbers);
                                $minTimes = PHP_INT_MAX;

                                // Tìm số lần xuất hiện thấp nhất của các số trong bộ xiên
                                foreach ($betArray as $num) {
                                    $count = $loCounts[$num] ?? 0;
                                    $minTimes = min($minTimes, $count);
                                }

                                // Số lần trúng là số lần xuất hiện thấp nhất, tối thiểu là 1
                                $times = max(1, $minTimes);
                                $winAmount = $points * $tyle[$type] * $giatien[$type] * $times; // Tính tiền thưởng dựa trên số lần trúng
                                $winningNumbers[] = "$betNumbers($times)"; // Ghi lại bộ số trúng và số lần trúng
                            }
                        }

                        if ($winAmount > 0) {
                            $fee = $winAmount * $feeRate;
                            $totalWin += $winAmount;
                            $totalFee += $fee;
                        }
                    }
                }
            }

            if ($totalWin > 0) {
                $moneyToAdd = $totalWin * (1 - $feeRate);
                $totalDailyFee += $totalFee;

                $updateUserMoney = $conn->prepare("UPDATE users SET money = money + :money, win = win + 1 WHERE username = :username");
                $updateUserMoney->bindParam(':money', $moneyToAdd, PDO::PARAM_STR);
                $updateUserMoney->bindParam(':username', $username);
                $updateUserMoney->execute();

                if ($updateUserMoney->rowCount() == 0) {
                    throw new Exception("Không tìm thấy user: $username trong bảng users hoặc không cập nhật được money/win.");
                }

                $winStatus = "WIN (" . implode(',', $winningNumbers) . ")";
                $updateBet = $conn->prepare("UPDATE cuoc SET ketqua = :fee, status = :status WHERE id = :id");
                $updateBet->bindParam(':fee', $totalFee, PDO::PARAM_STR);
                $updateBet->bindParam(':status', $winStatus);
                $updateBet->bindParam(':id', $betId);
                $updateBet->execute();
            } else {
                $updateBet = $conn->prepare("UPDATE cuoc SET status = 'LOSE' WHERE id = :id");
                $updateBet->bindParam(':id', $betId);
                $updateBet->execute();
            }

            $conn->commit();
            $processedIds[] = $betId;
        } catch (Exception $e) {
            $conn->rollBack();
            error_log("Lỗi xử lý Bet ID $betId: " . $e->getMessage());
            continue;
        }
    }

    if (!empty($processedIds)) {
        recordProcessing($conn, $dataDateStr, $processedIds, 'OK', $totalDailyFee);
        return "Đã xử lý trả thưởng cho ngày $dataDateStr.";
    }
    return "Không có cược nào để xử lý cho ngày $dataDateStr.";
}

// Hàm ghi log xử lý
function recordProcessing($conn, $date, $ids, $status, $totalFee) {
    $time = date('H:i:s');
    $idList = implode(';', $ids);
    $stmt = $conn->prepare("INSERT INTO xuly (date, time, id_list, status, money) VALUES (:date, :time, :id_list, :status, :money)");
    $stmt->bindParam(':date', $date);
    $stmt->bindParam(':time', $time);
    $stmt->bindParam(':id_list', $idList);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':money', $totalFee, PDO::PARAM_STR);
    $stmt->execute();
}

updateTimeLog($conn);
$result = processAutoReward($conn);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xử lý trả thưởng tự động</title>
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .container {
            text-align: center;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
            max-width: 800px;
            width: 100%;
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
            font-size: 18px;
            color: #3498db;
            margin: 20px 0;
        }

        .loading::after {
            content: '';
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #3498db;
            border-top: 3px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            vertical-align: middle;
            margin-left: 10px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        table {
            border: 1px solid #ccc;
            margin: 20px auto;
            width: 80%;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ccc;
        }

        th {
            background: #f0f0f0;
        }

        pre {
            text-align: left;
            background: #f9f9f9;
            padding: 10px;
            border-radius: 5px;
            max-height: 200px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Xử lý trả thưởng tự động</h1>
        <div id="loading" class="loading">Đang xử lý...</div>
        <div id="results"><?php echo $result['results'] ?? ''; ?></div>
        <div id="message"><?php echo $result['message']; ?></div>
        <a href="index.php" class="nut">Quay lại trang chủ</a>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loading = document.getElementById('loading');
            const results = document.getElementById('results');
            const message = document.getElementById('message');
            
            loading.style.display = 'block';
            results.style.display = 'none';
            message.style.display = 'none';
            
            setTimeout(() => {
                loading.style.display = 'none';
                results.style.display = 'block';
                message.style.display = 'block';
            }, 2000);
        });
    </script>
</body>
</html>