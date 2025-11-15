<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json; charset=utf-8');


/*  Chuẩn hóa loại xe    */
function normalize_vtype($raw) {
    $r = strtolower(trim((string)$raw));
    if ($r === '') return null;
    if (in_array($r, ['car','cars'])) return 'Car';
    if (in_array($r, ['motorcycle','motorbike','motor','bike','mc','moto'])) return 'Motorcycle';
    if (strpos($r,'car') !== false) return 'Car';
    if (strpos($r,'motor') !== false || strpos($r,'bike') !== false) return 'Motorcycle';
    return null;
}

/*  Chuẩn hóa biển số     */
function normalize_plate($p) {
    $p = strtoupper(trim($p));
    return preg_replace('/[^A-Z0-9]/', '', $p);
}


/*  Đọc cấu hình hệ thống (scan_mode)  */
$cfg = $conn->query("
    SELECT scan_mode 
    FROM system_config 
    WHERE id=1 LIMIT 1
");

if (!$cfg || !$cfg->num_rows) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Không đọc được cấu hình hệ thống']);
    exit;
}
$scan_mode = $cfg->fetch_assoc()['scan_mode'] ?? 'both';


/*  Lấy input từ FE   */
$uid        = isset($_POST['uid']) ? strtoupper(preg_replace('/[^0-9A-F]/','', $_POST['uid'])) : '';
$plate_raw  = isset($_POST['plate']) ? trim($_POST['plate']) : '';
$plate_norm = normalize_plate($plate_raw);
$raw_type   = $_POST['vehicle_type'] ?? '';

if ($uid === '') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Thiếu UID']);
    exit;
}
if ($plate_raw === '' || $plate_raw === '—' || $plate_raw === '-') {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'Chưa nhận diện được biển số']);
    exit;
}

/* Chuẩn hóa loại xe */
$vtype = normalize_vtype($raw_type) ?? 'Motorcycle';


/*  Chống trùng request 2 giây                                 */
$chk = $conn->prepare("
    SELECT 1 FROM card_reads
    WHERE uid=? AND plate=? 
      AND captured_at >= (NOW() - INTERVAL 2 SECOND)
    LIMIT 1
");
$chk->bind_param("ss", $uid, $plate_raw);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    echo json_encode(['ok'=>true,'message'=>'Bỏ trùng']);
    exit;
}
$chk->close();


/* Ghi log đọc thẻ */
$ins = $conn->prepare("
    INSERT INTO card_reads(uid, plate, vehicle_type, captured_at)
    VALUES(?,?,?,NOW())
");
$ins->bind_param("sss", $uid, $plate_raw, $vtype);
$ins->execute();
$ins->close();


/*  Kiểm tra phiên đang mở                                     */
$q = $conn->prepare("
    SELECT id, uid, plate, vehicle_type, in_time
    FROM vehicle_sessions
    WHERE uid=? AND out_time IS NULL
    LIMIT 1
");
$q->bind_param("s", $uid);
$q->execute();
$res  = $q->get_result();
$open = $res && $res->num_rows ? $res->fetch_assoc() : null;
$q->close();


/*  SCAN MODE = OUT nhưng không có session → từ chối           */
if ($scan_mode === 'out' && !$open) {
    echo json_encode([
        'ok'=>false,
        'error'=>'Máy đang ở chế độ RA — Xe này không có trong bãi'
    ]);
    exit;
}

/*  SCAN MODE = IN → chỉ cho vào, không cho ra                */
if ($scan_mode === 'in') {

    if ($open) {
        echo json_encode([
            'ok'=>true,
            'action'=>'checkin_ignore',
            'message'=>'Xe đã trong bãi — bỏ qua OUT'
        ]);
        exit;
    }

    // tạo phiên vào
    $i = $conn->prepare("
        INSERT INTO vehicle_sessions(uid, plate, vehicle_type, in_time)
        VALUES(?,?,?,NOW())
    ");
    $i->bind_param("sss",$uid,$plate_raw,$vtype);
    $i->execute();
    $i->close();

    echo json_encode([
        'ok'=>true,
        'action'=>'checkin',
        'message'=>'Xe vào – '.$plate_raw
    ]);
    exit;
}


/*  CHECKOUT — KHỚP BIỂN + TÍNH PHÍ                    */
if ($open) {

    $plate_in_norm  = normalize_plate($open['plate']);
    $plate_out_norm = $plate_norm;

    /* Nếu biển số không trùng → chặn */
    if ($plate_in_norm !== $plate_out_norm) {
        echo json_encode([
            'ok'=>false,
            'action'=>'checkout_denied',
            'error'=>'Xe ra không khớp với xe đã vào',
            'detail'=>[
                'plate_in'  => $open['plate'],
                'plate_out' => $plate_raw,
                'message'   => '⚠ Xe RA không phải chiếc đã VÀO bằng thẻ này!'
            ]
        ]);
        exit;
    }

    /* === TÍNH PHÍ THEO KHUNG GIỜ (KHÔNG TÍNH PHÚT) === */

    $in_time = new DateTime($open['in_time']);
    $hour_in = (int)$in_time->format("H"); // chỉ lấy giờ vào
    $storedType = normalize_vtype($open['vehicle_type']) ?? 'Motorcycle';

    // Lấy toàn bộ khung giờ
    $ranges = $conn->query("SELECT * FROM fee_time_ranges ORDER BY start_time ASC");

    $fee = 0;

    while ($r = $ranges->fetch_assoc()) {

        list($sh, $sm) = explode(':', $r['start_time']);
        list($eh, $em) = explode(':', $r['end_time']);

        $sh = (int)$sh;
        $eh = (int)$eh;

        $match = false;

        // Khung qua đêm
        if ($sh > $eh) {
            if ($hour_in >= $sh || $hour_in < $eh) {
                $match = true;
            }
        }
        // Khung bình thường
        else {
            if ($hour_in >= $sh && $hour_in < $eh) {
                $match = true;
            }
        }

        if ($match) {
            $fee = ($storedType === 'Car')
                ? (int)$r['fee_car_per_hour']
                : (int)$r['fee_mc_per_hour'];
            break;
        }
    }

    /* === Cập nhật DB === */
    $u = $conn->prepare("
        UPDATE vehicle_sessions
        SET out_time = NOW(), fee = ?
        WHERE id = ?
    ");
    $u->bind_param("di", $fee, $open['id']);
    $u->execute();
    $u->close();

    echo json_encode([
        'ok'=>true,
        'action'=>'checkout',
        'message'=>'Xe ra – '.$open['plate'],
        'fee'=>$fee
    ]);
    exit;
}


/*  Không có session → tạo phiên mới (IN)                       */

$i = $conn->prepare("
    INSERT INTO vehicle_sessions(uid, plate, vehicle_type, in_time)
    VALUES(?,?,?,NOW())
");
$i->bind_param("sss",$uid,$plate_raw,$vtype);
$i->execute();
$i->close();

echo json_encode([
    'ok'=>true,
    'action'=>'checkin',
    'message'=>'Xe vào – '.$plate_raw
]);
exit;

?>
