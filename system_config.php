<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Chặn user không phải admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}

// Lưu cấu hình
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
  $stmt->close();
  $saved = true;
}

// Load config
$res = $conn->query("SELECT * FROM system_config WHERE id=1");
$config = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cài đặt hệ thống</title>
  <link rel="stylesheet" href="assets/css/admin.css">

  <style>
    .config-box {
      max-width: 550px;
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
    .btn-save {
      margin-top: 20px;
      background: #2563eb;
      color: white;
      border: none;
      padding: 12px 16px;
      border-radius: 8px;
      cursor: pointer;
    }
    .msg { color: green; text-align:center; font-weight:bold; }
    .note {font-size:13px; color:#555; margin-top:8px;}
  </style>
</head>
<body>

  <!-- HEADER -->
  <header class="topbar">
    <div class="menu-btn" id="menuBtn"><span></span><span></span><span></span></div>
    <h2>⚙️ Cài đặt hệ thống</h2>
    <form action="logout.php" method="post">
      <button type="submit" class="logout-btn">Đăng xuất</button>
    </form>
  </header>

  <div class="admin-container">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
      <ul>
        <li><a href="admin_dashboard.php">👥 Quản lý người dùng</a></li>
        <li><a href="admin_dashboard.php#records">🚘 Lịch sử xe ra/vào</a></li>
        <li><a class="active" href="system_config.php">⚙️ Cài đặt hệ thống</a></li>
        <li><a href="time_fee_config.php">⏱️ Khung giờ & phí</a></li>
      </ul>
    </aside>

    <!-- CONTENT -->
    <main class="content">
      <div class="wrap">

        <div class="config-box">
          <?php if(!empty($saved)) echo '<p class="msg">Đã lưu cấu hình!</p>'; ?>

          <form method="POST">

            <label>Số chỗ tối đa ô tô</label>
            <input type="number" min="0" name="max_car" value="<?=htmlspecialchars($config['max_car'])?>">

            <label>Số chỗ tối đa xe máy</label>
            <input type="number" min="0" name="max_motorcycle" value="<?=htmlspecialchars($config['max_motorcycle'])?>">

            <label>Chế độ máy quét</label>
            <select name="scan_mode">
              <option value="in"   <?=$config['scan_mode']==='in'?'selected':''?>>Vào</option>
              <option value="out"  <?=$config['scan_mode']==='out'?'selected':''?>>Ra</option>
              <option value="both" <?=$config['scan_mode']==='both'?'selected':''?>>Vào & Ra</option>
            </select>

            <p class="note">👉 Lưu ý: Phí gửi xe đã chuyển sang cấu hình theo *khung giờ*.  
            Không còn sử dụng phí theo phút tại đây.</p>

            <button class="btn-save">Lưu thay đổi</button>
          </form>
        </div>

        <footer>© 2025 Parking Management System</footer>
      </div>
    </main>
  </div>

<script>
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });
</script>

</body>
</html>
