<?php
session_start();
require_once __DIR__ . '/db_connect.php';

// Chặn user không phải admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}

// Xử lý thêm khung giờ
if (isset($_POST['add'])) {
  $start = $_POST['start_time'];
  $end   = $_POST['end_time'];
  $car   = (int)$_POST['fee_car'];
  $mc    = (int)$_POST['fee_mc'];

  if ($start && $end && $car >= 0 && $mc >= 0) {
    $stmt = $conn->prepare("
      INSERT INTO fee_time_ranges(start_time, end_time, fee_car_per_hour, fee_mc_per_hour)
      VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("ssii", $start, $end, $car, $mc);
    $stmt->execute();
    $stmt->close();
  }
}

// Xử lý xóa
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  $conn->query("DELETE FROM fee_time_ranges WHERE id=$id LIMIT 1");
}

// Load danh sách khung giờ
$list = $conn->query("SELECT * FROM fee_time_ranges ORDER BY start_time ASC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Cấu hình khung giờ & phí</title>
  <link rel="stylesheet" href="assets/css/admin.css">
  <style>
    .box {max-width: 800px; padding:20px; background:white; margin:20px auto; border-radius:10px;}
    table {width:100%; border-collapse:collapse; margin-top:20px;}
    th,td {padding:10px; border:1px solid #ddd; text-align:center;}
    th {background:#f3f3f3;}
    input {padding:8px; width:100%; border-radius:6px; border:1px solid #ccc;}
    .btn {padding:8px 14px; border-radius:6px; cursor:pointer; border:none;}
    .btn-add {background:#2563eb; color:white;}
    .btn-del {background:#ef4444; color:white;}
  </style>
</head>

<body>

<header class="topbar">
  <div class="menu-btn" id="menuBtn"><span></span><span></span><span></span></div>
  <h2>⏱️ Cấu hình phí theo khung giờ</h2>
  <form method="post" action="logout.php"><button class="logout-btn">Đăng xuất</button></form>
</header>

<div class="admin-container">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <ul>
      <li><a href="admin_dashboard.php">👥 Quản lý người dùng</a></li>
      <li><a href="admin_dashboard.php#records">🚘 Lịch sử xe ra/vào</a></li>
      <li><a href="system_config.php">⚙️ Cài đặt hệ thống</a></li>
      <li><a class="active" href="time_fee_config.php">⏱️ Khung giờ & phí</a></li>
    </ul>
  </aside>

  <main class="content">
    <div class="wrap">

      <div class="box">
        <h3>➕ Thêm khung giờ mới</h3>

        <form method="post" style="display:grid; grid-template-columns: repeat(4,1fr); gap:10px;margin-bottom:20px;">
          <div><label>Bắt đầu</label><input type="time" name="start_time" required></div>
          <div><label>Kết thúc</label><input type="time" name="end_time" required></div>
          <div><label>Phí ô tô (₫/giờ)</label><input type="number" name="fee_car" min="0" required></div>
          <div><label>Phí xe máy (₫/giờ)</label><input type="number" name="fee_mc" min="0" required></div>

          <button name="add" class="btn btn-add" style="grid-column: span 4; margin-top:10px;">Thêm khung giờ</button>
        </form>

        <h3>📋 Danh sách khung giờ</h3>
        <table>
          <tr>
            <th>ID</th>
            <th>Bắt đầu</th>
            <th>Kết thúc</th>
            <th>Ô tô (₫/giờ)</th>
            <th>Xe máy (₫/giờ)</th>
            <th></th>
          </tr>

          <?php while($r = $list->fetch_assoc()): ?>
          <tr>
            <td><?=$r['id']?></td>
            <td><?=$r['start_time']?></td>
            <td><?=$r['end_time']?></td>
            <td><?=number_format($r['fee_car_per_hour'])?></td>
            <td><?=number_format($r['fee_mc_per_hour'])?></td>
            <td>
              <a class="btn btn-del" href="?delete=<?=$r['id']?>" onclick="return confirm('Xóa khung giờ này?')">Xóa</a>
            </td>
          </tr>
          <?php endwhile; ?>

        </table>

      </div>

      <footer>© 2025 Parking Management System</footer>
    </div>
  </main>
</div>

<script>
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  menuBtn.addEventListener('click', ()=> sidebar.classList.toggle('hidden'));
</script>

</body>
</html>
