<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Chặn user không phải admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}

/* XỬ LÝ AJAX ZONE (ADD / UPDATE / DELETE / GET) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  header("Content-Type: application/json; charset=utf-8");
  $action = $_POST['action'];

  /* ========== ADD ZONE ========== */
  if ($action === "add_zone") {
    $zone = trim($_POST['zone']);
    $type = $_POST['type'] ?? 'MC';
    $max  = (int)($_POST['max'] ?? 0);

    if ($zone === "") {
      echo json_encode(['ok'=>false,'error'=>'Tên zone không hợp lệ']);
      exit;
    }

    // Check tồn tại
    $chk = $conn->prepare("SELECT zone FROM parking_zones WHERE zone=?");
    $chk->bind_param("s", $zone);
    $chk->execute();
    $chk->store_result();

    if ($chk->num_rows > 0) {
      echo json_encode(['ok'=>false,'error'=>'Zone đã tồn tại']);
      exit;
    }

    $ins = $conn->prepare("INSERT INTO parking_zones(zone,type,max_slots,used) VALUES(?,?,?,0)");
    $ins->bind_param("ssi", $zone, $type, $max);
    $ins->execute();

    echo json_encode(['ok'=>true]);
    exit;
  }

  /* ========== UPDATE ZONE ========== */
  if ($action === "update_zone") {
    $zone = $_POST['zone'] ?? '';
    $type = $_POST['type'] ?? '';
    $max  = (int)($_POST['max'] ?? 0);

    if (!$zone || !$type) {
      echo json_encode(['ok'=>false,'error'=>'Thiếu dữ liệu']);
      exit;
    }

    $up = $conn->prepare("UPDATE parking_zones SET type=?, max_slots=? WHERE zone=?");
    $up->bind_param("sis", $type, $max, $zone);
    $up->execute();

    echo json_encode(['ok'=>true]);
    exit;
  }

  /* ========== DELETE ZONE ========== */
  if ($action === "delete_zone") {
    $zone = $_POST['zone'] ?? '';

    if (!$zone) {
      echo json_encode(['ok'=>false,'error'=>'Thiếu zone']);
      exit;
    }

    $del = $conn->prepare("DELETE FROM parking_zones WHERE zone=?");
    $del->bind_param("s", $zone);
    $del->execute();

    echo json_encode(['ok'=>true]);
    exit;
  }

  exit;
}

/* LƯU CẤU HÌNH HỆ THỐNG */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_system'])) {
  $max_car         = max(0, (int)($_POST['max_car'] ?? 0));
  $max_motorcycle  = max(0, (int)($_POST['max_motorcycle'] ?? 0));
  $scan_mode       = $_POST['scan_mode'] ?? 'both';

  if (!in_array($scan_mode, ['in','out','both'])) {
    $scan_mode = 'both';
  }

  $stmt = $conn->prepare("
    UPDATE system_config
    SET max_car=?, max_motorcycle=?, scan_mode=?
    WHERE id=1
  ");
  $stmt->bind_param('iis', $max_car, $max_motorcycle, $scan_mode);
  $stmt->execute();
  $saved = true;
}

/* LOAD CONFIG + ZONES */
$config = $conn->query("SELECT * FROM system_config WHERE id=1")->fetch_assoc();

$zones = [];
$q = $conn->query("SELECT * FROM parking_zones ORDER BY zone ASC");
while ($r = $q->fetch_assoc()) $zones[] = $r;

?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cài đặt hệ thống</title>
  <link rel="stylesheet" href="assets/css/admin.css">

  <style>
    .config-box {
      max-width: 600px;
      background: #fff;
      padding: 20px;
      border-radius: 10px;
      margin: 20px auto;
      color: #111;
    }
    .config-box label {
      font-weight: bold;
      margin-top: 12px;
      display: block;
    }
    .config-box input, .config-box select {
      width: 100%;
      margin-top: 6px;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 16px;
    }
    th, td {
      padding: 10px;
      border-bottom: 1px solid #ddd;
      text-align: center;
    }
    th {
      background: #2563eb;
      color: #fff;
    }
    .btn {
      padding: 8px 14px;
      border-radius: 6px;
      border: none;
      cursor: pointer;
    }
    .btn-save { background:#2563eb;color:#fff; }
    .btn-add  { background:#16a34a;color:#fff;margin-top:8px; }
    .btn-del  { background:#dc2626;color:#fff; }
  </style>
</head>
<body>

<header class="topbar">
  <div class="menu-btn" id="menuBtn"><span></span><span></span><span></span></div>
  <h2>⚙️ Cài đặt hệ thống</h2>
  <form action="logout.php" method="post">
    <button class="logout-btn">Đăng xuất</button>
  </form>
</header>

<div class="admin-container">

<aside class="sidebar" id="sidebar">
  <ul>
    <li><a href="admin_dashboard.php">👥 Quản lý người dùng</a></li>
    <li><a href="admin_dashboard.php#records">🚘 Lịch sử xe ra/vào</a></li>
    <li><a href="#" onclick="showSection('revenue')">💰 Doanh thu</a></li>
    <li><a class="active" href="system_config.php">⚙️ Cài đặt hệ thống</a></li>
    <li><a href="time_fee_config.php">⏱️ Khung giờ & phí</a></li>
  </ul>
</aside>

<main class="content">
  <div class="wrap">

    <!-- SYSTEM CONFIG -->
    <div class="config-box">
      <?php if(!empty($saved)) echo '<p class="msg">Đã lưu cấu hình!</p>'; ?>

      <form method="POST">
        <input type="hidden" name="save_system" value="1">

        <label>Chế độ máy quét</label>
        <select name="scan_mode">
          <option value="in"   <?=$config['scan_mode']=='in'?'selected':''?>>Vào</option>
          <option value="out"  <?=$config['scan_mode']=='out'?'selected':''?>>Ra</option>
          <option value="both" <?=$config['scan_mode']=='both'?'selected':''?>>Vào & Ra</option>
        </select>

        <button class="btn btn-save">Lưu thay đổi</button>
      </form>
    </div>

    <!-- ZONE MANAGEMENT -->
    <div class="config-box">
      <h3>📦 Quản lý khu vực để xe</h3>

      <table>
        <tr>
          <th>Zone</th>
          <th>Loại</th>
          <th>Sức chứa</th>
          <th>Đang dùng</th>
          <th>Hành động</th>
        </tr>

        <?php foreach($zones as $z): ?>
        <tr>
          <td><?=$z['zone']?></td>

          <td>
            <select class="zone-type" data-zone="<?=$z['zone']?>">
              <option value="MC"  <?=$z['type']=='MC'?'selected':''?>>Xe máy</option>
              <option value="CAR" <?=$z['type']=='CAR'?'selected':''?>>Ô tô</option>
            </select>
          </td>

          <td>
            <input type="number" class="zone-max" data-zone="<?=$z['zone']?>" value="<?=$z['max_slots']?>">
          </td>

          <td><?=$z['used']?></td>

          <td><button class="btn btn-del" onclick="deleteZone('<?=$z['zone']?>')">Xóa</button></td>
        </tr>
        <?php endforeach; ?>

      </table>

      <h4>➕ Thêm zone mới</h4>

      <input id="new_zone" placeholder="VD: A1">
      <select id="new_type">
        <option value="MC">Xe máy</option>
        <option value="CAR">Ô tô</option>
      </select>
      <input id="new_max" type="number" min="0" placeholder="Sức chứa">

      <button class="btn btn-add" onclick="addZone()">Thêm</button>
    </div>

    <footer>© 2025 Parking Management System</footer>
  </div>
</main>

</div>

<script>
/* ========== UPDATE ZONE ========== */
document.querySelectorAll(".zone-type, .zone-max").forEach(el=>{
  el.addEventListener("change", async ()=>{
    const zone = el.dataset.zone;
    const type = document.querySelector(`.zone-type[data-zone="${zone}"]`).value;
    const max  = document.querySelector(`.zone-max[data-zone="${zone}"]`).value;

    await fetch("system_config.php", {
      method: "POST",
      body: new URLSearchParams({
        action: "update_zone",
        zone, type, max
      })
    });
  });
});

/* ========== ADD ZONE ========== */
async function addZone(){
  const zone = document.getElementById("new_zone").value.trim();
  const type = document.getElementById("new_type").value;
  const max  = document.getElementById("new_max").value;

  if(!zone || !max){
    alert("Vui lòng nhập đầy đủ");
    return;
  }

  const r = await fetch("system_config.php", {
    method: "POST",
    body: new URLSearchParams({
      action: "add_zone",
      zone, type, max
    })
  });

  const j = await r.json();
  if(j.ok){ location.reload(); }
  else alert(j.error);
}

/* ========== DELETE ZONE ========== */
async function deleteZone(zone){
  if(!confirm("Xóa zone " + zone + "?")) return;

  const r = await fetch("system_config.php", {
    method: "POST",
    body: new URLSearchParams({
      action: "delete_zone",
      zone
    })
  });

  const j = await r.json();
  if(j.ok) location.reload();
  else alert(j.error);
}
</script>

</body>
</html>
