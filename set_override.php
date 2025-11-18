<?php
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

$type = strtolower(trim($_POST['type'] ?? ''));
if (!in_array($type, ['car','motorcycle'])) { echo json_encode(['ok'=>false]); exit; }

$field = $type === 'car' ? 'override_car' : 'override_mc';
$ok = $conn->query("UPDATE lot_state SET $field=1 WHERE id=1");
echo json_encode(['ok' => (bool)$ok]);
