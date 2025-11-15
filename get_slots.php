<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$cfg = $conn->query("SELECT max_car, max_motorcycle FROM system_config WHERE id=1 LIMIT 1");
if(!$cfg || !$cfg->num_rows){ echo json_encode(['ok'=>false]); exit; }
$cfg = $cfg->fetch_assoc();
$MAX_CAR = (int)$cfg['max_car'];
$MAX_MC  = (int)$cfg['max_motorcycle'];

$rows = $conn->query("SELECT vehicle_type, COUNT(*) AS c 
  FROM vehicle_sessions WHERE out_time IS NULL GROUP BY vehicle_type");
$used_car = 0; $used_mc = 0;
if ($rows) while($r = $rows->fetch_assoc()){
  if (strcasecmp($r['vehicle_type'],'Car')===0) $used_car = (int)$r['c'];
  if (strcasecmp($r['vehicle_type'],'Motorcycle')===0) $used_mc = (int)$r['c'];
}

echo json_encode([
  'ok'=>true,
  'car'=>['max'=>$MAX_CAR,'used'=>$used_car,'available'=>max(0,$MAX_CAR-$used_car),'is_full'=>$used_car >= $MAX_CAR],
  'motorcycle'=>['max'=>$MAX_MC,'used'=>$used_mc,'available'=>max(0,$MAX_MC-$used_mc),'is_full'=>$used_mc >= $MAX_MC]
]);
