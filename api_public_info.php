<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/db_connect.php';
header("Content-Type: application/json; charset=utf-8");

$fees = [];
$res = $conn->query("SELECT * FROM fee_time_ranges ORDER BY id ASC");
while ($r = $res->fetch_assoc()) {
    $fees[] = [
        "start" => $r["start_time"],
        "end"   => $r["end_time"],
        "car"   => (int)$r["fee_car_per_hour"],
        "mc"    => (int)$r["fee_mc_per_hour"]

    ];
}

$cfg = $conn->query("SELECT max_car, max_motorcycle FROM system_config WHERE id = 1")
           ->fetch_assoc();

$max_car = (int)$cfg["max_car"];
$max_mc  = (int)$cfg["max_motorcycle"];

$used_car = 0;
$used_mc  = 0;

$q = $conn->query("SELECT vehicle_type, COUNT(*) AS c
                   FROM vehicle_sessions
                   WHERE out_time IS NULL
                   GROUP BY vehicle_type");

while ($r = $q->fetch_assoc()) {
    if ($r["vehicle_type"] === "Car") $used_car = $r["c"];
    if ($r["vehicle_type"] === "Motorcycle") $used_mc = $r["c"];
}

echo json_encode([
    "ok" => true,
    "fee_ranges" => $fees,
    "slots" => [
        "car" => ["used" => $used_car, "max" => $max_car],
        "mc"  => ["used" => $used_mc , "max" => $max_mc]
    ]
]);
