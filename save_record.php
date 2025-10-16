<?php
header('Content-Type: application/json; charset=utf-8');
$mysqli = new mysqli('localhost','root','','parking_system');
if ($mysqli->connect_error) {
  http_response_code(500);
  exit(json_encode(['message'=>'Lỗi kết nối MySQL']));
}

$uid   = isset($_POST['uid']) ? strtoupper(preg_replace('/[^0-9A-F]/i','', $_POST['uid'])) : '';
$plate = isset($_POST['plate']) ? trim($_POST['plate']) : '';
$type  = isset($_POST['vehicle_type']) ? trim($_POST['vehicle_type']) : '';

if ($uid === '') {
  http_response_code(400); exit(json_encode(['message'=>'Thiếu UID']));
}
if ($plate === '' || $plate === '—' || $plate === '-') {
  http_response_code(400); exit(json_encode(['message'=>'Chưa nhận diện được biển số']));
}

/* Debounce 2 giây */
$chk = $mysqli->prepare("
  SELECT 1 FROM card_reads
  WHERE uid=? AND plate=? 
    AND captured_at >= (NOW() - INTERVAL 2 SECOND)
  LIMIT 1
");
$chk->bind_param("ss",$uid,$plate);
$chk->execute(); $chk->store_result();
if ($chk->num_rows > 0) { echo json_encode(['message'=>'Bỏ trùng']); exit; }

/* Lưu bản ghi */
$stmt = $mysqli->prepare("
  INSERT INTO card_reads (uid, plate, vehicle_type, captured_at)
  VALUES (?, ?, ?, NOW())
  ON DUPLICATE KEY UPDATE captured_at = NOW()
");
$stmt->bind_param("sss",$uid,$plate,$type);
$stmt->execute();

/* Tự động xác định vào/ra */
$q=$mysqli->prepare("SELECT id FROM vehicle_sessions WHERE uid=? AND out_time IS NULL LIMIT 1");
$q->bind_param("s",$uid); $q->execute(); $q->bind_result($sid);
if($q->fetch()){
  $u=$mysqli->prepare("UPDATE vehicle_sessions SET out_time=NOW(), plate=?, vehicle_type=? WHERE id=?");
  $u->bind_param("ssi",$plate,$type,$sid); $u->execute();
  echo json_encode(['message'=>'Xe ra – '.$plate]);
}else{
  $i=$mysqli->prepare("INSERT INTO vehicle_sessions(uid,plate,vehicle_type,in_time) VALUES(?,?,?,NOW())");
  $i->bind_param("sss",$uid,$plate,$type); $i->execute();
  echo json_encode(['message'=>'Xe vào – '.$plate]);
}
