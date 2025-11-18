<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

/*
  Log = union giữa:
  - các bản ghi mới check-in (out_time IS NULL) coi là "in"
  - các bản ghi đã check-out (out_time NOT NULL) coi là "out"
*/
$sql = "
  SELECT uid, plate, vehicle_type, in_time AS t, NULL AS fee, 'in' AS action
  FROM vehicle_sessions
  UNION ALL
  SELECT uid, plate, vehicle_type, out_time AS t, fee, 'out' AS action
  FROM vehicle_sessions
  ORDER BY t DESC
  LIMIT 20
";
$res = $conn->query($sql);
$list = [];
if ($res) while($r = $res->fetch_assoc()) $list[] = $r;
echo json_encode(['ok'=>true,'items'=>$list]);
