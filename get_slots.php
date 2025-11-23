<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

// Lấy tổng theo loại CAR + MC
$sql = "
  SELECT 
      type,
      SUM(max_slots) AS max_slots,
      SUM(used) AS used_slots
  FROM parking_zones
  GROUP BY type
";

$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['ok'=>false, 'error'=>'DB error']);
    exit;
}

$car = ['max'=>0,'used'=>0];
$mc  = ['max'=>0,'used'=>0];

while ($r = $res->fetch_assoc()) {
    if ($r['type'] === 'CAR') {
        $car['max']  = (int)$r['max_slots'];
        $car['used'] = (int)$r['used_slots'];
    }
    if ($r['type'] === 'MC') {
        $mc['max']  = (int)$r['max_slots'];
        $mc['used'] = (int)$r['used_slots'];
    }
}

$car['available'] = max(0, $car['max'] - $car['used']);
$car['is_full']   = $car['used'] >= $car['max'];

$mc['available']  = max(0, $mc['max'] - $mc['used']);
$mc['is_full']    = $mc['used'] >= $mc['max'];

echo json_encode([
    'ok' => true,
    'car' => $car,
    'motorcycle' => $mc
]);
