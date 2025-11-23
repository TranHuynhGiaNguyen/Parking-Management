<?php
date_default_timezone_set('Asia/Ho_Chi_Minh');
require_once __DIR__ . '/db_connect.php';

header('Content-Type: application/json; charset=utf-8');

$session_id = isset($_POST['session_id']) ? (int)$_POST['session_id'] : null;
$uid        = trim($_POST['uid']   ?? '');
$plate      = trim($_POST['plate'] ?? '');
$action     = trim($_POST['action'] ?? '');

if (!isset($_FILES['face']) || !is_uploaded_file($_FILES['face']['tmp_name'])) {
    echo json_encode(['ok' => false, 'error' => 'Không có file ảnh']); 
    exit;
}

$folder = __DIR__ . '/faces';
if (!is_dir($folder)) {
    mkdir($folder, 0777, true);
}

$ts = date('Ymd_His');
$uid_safe   = preg_replace('/[^0-9A-Z]/i','', $uid) ?: 'UNKNOWN';
$sessionStr = $session_id ? ('SID'.$session_id) : 'NOSESSION';

$filename = "{$sessionStr}_{$uid_safe}_{$ts}.jpg";
$fullpath = $folder . '/' . $filename;

if (!move_uploaded_file($_FILES['face']['tmp_name'], $fullpath)) {
    echo json_encode(['ok' => false, 'error' => 'Lưu file thất bại']); 
    exit;
}

$rel_path = 'faces/'.$filename;

$stmt = $conn->prepare("
    INSERT INTO face_captures(session_id, uid, plate, img_path, captured_at)
    VALUES(?,?,?,?,NOW())
");
$stmt->bind_param(
    "isss",
    $session_id,
    $uid,
    $plate,
    $rel_path
);
$stmt->execute();
$stmt->close();

echo json_encode([
    'ok' => true,
    'img' => $rel_path
]);
