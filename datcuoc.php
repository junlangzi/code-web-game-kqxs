<?php
session_start();
require_once 'config.php';

// Chỉ cho phép người dùng đã đăng nhập truy cập
if (!isset($_SESSION['username'])) {
    echo "<div style='text-align: center; margin-top: 20px;'>";
    echo "Vui lòng đăng nhập để đặt cược!";
    echo "<br><br>";
    echo "<button class='nut' onclick='location.href=\"login.php\"'>Đăng nhập</button>";
    echo "</div>";
    exit();
}

$username = $_SESSION['username'];

// Xác định ngày đặt cược
$currentHour = (int)date('H');
$betDate = ($currentHour < 18) ? date('Y-m-d') : date('Y-m-d', strtotime('+1 day'));
$betDateDisplay = date('d/m/Y', strtotime($betDate));

// Lấy thông tin giá tiền từ bảng giatien
try {
    $stmt = $conn->prepare("SELECT lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang FROM giatien");
    $stmt->execute();
    $giatien = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Lỗi truy vấn bảng giatien: " . $e->getMessage();
    exit();
}

// Lấy số tiền hiện tại của user
try {
    $stmt = $conn->prepare("SELECT money FROM users WHERE username = :username");
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $userMoney = $user['money'];
} catch (PDOException $e) {
    echo "Lỗi truy vấn bảng users: " . $e->getMessage();
    exit();
}

// Xử lý đặt cược
if (isset($_POST['bet_submit']) && isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {
    $bets = $_POST['bets'];
    $totalMoney = 0;
    $betData = [
        'lo' => [], 'loxien2' => [], 'loxien3' => [], 'loxien4' => [], 'loxien5' => [], 'loxien6' => [],
        'de' => '', 'de3cang' => ''
    ];

    foreach ($bets as $index => $bet) {
        $type = $bet['type'];
        $points = isset($bet['points']) && $bet['points'] !== '' ? (int)$bet['points'] : null;
        $numbers = array_filter(array_map('trim', [$bet['num1'], $bet['num2'] ?? '', $bet['num3'] ?? '', $bet['num4'] ?? '', $bet['num5'] ?? '', $bet['num6'] ?? '']));

        if (empty($numbers) && $points === null) {
            continue;
        }

        if (empty($numbers) && $points !== null) {
            $error = "Dòng " . ($index + 1) . ": Bạn phải nhập số nếu đã nhập số điểm!";
            break;
        }
        if (!empty($numbers) && $points === null) {
            $error = "Dòng " . ($index + 1) . ": Bạn phải nhập số điểm nếu đã nhập số!";
            break;
        }

        if ($type == 'lo' || $type == 'de') {
            if (count($numbers) != 1 || !preg_match('/^\d{2}$/', $numbers[0])) {
                $error = "Dòng " . ($index + 1) . ": Số $type phải là 2 chữ số!";
                break;
            }
        } elseif ($type == 'de3cang') {
            if (count($numbers) != 1 || !preg_match('/^\d{3}$/', $numbers[0])) {
                $error = "Dòng " . ($index + 1) . ": Số $type phải là 3 chữ số!";
                break;
            }
        } else {
            $requiredCount = (int)substr($type, -1);
            if (count($numbers) != $requiredCount) {
                $error = "Dòng " . ($index + 1) . ": Lô xiên $requiredCount phải chọn đúng $requiredCount số!";
                break;
            } else {
                $uniqueNumbers = array_unique($numbers);
                if (count($uniqueNumbers) != $requiredCount) {
                    $error = "Dòng " . ($index + 1) . ": Các số trong lô xiên $requiredCount không được trùng nhau!";
                    break;
                } else {
                    foreach ($numbers as $num) {
                        if (!preg_match('/^\d{2}$/', $num)) {
                            $error = "Dòng " . ($index + 1) . ": Số trong lô xiên $requiredCount phải là 2 chữ số!";
                            break 2;
                        }
                    }
                }
            }
        }

        $totalMoney += $giatien[$type] * $points;
        
        if ($type == 'lo') {
            $betData['lo'][] = "$numbers[0]($points)";
        } elseif ($type == 'de') {
            $betData['de'] = "$numbers[0]($points)";
        } elseif ($type == 'de3cang') {
            $betData['de3cang'] = "$numbers[0]($points)";
        } else {
            // Xử lý lô xiên với định dạng số-số-số(điểm)
            $betString = implode('-', $numbers) . "($points)";
            $betData[$type][] = $betString;
        }
    }

    // Chuyển các mảng thành chuỗi, cách nhau bằng dấu chấm phẩy
    $betData['lo'] = implode(';', $betData['lo']);
    $betData['loxien2'] = implode(';', $betData['loxien2']);
    $betData['loxien3'] = implode(';', $betData['loxien3']);
    $betData['loxien4'] = implode(';', $betData['loxien4']);
    $betData['loxien5'] = implode(';', $betData['loxien5']);
    $betData['loxien6'] = implode(';', $betData['loxien6']);

    if (isset($error)) {
        echo "<div class='thong-bao error'>$error</div>";
        exit();
    }

    if ($totalMoney > $userMoney) {
        $error = "Số tiền không đủ (" . number_format($totalMoney, 0, ',', '.') . " Vnđ)! Vui lòng nạp thêm hoặc bỏ bớt lựa chọn.";
    } elseif ($totalMoney == 0) {
        $error = "Vui lòng nhập ít nhất một lựa chọn đặt cược hợp lệ!";
    } else {
        $stmt = $conn->query("SELECT id FROM cuoc ORDER BY id DESC LIMIT 1");
        $lastId = $stmt->fetchColumn();
        if (!$lastId) {
            $newId = 'AA00001';
        } else {
            $prefix = substr($lastId, 0, 2);
            $number = (int)substr($lastId, 2);
            if ($number >= 99999) {
                $prefix = chr(ord($prefix[0]) + 1) . 'A';
                $number = 1;
            } else {
                $number++;
            }
            $newId = $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
        }

        try {
            // Bắt đầu transaction để đảm bảo tính toàn vẹn dữ liệu
            $conn->beginTransaction();

            // Thêm cược vào bảng cuoc
            $stmt = $conn->prepare("INSERT INTO cuoc (id, date, user, time, lo, loxien2, loxien3, loxien4, loxien5, loxien6, de, de3cang, money, status, ketqua) 
                VALUES (:id, :date, :user, :time, :lo, :loxien2, :loxien3, :loxien4, :loxien5, :loxien6, :de, :de3cang, :money, 'NEW', '')");
            $stmt->execute([
                ':id' => $newId,
                ':date' => $betDate,
                ':user' => $username,
                ':time' => date('Y-m-d H:i:s'),
                ':lo' => $betData['lo'],
                ':loxien2' => $betData['loxien2'],
                ':loxien3' => $betData['loxien3'],
                ':loxien4' => $betData['loxien4'],
                ':loxien5' => $betData['loxien5'],
                ':loxien6' => $betData['loxien6'],
                ':de' => $betData['de'],
                ':de3cang' => $betData['de3cang'],
                ':money' => $totalMoney
            ]);

            // Trừ tiền từ tài khoản người dùng
            $stmt = $conn->prepare("UPDATE users SET money = money - :money WHERE username = :username");
            $stmt->execute([':money' => $totalMoney, ':username' => $username]);

            // Cộng 1 vào luot_choi
            $stmt = $conn->prepare("UPDATE users SET luot_choi = luot_choi + 1 WHERE username = :username");
            $stmt->execute([':username' => $username]);

            // Commit transaction
            $conn->commit();

            $success = "Đặt cược thành công! ID: $newId";
        } catch (PDOException $e) {
            // Rollback nếu có lỗi
            $conn->rollBack();
            $error = "Lỗi khi đặt cược: " . $e->getMessage();
        }
    }
}

// Xử lý hủy cược
if (isset($_POST['cancel']) && isset($_POST['confirm']) && $_POST['confirm'] == 'yes') {
    $betId = $_POST['bet_id'];
    $currentHour = (int)date('H');

    $stmt = $conn->prepare("SELECT time, money, date FROM cuoc WHERE id = :id AND user = :user AND status = 'NEW'");
    $stmt->execute([':id' => $betId, ':user' => $username]);
    $bet = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bet) {
        $betTime = strtotime($bet['time']);
        $currentTime = time();
        $diffMinutes = ($currentTime - $betTime) / 60;

        if ($diffMinutes <= 30 && !($currentHour >= 18 && $bet['date'] == date('Y-m-d'))) {
            try {
                // Bắt đầu transaction
                $conn->beginTransaction();

                // Cập nhật trạng thái cược thành CANCELLED
                $stmt = $conn->prepare("UPDATE cuoc SET status = 'CANCELLED' WHERE id = :id");
                $stmt->execute([':id' => $betId]);

                // Hoàn tiền cho người dùng
                $stmt = $conn->prepare("UPDATE users SET money = money + :money WHERE username = :username");
                $stmt->execute([':money' => $bet['money'], ':username' => $username]);

                // Trừ 1 khỏi luot_choi
                $stmt = $conn->prepare("UPDATE users SET luot_choi = luot_choi - 1 WHERE username = :username");
                $stmt->execute([':username' => $username]);

                // Commit transaction
                $conn->commit();

                $success = "Đã hủy cược $betId thành công!";
            } catch (PDOException $e) {
                // Rollback nếu có lỗi
                $conn->rollBack();
                $error = "Lỗi khi hủy cược: " . $e->getMessage();
            }
        } else {
            $error = "Không thể hủy cược $betId: Đã quá 30 phút hoặc sau 18h cho cược hôm nay!";
        }
    } else {
        $error = "Không tìm thấy cược $betId hoặc không thể hủy!";
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <?php include 'head.php'; ?>
    <title>Đặt Cược</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .thong-bao {
            background: #f0f0f0;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .success { background: #c6efce; color: #3e8e41; }
        .error { background: #f2dede; color: #a94442; }
        .bet-row { 
            margin-bottom: 15px; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .bet-row label { margin-right: 10px; }
        .number-group { display: inline-block; margin-right: 10px; }
        .number-input { width: 60px; margin-right: 5px; }
        .nut {
            background: rgba(52, 152, 219, 0.9);
            color: white;
            padding: 7px 14px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 10px;
        }
        .nut:hover {
            background: rgba(52, 152, 219, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .delete-btn {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            padding: 5px 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .delete-btn:hover {
            background: rgba(231, 76, 60, 1);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }
        th { background: #f2f2f2; }
        #totalMoney { font-weight: bold; color: red; }
        .popup {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 1000;
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        .popup-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        .popup-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 10px 0; 
        }
        .popup-table th, .popup-table td { 
            border: 1px solid #ddd; 
            padding: 5px; 
            text-align: left; 
        }
        .popup-table th { 
            background: #f2f2f2; 
        }
        .status-cancelled { font-weight: bold; text-decoration: line-through; color: #FFC107; }
        .status-win { font-weight: bold; color: #28A745; }
        .status-lose { font-weight: bold; color: #000000; }
        .status-new { font-weight: bold; color: #6F42C1; }
    </style>
</head>
<body>
    <div class="popup-overlay" id="popupOverlay"></div>
    <div class="popup" id="confirmPopup">
        <h3>Xác nhận</h3>
        <div id="popupContent"></div>
        <button class="nut" id="confirmButton">Xác nhận</button>
        <button class="nut" id="cancelButton">Hủy</button>
    </div>

    <div class="container">
        <h2>Đặt Cược - Đặt cược cho ngày quay thưởng <span style="color: red;"><?php echo $betDateDisplay; ?></span></h2>

        <div class="thong-bao">
            <strong>Thông báo:</strong><br>
            - Từ 00h đến 18h: Chấp nhận đặt cược kết quả ngày hôm đó.<br>
            - Từ 18h đến 24h: Chỉ chấp nhận đặt cược kết quả ngày hôm sau.<br>
            - Có thể hủy đặt cược trong 30 phút sau khi đặt, nhưng sau 18h không hủy được cược của ngày hôm đó.
        </div>

        <?php if (isset($success)) { ?>
            <div class="thong-bao success"><?php echo $success; ?></div>
        <?php } elseif (isset($error)) { ?>
            <div class="thong-bao error"><?php echo $error; ?></div>
        <?php } ?>

        <form method="post" id="betForm">
            <div id="betContainer">
                <div class="bet-row" data-index="0">
                    <label>Loại:</label>
                    <select name="bets[0][type]" class="bet-type" onchange="updateNumberInputs(this, 0)">
                        <option value="lo">Lô</option>
                        <option value="loxien2">Lô xiên 2</option>
                        <option value="loxien3">Lô xiên 3</option>
                        <option value="loxien4">Lô xiên 4</option>
                        <option value="loxien5">Lô xiên 5</option>
                        <option value="loxien6">Lô xiên 6</option>
                        <option value="de">Đề</option>
                        <option value="de3cang">Đề 3 càng</option>
                    </select>
                    <label>Số:</label>
                    <span id="numbers0" class="number-group">
                        <input type="text" class="number-input" name="bets[0][num1]" maxlength="3" onblur="formatNumber(this)" oninput="calculateTotal()">
                    </span>
                    <label>Điểm:</label>
                    <input type="number" name="bets[0][points]" min="1" value="" oninput="calculateTotal()">
                </div>
            </div>
            <div>Tổng tiền: <span id="totalMoney">0</span> Vnđ</div>
            <button type="button" class="nut" onclick="addBet()">Thêm số đặt cược</button>
            <button type="button" class="nut" onclick="showConfirmPopup('submit')">Xác nhận đặt cược</button>
        </form>

        <h3>Lịch sử đặt cược</h3>
        <?php
        $stmt = $conn->prepare("SELECT * FROM cuoc WHERE user = :user ORDER BY time DESC");
        $stmt->execute([':user' => $username]);
        $bets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($bets) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Ngày</th><th>Thời gian</th><th>Tiền</th><th>Trạng thái</th><th>Hành động</th></tr>";
            foreach ($bets as $bet) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($bet['id']) . "</td>";
                echo "<td>" . htmlspecialchars($bet['date']) . "</td>";
                echo "<td>" . htmlspecialchars($bet['time']) . "</td>";
                echo "<td>" . number_format($bet['money'], 0, ',', '.') . " Vnđ</td>";
                echo "<td>";
                switch ($bet['status']) {
                    case 'CANCELLED':
                        echo "<span class='status-cancelled'>Hủy</span>";
                        break;
                    case 'WIN':
                        echo "<span class='status-win'>Thắng</span>";
                        break;
                    case 'LOSE':
                        echo "<span class='status-lose'>Thua</span>";
                        break;
                    case 'NEW':
                        echo "<span class='status-new'>Mới</span>";
                        break;
                    default:
                        echo htmlspecialchars($bet['status']);
                }
                echo "</td>";
                echo "<td>";
                if ($bet['status'] == 'NEW') {
                    $betTime = strtotime($bet['time']);
                    $diffMinutes = (time() - $betTime) / 60;
                    if ($diffMinutes <= 30 && !((int)date('H') >= 18 && $bet['date'] == date('Y-m-d'))) {
                        echo "<form method='post' style='display:inline;'>";
                        echo "<input type='hidden' name='bet_id' value='" . $bet['id'] . "'>";
                        echo "<button type='button' onclick=\"showConfirmPopup('cancel', '" . $bet['id'] . "', " . $bet['money'] . ")\" class='nut'>Hủy</button>";
                        echo "</form>";
                    }
                }
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Chưa có lịch sử đặt cược.</p>";
        }
        ?>
    </div>

    <script>
        let betCount = 1;
        const giatien = <?php echo json_encode($giatien); ?>;
        const typeNames = {
            'lo': 'Lô',
            'loxien2': 'Lô xiên 2',
            'loxien3': 'Lô xiên 3',
            'loxien4': 'Lô xiên 4',
            'loxien5': 'Lô xiên 5',
            'loxien6': 'Lô xiên 6',
            'de': 'Đề',
            'de3cang': 'Đề 3 càng'
        };

        function updateNumberInputs(select, index) {
            const type = select.value;
            const container = document.getElementById(`numbers${index}`);
            let inputs = '';

            if (type === 'lo' || type === 'de') {
                inputs = `<input type="text" class="number-input" name="bets[${index}][num1]" maxlength="3" onblur="formatNumber(this)" oninput="calculateTotal()">`;
            } else if (type === 'de3cang') {
                inputs = `<input type="text" class="number-input" name="bets[${index}][num1]" maxlength="3" onblur="formatNumber(this)" oninput="calculateTotal()">`;
            } else if (type.startsWith('loxien')) {
                const count = parseInt(type.slice(-1));
                for (let i = 1; i <= count; i++) {
                    inputs += `<input type="text" class="number-input" name="bets[${index}][num${i}]" maxlength="3" onblur="formatNumber(this)" oninput="calculateTotal()">`;
                }
            }
            container.innerHTML = inputs;
            calculateTotal();
        }

        function formatNumber(input) {
            let value = input.value.replace(/\D/g, '');
            const type = input.closest('.bet-row').querySelector('.bet-type').value;

            if (value && type === 'de3cang') {
                if (value.length === 1) value = '00' + value;
                else if (value.length === 2) value = '0' + value;
            } else if (value) {
                if (value.length === 1) value = '0' + value;
            }
            input.value = value;
            calculateTotal();
        }

        function addBet() {
            const container = document.getElementById('betContainer');
            const newBet = document.createElement('div');
            newBet.className = 'bet-row';
            newBet.dataset.index = betCount;
            newBet.innerHTML = `
                <label>Loại:</label>
                <select name="bets[${betCount}][type]" class="bet-type" onchange="updateNumberInputs(this, ${betCount})">
                    <option value="lo">Lô</option>
                    <option value="loxien2">Lô xiên 2</option>
                    <option value="loxien3">Lô xiên 3</option>
                    <option value="loxien4">Lô xiên 4</option>
                    <option value="loxien5">Lô xiên 5</option>
                    <option value="loxien6">Lô xiên 6</option>
                    <option value="de">Đề</option>
                    <option value="de3cang">Đề 3 càng</option>
                </select>
                <label>Số:</label>
                <span id="numbers${betCount}" class="number-group">
                    <input type="text" class="number-input" name="bets[${betCount}][num1]" maxlength="3" onblur="formatNumber(this)" oninput="calculateTotal()">
                </span>
                <label>Điểm:</label>
                <input type="number" name="bets[${betCount}][points]" min="1" value="" oninput="calculateTotal()">
                <button type="button" class="delete-btn" onclick="deleteBetRow(this)">Xóa</button>
            `;
            container.appendChild(newBet);
            betCount++;
            toggleDeleteButtons();
            calculateTotal();
        }

        function deleteBetRow(button) {
            const row = button.parentElement;
            row.remove();
            toggleDeleteButtons();
            calculateTotal();
        }

        function toggleDeleteButtons() {
            const rows = document.querySelectorAll('.bet-row');
            rows.forEach(row => {
                const deleteBtn = row.querySelector('.delete-btn');
                if (rows.length <= 1 && deleteBtn) {
                    deleteBtn.style.display = 'none';
                } else if (deleteBtn) {
                    deleteBtn.style.display = 'inline-block';
                }
            });
        }

        function calculateTotal() {
            let total = 0;
            const rows = document.querySelectorAll('.bet-row');
            rows.forEach(row => {
                const type = row.querySelector('select').value;
                const points = parseInt(row.querySelector('input[type="number"]').value) || 0;
                const numbers = Array.from(row.querySelectorAll('.number-input')).map(input => input.value.trim()).filter(val => val !== '');
                if (numbers.length > 0 && points > 0 && giatien[type]) {
                    total += giatien[type] * points;
                }
            });
            document.getElementById('totalMoney').textContent = total.toLocaleString('vi-VN');
            return total;
        }

        function showConfirmPopup(action, betId = '', betMoney = 0) {
            const popup = document.getElementById('confirmPopup');
            const overlay = document.getElementById('popupOverlay');
            const popupContent = document.getElementById('popupContent');
            let htmlContent = '';

            if (action === 'submit') {
                const rows = document.querySelectorAll('.bet-row');
                let hasValidBet = false;
                let errorMessage = '';

                htmlContent = '<p>Xác nhận đặt cược:</p><table class="popup-table"><tr><th>Loại</th><th>Số</th><th>Điểm</th></tr>';
                rows.forEach((row, index) => {
                    const type = row.querySelector('select').value;
                    const numbers = Array.from(row.querySelectorAll('.number-input')).map(input => input.value.trim()).filter(val => val !== '');
                    const points = row.querySelector('input[type="number"]').value.trim();

                    if (numbers.length > 0 && points === '') {
                        errorMessage = `Dòng ${index + 1}: Bạn phải nhập số điểm nếu đã nhập số!`;
                    } else if (numbers.length === 0 && points !== '') {
                        errorMessage = `Dòng ${index + 1}: Bạn phải nhập số nếu đã nhập số điểm!`;
                    } else if (numbers.length > 0 && points !== '') {
                        hasValidBet = true;
                        htmlContent += `<tr><td>${typeNames[type]}</td><td>${numbers.join(', ')}</td><td>${points}</td></tr>`;
                    }
                });
                const totalMoney = calculateTotal();
                htmlContent += `</table><p>Tổng tiền: ${totalMoney.toLocaleString('vi-VN')} Vnđ</p>`;

                if (errorMessage) {
                    document.querySelector('.container').insertAdjacentHTML('afterbegin', `<div class="thong-bao error">${errorMessage}</div>`);
                    return;
                }

                if (!hasValidBet) {
                    document.querySelector('.container').insertAdjacentHTML('afterbegin', `<div class="thong-bao error">Vui lòng nhập ít nhất một lựa chọn đặt cược hợp lệ!</div>`);
                    return;
                }
            } else if (action === 'cancel') {
                htmlContent = '<p>Xác nhận hủy cược:</p><table class="popup-table"><tr><th>ID</th><th>Số tiền hoàn lại</th></tr>';
                htmlContent += `<tr><td>${betId}</td><td>${betMoney.toLocaleString('vi-VN')} Vnđ</td></tr></table>`;
            }

            popupContent.innerHTML = htmlContent;
            popup.style.display = 'block';
            overlay.style.display = 'block';

            const confirmButton = document.getElementById('confirmButton');
            const cancelButton = document.getElementById('cancelButton');

            confirmButton.replaceWith(confirmButton.cloneNode(true));
            cancelButton.replaceWith(cancelButton.cloneNode(true));

            const newConfirmButton = document.getElementById('confirmButton');
            const newCancelButton = document.getElementById('cancelButton');

            newConfirmButton.onclick = function() {
                if (action === 'submit') {
                    const form = document.getElementById('betForm');
                    const hiddenBetSubmit = document.createElement('input');
                    hiddenBetSubmit.type = 'hidden';
                    hiddenBetSubmit.name = 'bet_submit';
                    hiddenBetSubmit.value = 'bet_submit';
                    const hiddenConfirm = document.createElement('input');
                    hiddenConfirm.type = 'hidden';
                    hiddenConfirm.name = 'confirm';
                    hiddenConfirm.value = 'yes';
                    form.appendChild(hiddenBetSubmit);
                    form.appendChild(hiddenConfirm);
                    form.submit();
                } else if (action === 'cancel') {
                    const cancelForm = document.createElement('form');
                    cancelForm.method = 'post';
                    cancelForm.innerHTML = `
                        <input type="hidden" name="bet_id" value="${betId}">
                        <input type="hidden" name="cancel" value="cancel">
                        <input type="hidden" name="confirm" value="yes">
                    `;
                    document.body.appendChild(cancelForm);
                    cancelForm.submit();
                }
                closePopup();
            };

            newCancelButton.onclick = function() {
                closePopup();
            };
        }

        function closePopup() {
            const popup = document.getElementById('confirmPopup');
            const overlay = document.getElementById('popupOverlay');
            popup.style.display = 'none';
            overlay.style.display = 'none';
        }

        window.onload = function() {
            calculateTotal();
            toggleDeleteButtons();
        };
    </script>
</body>
</html>