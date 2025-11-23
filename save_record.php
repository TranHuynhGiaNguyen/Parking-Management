<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');

/* -------------------- CHUẨN HÓA -------------------- */
function normalize_vtype($raw) {
    $r = strtoupper(trim((string)$raw));
    if ($r === 'CAR') return 'CAR';
    if ($r === 'MC')  return 'MC';
    if (strpos($r,'CAR') !== false) return 'CAR';
    if (strpos($r,'MOTOR') !== false || strpos($r,'BIKE') !== false) return 'MC';
    return 'MC';
}

function normalize_plate($p) {
    return preg_replace('/[^A-Z0-9]/', '', strtoupper(trim($p)));
}

/* -------------------- ĐỌC CẤU HÌNH -------------------- */
$cfg = $conn->query("SELECT scan_mode FROM system_config WHERE id=1 LIMIT 1");
if (!$cfg || !$cfg->num_rows) {
    echo json_encode(['ok'=>false,'error'=>'Không đọc được cấu hình']);
    exit;
}
$scan_mode = $cfg->fetch_assoc()['scan_mode'] ?? 'both';

/* -------------------- INPUT -------------------- */
$uid        = isset($_POST['uid']) ? strtoupper(preg_replace('/[^0-9A-F]/','', $_POST['uid'])) : '';
$plate_raw  = trim($_POST['plate'] ?? '');
$plate_norm = normalize_plate($plate_raw);
$raw_type   = $_POST['vehicle_type'] ?? '';

if ($uid === '') {
    echo json_encode(['ok'=>false,'error'=>'Thiếu UID']); exit;
}
if ($plate_raw === '' || $plate_raw === "-" || $plate_raw === "—") {
    echo json_encode(['ok'=>false,'error'=>'Không nhận diện được biển số']); exit;
}
$vtype = normalize_vtype($raw_type);

/* -------------------- ANTI-SPAM 2s -------------------- */
$chk = $conn->prepare("
    SELECT 1 FROM card_reads
    WHERE uid=? AND plate=?
      AND captured_at >= (NOW() - INTERVAL 2 SECOND)
    LIMIT 1
");
$chk->bind_param("ss", $uid, $plate_raw);
$chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) {
    echo json_encode(['ok'=>true,'message'=>'Bỏ trùng']); exit;
}
$chk->close();

/* -------------------- LOG ĐỌC THẺ -------------------- */
$ins = $conn->prepare("
    INSERT INTO card_reads(uid, plate, vehicle_type, captured_at)
    VALUES(?,?,?,NOW())
");
$ins->bind_param("sss",$uid,$plate_raw,$vtype);
$ins->execute();
$ins->close();

/* ============================================================
   KHÓA CHẶN: 1 BIỂN SỐ CHỈ THUỘC 1 UID – KHÔNG CHO XE DÙNG 2 THẺ
   ============================================================ */

/* --- kiểm tra biển số này từng thuộc UID nào --- */
$cp = $conn->prepare("
    SELECT uid FROM vehicle_sessions
    WHERE plate=? 
    ORDER BY id DESC LIMIT 1
");
$cp->bind_param("s", $plate_raw);
$cp->execute();
$prev = $cp->get_result()->fetch_assoc();
$cp->close();

/* --- nếu biển từng thuộc UID khác → CHẶN --- */
if ($prev && $prev['uid'] !== $uid) {
    echo json_encode([
        'ok'=>false,
        'error'=>'Biển số này trước đây thuộc UID khác — không cho check-in',
        'expected_uid'=>$prev['uid'],
        'your_uid'=>$uid
    ]);
    exit;
}

/* --- kiểm tra xe đang gửi bằng UID khác --- */
$cp2 = $conn->prepare("
    SELECT uid FROM vehicle_sessions
    WHERE plate=? AND out_time IS NULL
    LIMIT 1
");
$cp2->bind_param("s", $plate_raw);
$cp2->execute();
$busy = $cp2->get_result()->fetch_assoc();
$cp2->close();

if ($busy && $busy['uid'] !== $uid) {
    echo json_encode([
        'ok'=>false,
        'error'=>'Xe này đang gửi bằng UID khác — không cho vào'
    ]);
    exit;
}

/* -------------------- CHECK PHIÊN MỞ CỦA UID HIỆN TẠI -------------------- */
$q = $conn->prepare("
    SELECT id, uid, plate, zone, vehicle_type, in_time
    FROM vehicle_sessions
    WHERE uid=? AND out_time IS NULL
    LIMIT 1
");
$q->bind_param("s",$uid);
$q->execute();
$res  = $q->get_result();
$open = $res && $res->num_rows ? $res->fetch_assoc() : null;
$q->close();

/* -------------------- CHỌN BÃI -------------------- */
function pick_zone($conn, $vtype) {
    $vtype = $conn->real_escape_string($vtype);
    $q = $conn->query("
        SELECT zone, used, max_slots 
        FROM parking_zones
        WHERE type = '$vtype'
        ORDER BY zone ASC
    ");
    while ($z = $q->fetch_assoc()) {
        if ((int)$z['used'] < (int)$z['max_slots']) return $z['zone'];
    }
    return null;
}

/* -------------------- OUT ONLY -------------------- */
if ($scan_mode === 'out' && !$open) {
    echo json_encode(['ok'=>false,'error'=>'Máy để chế độ RA – xe không nằm trong bãi']);
    exit;
}

/* -------------------- IN ONLY -------------------- */
if ($scan_mode === 'in') {

    if ($open) {
        echo json_encode([
            'ok'=>true,
            'action'=>'checkin_ignore',
            'message'=>'Xe đã trong bãi — bỏ qua'
        ]);
        exit;
    }

    $zone = pick_zone($conn, $vtype);
    if (!$zone) {
        echo json_encode(['ok'=>false,'error'=>'Bãi FULL']); exit;
    }

    $i = $conn->prepare("
        INSERT INTO vehicle_sessions(uid, plate, vehicle_type, zone, in_time)
        VALUES(?,?,?,?,NOW())
    ");
    $i->bind_param("ssss",$uid,$plate_raw,$vtype,$zone);
    $i->execute();
    $session_id = $i->insert_id;
    $i->close();

    $conn->query("UPDATE parking_zones SET used = used + 1 WHERE zone='$zone'");

    echo json_encode([
        'ok'=>true,
        'action'=>'checkin',
        'zone'=>$zone,
        'session_id'=>$session_id,
        'message'=>'Xe vào – '.$plate_raw
    ]);
    exit;
}

/* -------------------- CHECKOUT -------------------- */
if ($open) {

    if (normalize_plate($open['plate']) !== $plate_norm) {
        echo json_encode([
            'ok'=>false,
            'action'=>'checkout_denied',
            'error'=>'Biển số không khớp!',
            'detail'=>[
                'plate_in'=>$open['plate'],
                'plate_out'=>$plate_raw
            ]
        ]);
        exit;
    }

    /* -------------------- TÍNH PHÍ -------------------- */
    function calc_fee($vehicleType, $inTime, $conn) {

        $outTime = date("Y-m-d H:i:s");

        $ranges = [];
        $qr = $conn->query("SELECT * FROM fee_time_ranges ORDER BY id ASC");
        while ($r = $qr->fetch_assoc()) $ranges[] = $r;

        $inTS  = strtotime($inTime);
        $outTS = strtotime($outTime);
        $total = 0;

        while ($inTS < $outTS) {

            $currentDay = date("Y-m-d", $inTS);
            $found = null;
            $blockStart = null;
            $blockEnd = null;

            foreach ($ranges as $r) {

                $startTS = strtotime("$currentDay {$r['start_time']}");
                $endTS   = strtotime("$currentDay {$r['end_time']}");

                if ($r['start_time'] > $r['end_time']) {

                    if ($inTS >= $startTS) {
                        $blockStart = $startTS;
                        $blockEnd   = strtotime("$currentDay {$r['end_time']} +1 day");
                        $found = $r; break;
                    }

                    if ($inTS < $endTS) {
                        $blockStart = strtotime("$currentDay {$r['start_time']} -1 day");
                        $blockEnd   = $endTS;
                        $found = $r; break;
                    }
                }
                else {
                    if ($inTS >= $startTS && $inTS < $endTS) {
                        $blockStart = $startTS;
                        $blockEnd   = $endTS;
                        $found = $r; break;
                    }
                }
            }

            if (!$found) break;

            $segmentEnd = min($blockEnd, $outTS);
            $minutes = ($segmentEnd - $inTS) / 60;

            $rate = ($vehicleType === 'CAR')
                ? $found['fee_car_per_hour']
                : $found['fee_mc_per_hour'];

            $total += ($minutes / 60) * $rate;

            $inTS = $segmentEnd;
        }

        return round($total);
    }

    $storedType = normalize_vtype($open['vehicle_type']);
    $fee = calc_fee($storedType, $open['in_time'], $conn);

    $u = $conn->prepare("
        UPDATE vehicle_sessions
        SET out_time = NOW(), fee = ?
        WHERE id = ?
    ");
    $u->bind_param("di", $fee, $open['id']);
    $u->execute();
    $u->close();

    $zone_old = $open['zone'];
    $conn->query("UPDATE parking_zones SET used = GREATEST(used - 1, 0) WHERE zone='$zone_old'");

    echo json_encode([
        'ok'=>true,
        'action'=>'checkout',
        'fee'=>$fee,
        'session_id'=>$open['id'],
        'message'=>'Xe ra – '.$open['plate']
    ]);
    exit;
}

/* -------------------- CHECK-IN DEFAULT (MODE BOTH) -------------------- */

$zone = pick_zone($conn, $vtype);
if (!$zone) {
    echo json_encode(['ok'=>false,'error'=>'Bãi FULL']); exit;
}

$i = $conn->prepare("
    INSERT INTO vehicle_sessions(uid, plate, vehicle_type, zone, in_time)
    VALUES(?,?,?,?,NOW())
");
$i->bind_param("ssss",$uid,$plate_raw,$vtype,$zone);
$i->execute();
$session_id = $i->insert_id;
$i->close();

$conn->query("UPDATE parking_zones SET used = used + 1 WHERE zone='$zone'");

echo json_encode([
    'ok'=>true,
    'action'=>'checkin',
    'zone'=>$zone,
    'session_id'=>$session_id,
    'message'=>'Xe vào – '.$plate_raw
]);
exit;
?>
