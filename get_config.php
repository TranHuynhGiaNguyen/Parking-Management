<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$q = $conn->query("SELECT max_car, max_motorcycle, scan_mode, fee_car_per_min, fee_mc_per_min 
                   FROM system_config WHERE id=1 LIMIT 1");
if ($q && $q->num_rows) {
  echo json_encode(['ok'=>true,'config'=>$q->fetch_assoc()]);
} else {
  echo json_encode(['ok'=>false, 'error'=>'Config not found']);
}
