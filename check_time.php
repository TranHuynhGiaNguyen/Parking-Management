<?php
// Ép PHP dùng timezone VN
date_default_timezone_set("Asia/Ho_Chi_Minh");

require_once __DIR__ . "/db_connect.php";

header("Content-Type: text/plain; charset=utf-8");

// Thời gian PHP
$php_time = date("Y-m-d H:i:s");

// Thời gian MySQL
$res = $conn->query("SELECT NOW() AS mysql_time");
$db = $res->fetch_assoc();
$mysql_time = $db['mysql_time'];

// Thời gian hệ điều hành thật (Windows)
$os_time = shell_exec("time /t") . " " . shell_exec("date /t");

// Output
echo "===== CHECK TIME SYSTEM =====\n\n";
echo "PHP TIME       : $php_time\n";
echo "MYSQL TIME     : $mysql_time\n";
echo "WINDOWS CLOCK  : $os_time\n";
echo "Timezone PHP   : " . date_default_timezone_get() . "\n\n";

// Kiểm tra lệch
$php_ts = strtotime($php_time);
$mysql_ts = strtotime($mysql_time);

$diff = abs($php_ts - $mysql_ts);

echo "⚠️ Lệch giữa PHP & MySQL: {$diff} giây\n";

if ($diff > 5) {
    echo "❌ HỆ THỐNG ĐANG LỆCH GIỜ — TÍNH PHÍ SẼ SAI\n";
    echo "Giải pháp: restart Apache + MySQL và thiết lập timezone như tao nói.";
} else {
    echo "✔ Đồng bộ — hệ thống tính phí CHUẨN.";
}
?>
