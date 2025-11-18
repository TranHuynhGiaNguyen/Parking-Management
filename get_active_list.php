<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$res = $conn->query("SELECT uid, plate, vehicle_type, in_time 
                     FROM vehicle_sessions WHERE out_time IS NULL 
                     ORDER BY in_time DESC");
$data = [];
if ($res) while($r = $res->fetch_assoc()) $data[] = $r;
echo json_encode(['ok'=>true,'items'=>$data]);
