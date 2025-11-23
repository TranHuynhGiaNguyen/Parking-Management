<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Method']); exit; }

$uid    = trim($_POST['uid'] ?? '');
$plate  = trim($_POST['plate'] ?? '');
$auto_vtype = trim($_POST['auto_vehicle_type'] ?? 'unknown'); 
$override_vtype = trim($_POST['override_vehicle_type'] ?? ''); 
$vehicleType = $override_vtype ?: $auto_vtype;
if (!in_array($vehicleType, ['Car','Motorcycle'])) $vehicleType = 'Motorcycle'; 

if ($uid === '') { echo json_encode(['ok'=>false, 'error'=>'UID rỗng']); exit; }

$cfgQ = $conn->query("SELECT scan_mode, fee_car_per_min, fee_mc_per_min, max_car, max_motorcycle 
                      FROM system_config WHERE id=1 LIMIT 1");
if (!$cfgQ || !$cfgQ->num_rows) { echo json_encode(['ok'=>false, 'error'=>'No config']); exit; }
$cfg = $cfgQ->fetch_assoc();
$scanMode = $cfg['scan_mode'];
$rate_car = (float)$cfg['fee_car_per_min'];
$rate_mc  = (float)$cfg['fee_mc_per_min'];

// Tìm session đang mở của UID
$openQ = $conn->prepare("SELECT id, vehicle_type, in_time FROM vehicle_sessions WHERE uid=? AND out_time IS NULL LIMIT 1");
$openQ->bind_param('s', $uid);
$openQ->execute();
$openRes = $openQ->get_result();
$open = $openRes && $openRes->num_rows ? $openRes->fetch_assoc() : null;
$openQ->close();

$now = date('Y-m-d H:i:s');

if ($scanMode === 'in') {
  // Chỉ cho check-in
  if ($open) { echo json_encode(['ok'=>false,'error'=>'Đã check-in, không thể vào nữa']); exit; }

  // Kiểm tra full theo loại
  $countQ = $conn->prepare("SELECT COUNT(*) AS c FROM vehicle_sessions WHERE vehicle_type=? AND out_time IS NULL");
  $countQ->bind_param('s',$vehicleType);
  $countQ->execute();
  $count = $countQ->get_result()->fetch_assoc()['c'] ?? 0;
  $countQ->close();

  $limit = strcasecmp($vehicleType,'Car')===0 ? (int)$cfg['max_car'] : (int)$cfg['max_motorcycle'];
  if ($count >= $limit) { echo json_encode(['ok'=>false,'error'=>'Bãi đầy (chế độ vào)']); exit; }

  $stmt = $conn->prepare("INSERT INTO vehicle_sessions (uid, plate, vehicle_type, in_time) VALUES (?,?,?,?)");
  $stmt->bind_param('ssss', $uid, $plate, $vehicleType, $now);
  $stmt->execute();
  $id = $stmt->insert_id;
  $stmt->close();
  echo json_encode(['ok'=>true,'action'=>'checkin','id'=>$id,'uid'=>$uid,'vehicle_type'=>$vehicleType,'time'=>$now]);
  exit;
}

if ($scanMode === 'out') {
  // Chỉ cho check-out
  if (!$open) { echo json_encode(['ok'=>false,'error'=>'Chưa check-in, không thể ra']); exit; }

  $in_time = strtotime($open['in_time']);
  $minutes = max(0, (time() - $in_time) / 60.0); // không làm tròn
  $rate = strcasecmp($open['vehicle_type'],'Car')===0 ? $rate_car : $rate_mc;
  $fee = round($minutes * $rate, 2);

  $stmt = $conn->prepare("UPDATE vehicle_sessions SET out_time=?, fee=? WHERE id=?");
  $stmt->bind_param('sdi', $now, $fee, $open['id']);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok'=>true,'action'=>'checkout','uid'=>$uid,'vehicle_type'=>$open['vehicle_type'],
                    'time'=>$now,'minutes'=>$minutes,'fee'=>$fee]);
  exit;
}

// both: tự quyết định
if ($open) {
  // checkout
  $in_time = strtotime($open['in_time']);
  $minutes = max(0, (time() - $in_time) / 60.0);
  $rate = strcasecmp($open['vehicle_type'],'Car')===0 ? $rate_car : $rate_mc;
  $fee = round($minutes * $rate, 2);

  $stmt = $conn->prepare("UPDATE vehicle_sessions SET out_time=?, fee=? WHERE id=?");
  $stmt->bind_param('sdi', $now, $fee, $open['id']);
  $stmt->execute();
  $stmt->close();

  echo json_encode(['ok'=>true,'action'=>'checkout','uid'=>$uid,'vehicle_type'=>$open['vehicle_type'],
                    'time'=>$now,'minutes'=>$minutes,'fee'=>$fee]);
  exit;
} else {
  // check-in
  // Kiểm tra full trước
  $countQ = $conn->prepare("SELECT COUNT(*) AS c FROM vehicle_sessions WHERE vehicle_type=? AND out_time IS NULL");
  $countQ->bind_param('s',$vehicleType);
  $countQ->execute();
  $count = $countQ->get_result()->fetch_assoc()['c'] ?? 0;
  $countQ->close();

  $limit = strcasecmp($vehicleType,'Car')===0 ? (int)$cfg['max_car'] : (int)$cfg['max_motorcycle'];
  if ($count >= $limit) { echo json_encode(['ok'=>false,'error'=>'Bãi đầy']); exit; }

  $stmt = $conn->prepare("INSERT INTO vehicle_sessions (uid, plate, vehicle_type, in_time) VALUES (?,?,?,?)");
  $stmt->bind_param('ssss', $uid, $plate, $vehicleType, $now);
  $stmt->execute();
  $id = $stmt->insert_id;
  $stmt->close();

  echo json_encode(['ok'=>true,'action'=>'checkin','id'=>$id,'uid'=>$uid,'vehicle_type'=>$vehicleType,'time'=>$now]);
  exit;
}
