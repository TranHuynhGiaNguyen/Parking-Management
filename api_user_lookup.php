<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
    echo json_encode(["ok" => false, "msg" => "Thiếu từ khóa tìm kiếm"]);
    exit;
}

// Lấy thông tin xe
$stmt = $conn->prepare("
    SELECT id, uid, plate, vehicle_type, in_time, out_time, zone, fee
    FROM vehicle_sessions
    WHERE uid = ? OR plate = ?
    ORDER BY id DESC
    LIMIT 1
");
$stmt->bind_param("ss", $q, $q);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["ok" => false, "msg" => "Không tìm thấy xe"]);
    exit;
}

$car = $res->fetch_assoc();

// Lấy bảng khung giờ
$fee_q = $conn->query("
    SELECT * FROM fee_time_ranges ORDER BY id ASC
");

$fee_ranges = [];
while ($r = $fee_q->fetch_assoc()) {
    $fee_ranges[] = $r;
}

// Hàm tính phí theo từng khung
function calc_fee($in, $out, $type, $fee_ranges) {
    $total = 0;

    $start = strtotime($in);
    $end   = strtotime($out);

    if ($end <= $start) return 0;

    // Lặp từng phút (an toàn, chính xác)
    for ($t = $start; $t < $end; $t += 60) {

        $h = date("H:i:s", $t);

        foreach ($fee_ranges as $fr) {

            $s = $fr['start_time'];
            $e = $fr['end_time'];

            $in_range = false;

            if ($s < $e) {
                // Khung giờ bình thường
                if ($h >= $s && $h < $e) $in_range = true;

            } else {
                // Khung qua đêm (22:00 - 05:00)
                if ($h >= $s || $h < $e) $in_range = true;
            }

            if ($in_range) {
                if ($type === "Car")  $total += $fr['fee_car_per_hour'] / 60;
                if ($type === "Motorcycle") $total += $fr['fee_mc_per_hour'] / 60;
                break;
            }
        }
    }

    return floor($total);
}

// Nếu xe chưa ra => tính đến hiện tại
if ($car['out_time'] === null) {
    $now = date("Y-m-d H:i:s");
    $fee_now = calc_fee($car["in_time"], $now, $car["vehicle_type"], $fee_ranges);
    $car['fee'] = $fee_now;
    $car['out_time'] = "Đang gửi";
}

echo json_encode([
    "ok" => true,
    "data" => $car
]);
