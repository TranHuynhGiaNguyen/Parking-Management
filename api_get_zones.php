<?php
header("Content-Type: application/json; charset=utf-8");
include "db_connect.php";

$res = $conn->query("SELECT zone, max_slots, used, type FROM parking_zones ORDER BY zone");

if (!$res) {
    echo json_encode([
        "ok" => false,
        "error" => "DB query error"
    ]);
    exit;
}

$zones = [];
while ($r = $res->fetch_assoc()) {
    $zones[] = [
        "zone"      => $r["zone"],
        "type"      => $r["type"],  
        "max_slots" => (int)$r["max_slots"],
        "used"      => (int)$r["used"]
    ];
}

echo json_encode([
    "ok" => true,
    "zones" => $zones
]);
