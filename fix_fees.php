<?php
// fix_fees.php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: text/plain; charset=utf-8');

// ---- Chuẩn hóa loại xe giống save_record.php ----
function normalize_vtype($raw) {
    $r = strtoupper(trim((string)$raw));
    if ($r === 'CAR') return 'CAR';
    if ($r === 'MC')  return 'MC';
    if (strpos($r,'CAR') !== false) return 'CAR';
    if (strpos($r,'MOTOR') !== false || strpos($r,'BIKE') !== false) return 'MC';
    return 'MC';
}

// ---- Load bảng fee_time_ranges vào mảng ----
$fee_ranges = [];
$qr = $conn->query("SELECT * FROM fee_time_ranges ORDER BY id ASC");
if (!$qr || !$qr->num_rows) {
    echo "Không đọc được fee_time_ranges\n";
    exit;
}
while ($r = $qr->fetch_assoc()) {
    $fee_ranges[] = $r;
}

// ---- Hàm tính phí SỬ DỤNG in_time & out_time LƯU SẴN ----
function calc_fee_from_db($vehicleType, $inTime, $outTime, $ranges) {
    $inTS  = strtotime($inTime);
    $outTS = strtotime($outTime);
    if ($outTS <= $inTS) return 0;

    $total = 0;

    while ($inTS < $outTS) {

        $currentDay = date("Y-m-d", $inTS);
        $found      = null;
        $blockStart = null;
        $blockEnd   = null;

        foreach ($ranges as $r) {
            $startTS = strtotime("$currentDay {$r['start_time']}");
            $endTS   = strtotime("$currentDay {$r['end_time']}");

            // Block qua đêm (VD: 22:00 -> 05:00)
            if ($r['start_time'] > $r['end_time']) {

                // VD đang trong 23h - 24h
                if ($inTS >= $startTS) {
                    $blockStart = $startTS;
                    $blockEnd   = strtotime("$currentDay {$r['end_time']} +1 day");
                    $found = $r; 
                    break;
                }

                // VD đang trong 00h - 05h
                if ($inTS < $endTS) {
                    $blockStart = strtotime("$currentDay {$r['start_time']} -1 day");
                    $blockEnd   = $endTS;
                    $found = $r; 
                    break;
                }

            } else {
                // Block bình thường trong ngày
                if ($inTS >= $startTS && $inTS < $endTS) {
                    $blockStart = $startTS;
                    $blockEnd   = $endTS;
                    $found = $r; 
                    break;
                }
            }
        }

        if (!$found) {
            // tránh kẹt, nhích 1 phút
            $inTS += 60;
            continue;
        }

        $segmentEnd = min($blockEnd, $outTS);
        if ($segmentEnd <= $inTS) {
            $inTS += 60;
            continue;
        }

        $minutes = ($segmentEnd - $inTS) / 60;

        $rate = ($vehicleType === 'CAR')
            ? $found['fee_car_per_hour']
            : $found['fee_mc_per_hour'];

        $total += ($minutes / 60) * $rate;

        $inTS = $segmentEnd;
    }

    return round($total);
}

// ---- Lấy danh sách phiên đã ra nhưng fee = 0 ----
$sql = "
    SELECT id, uid, plate, vehicle_type, in_time, out_time, fee
    FROM vehicle_sessions
    WHERE out_time IS NOT NULL
      AND out_time <> '0000-00-00 00:00:00'
      AND fee = 0
";
$res = $conn->query($sql);

if (!$res) {
    echo "Lỗi query vehicle_sessions: " . $conn->error . "\n";
    exit;
}

if ($res->num_rows === 0) {
    echo "Không có phiên nào fee=0 cần sửa.\n";
    exit;
}

echo "Bắt đầu sửa " . $res->num_rows . " phiên...\n\n";

while ($row = $res->fetch_assoc()) {
    $id      = (int)$row['id'];
    $vtype   = normalize_vtype($row['vehicle_type']);
    $inTime  = $row['in_time'];
    $outTime = $row['out_time'];

    $fee_new = calc_fee_from_db($vtype, $inTime, $outTime, $fee_ranges);

    // Nếu vẫn =0 thì in cảnh báo cho biết, nhưng vẫn update cho rõ ràng
    $u = $conn->prepare("UPDATE vehicle_sessions SET fee = ? WHERE id = ?");
    $u->bind_param("di", $fee_new, $id);
    $u->execute();
    $u->close();

    echo "ID #$id | {$row['uid']} | {$row['plate']} | $inTime → $outTime | fee cũ: {$row['fee']} → fee mới: $fee_new\n";
}

echo "\nHoàn tất.\n";
