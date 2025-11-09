<?php
// anpr_proxy.php
define('MODE', 'CLOUD_MODE'); // 'CLOUD_MODE' hoặc 'LOCAL_MODE'

// CLOUD (PlateRecognizer)
$PLATE_API_URL   = 'https://api.platerecognizer.com/v1/plate-reader/';
$PLATE_API_TOKEN = '87ba5243d6a596480ddd28d7a32e4e6cb6b54f42';

// LOCAL (tự host FastAPI)
$LOCAL_ANPR_URL  = 'http://127.0.0.1:8000/anpr';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405); echo json_encode(['error'=>'Only POST allowed']); exit;
}
if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
  http_response_code(400); echo json_encode(['error'=>'No image']); exit;
}

$tmp  = $_FILES['image']['tmp_name'];
$mime = mime_content_type($tmp) ?: 'image/jpeg';

try {
  if (MODE === 'CLOUD_MODE') {
    $ch = curl_init();
    $cfile = new CURLFile($tmp, $mime, 'frame.jpg');
    $post  = ['upload' => $cfile]; // field đúng cho PlateRecognizer

    curl_setopt_array($ch, [
      CURLOPT_URL => $PLATE_API_URL,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => $post,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => ['Authorization: Token '.$PLATE_API_TOKEN],
      CURLOPT_TIMEOUT => 45,
      CURLOPT_CONNECTTIMEOUT => 15,
      CURLOPT_FOLLOWLOCATION => true,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) {
      $err = curl_error($ch);
      curl_close($ch);
      http_response_code(502);
      echo json_encode(['error'=>'Curl error','detail'=>$err]); exit;
    }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 300) {
      http_response_code(502);
      echo json_encode(['error'=>'Upstream HTTP '.$code,'detail'=>$raw]); exit;
    }

    // Chuẩn hóa về {plate, confidence, bbox}
    $resp = json_decode($raw, true);
    $plate = null; $conf = 0.0; $bbox = null;
    if (isset($resp['results'][0])) {
      $r = $resp['results'][0];
      $plate = strtoupper($r['plate'] ?? '');
      $conf  = $r['score'] ?? 0.0;
      $bbox  = $r['box']   ?? null;
    }
    echo json_encode(['plate'=>$plate,'confidence'=>$conf,'bbox'=>$bbox], JSON_UNESCAPED_UNICODE); exit;

  } elseif (MODE === 'LOCAL_MODE') {
    $ch = curl_init();
    $cfile = new CURLFile($tmp, $mime, 'frame.jpg');
    curl_setopt_array($ch, [
      CURLOPT_URL => $LOCAL_ANPR_URL,
      CURLOPT_POST => true,
      CURLOPT_POSTFIELDS => ['image'=>$cfile],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 45,
      CURLOPT_CONNECTTIMEOUT => 15,
    ]);

    $raw = curl_exec($ch);
    if ($raw === false) { $err = curl_error($ch); curl_close($ch);
      http_response_code(502); echo json_encode(['error'=>'Curl error','detail'=>$err]); exit; }
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code >= 300) { http_response_code(502); echo json_encode(['error'=>'Upstream HTTP '.$code,'detail'=>$raw]); exit; }

    echo $raw; exit;

  } else {
    throw new Exception('MODE không hợp lệ');
  }
} catch (Exception $e) {
  http_response_code(502);
  echo json_encode(['error'=>$e->getMessage()]);
}
