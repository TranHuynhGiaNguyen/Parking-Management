<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/db_connect.php';

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
    echo json_encode(["ok" => false, "msg" => "Thiếu từ khóa tìm kiếm"]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, uid, plate, vehicle_type, in_time, out_time, zone, fee
    FROM vehicle_sessions
    WHERE uid = ? OR plate = ?
    ORDER BY in_time DESC
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

/* ----- Load fee ranges ----- */
$fee_ranges = [];
$qr = $conn->query("SELECT * FROM fee_time_ranges ORDER BY id ASC");
while ($r = $qr->fetch_assoc()) $fee_ranges[] = $r;

/* ----- Hàm tính phí ----- */
function calc_fee($vtype, $inTime, $outTime, $ranges)
{
    $inTS  = strtotime($inTime);
    $outTS = strtotime($outTime);

    if ($outTS <= $inTS) return 0;

    $total = 0;

    while ($inTS < $outTS) {

        $currentDay = date("Y-m-d", $inTS);
        $applied = null;
        $blockStart = null;
        $blockEnd   = null;

        foreach ($ranges as $r) {

            $startTS = strtotime("$currentDay {$r['start_time']}");
            $endTS   = strtotime("$currentDay {$r['end_time']}");

            if ($r['start_time'] > $r['end_time']) {

                if ($inTS >= $startTS) {
                    $blockStart = $startTS;
                    $blockEnd   = strtotime("$currentDay {$r['end_time']} +1 day");
                    $applied = $r; break;
                }

                if ($inTS < $endTS) {
                    $blockStart = strtotime("$currentDay {$r['start_time']} -1 day");
                    $blockEnd   = $endTS;
                    $applied = $r; break;
                }

            } else {
                if ($inTS >= $startTS && $inTS < $endTS) {
                    $blockStart = $startTS;
                    $blockEnd   = $endTS;
                    $applied = $r; break;
                }
            }
        }

        if (!$applied) {
            $inTS += 60;
            continue;
        }

        $segmentEnd = min($blockEnd, $outTS);

        if ($segmentEnd <= $inTS) {
            $inTS += 60;
            continue;
        }

        $minutes = ($segmentEnd - $inTS) / 60;

        $rate = ($vtype === 'CAR')
            ? $applied['fee_car_per_hour']
            : $applied['fee_mc_per_hour'];

        $total += ($minutes / 60) * $rate;

        $inTS = $segmentEnd;
    }

    return round($total);
}

/* ===== TÍNH PHÍ ===== */
if ($car['out_time'] === null || $car['out_time'] == "0000-00-00 00:00:00") {

    $now = date("Y-m-d H:i:s");
    $car['out_time'] = "Đang gửi";

    $car['fee'] = calc_fee(
        strtoupper($car['vehicle_type']),
        $car["in_time"],
        $now,
        $fee_ranges
    );
}

echo json_encode([
    "ok" => true,
    "data" => $car
]);
?>
