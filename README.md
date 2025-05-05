# Code web game đánh lô - đề online, tự cập nhật kết quả và trả thưởng

**Code thuần 100% PHP**

**Cài đặt:**

* Upload toàn bộ code lên hosting ( xoá folder demo )
* Tạo database
* Chạy file install.php và điền các thông tin để kết nối với database
* Tạo user admin, set password cho file quản trị ( admin.php )


Data có sẵn link online để cập nhật kết quả, có thể chạy file **cron.php** trong folder data để lấy data về hosting.
Tạo tài khoản, có ngay 10.000.000 trong tài khoản, có thể sửa trong file **register.php** 

```
$money = 10000000;
```


Tỉ lệ cược, giá tiền có thể sửa đổi trong file **admin.php**

Code gồm đầy đủ tính năng sau:

* Hiển thị đầy đủ thông tin về data - cập nhật trả thưởng
* Hiển thị tỉ lệ trả thưởng
* Hiển thị top 10 người chơi ngoài trang chủ
* Hiển thị kết quả quay thưởng mới nhất
* Hiển thị lượt chơi ( lọc lượt chơi mới )
* Đặt cược với đầy đủ các loại, từ đề, đề 3 càng, lô, lô xiên...
* Quản trị tài khoản, hiển thị chi tiết lịch sử giao dịch
* Bảng xếp hạng toàn bộ người chơi
* Thống kê toàn bộ lượt chơi / lượt trúng thưởng
* Toàn bộ lịch sử chơi trong 90 ngày ( file cron trả thưởng sẽ xoá data quá 90 ngày )


```
$thresholdDate91 = $currentDate->modify('-91 days')->format('Y-m-d');
```


Chạy file **trathuongtudong.php** để trả thưởng ( 1 lần 1 ngày tầm khoảng 18h40 là được )

![image](https://raw.githubusercontent.com/junlangzi/code-web-game-kqxs/refs/heads/main/demo/demo.png)

<br>

![image](https://raw.githubusercontent.com/junlangzi/code-web-game-kqxs/refs/heads/main/demo/demo1.png)

![image](https://raw.githubusercontent.com/junlangzi/code-web-game-kqxs/refs/heads/main/demo/demo2.png)

![image](https://raw.githubusercontent.com/junlangzi/code-web-game-kqxs/refs/heads/main/demo/demo3.png) ![image](https://raw.githubusercontent.com/junlangzi/code-web-game-kqxs/refs/heads/main/demo/demo4.png)
