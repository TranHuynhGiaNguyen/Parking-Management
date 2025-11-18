<?php
session_start();
include 'db_connect.php';

// --- Kiểm tra đăng nhập ---
if (!isset($_SESSION['user'])) {
  header('Location: index.php');
  exit;
}

// --- Chỉ cho phép bảo vệ truy cập ---
if ($_SESSION['user']['role'] !== 'baove') {
  header('Location: index.php');
  exit;
}

// --- Lấy danh sách xe ra/vào ---
$sql = "SELECT * FROM vehicle_sessions ORDER BY in_time DESC";
$result = $conn->query($sql);
?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Danh sách xe ra/vào</title>
  <link rel="stylesheet" href="assets/css/scan_auto.css">
  <style>
    .table-wrap {overflow-x:auto; margin-top:16px;}
    table {width:100%; border-collapse:collapse; background:var(--panel);}
    th, td {padding:8px 10px; border:1px solid #ccc; text-align:center;}
    th {background:var(--muted); color:#333;}
    tr:nth-child(even){background:rgba(0,0,0,0.03);}
    .status-in {color:green; font-weight:600;}
    .status-out {color:#e67e22; font-weight:600;}
    h2 {display:flex; align-items:center; gap:8px;}
  </style>
</head>
<body>
  <!-- Header -->
  <header class="topbar">
    <div class="menu-btn" id="menuBtn">
      <span></span>
      <span></span>
      <span></span>
    </div>

    <div class="header-title">
      <h1>🧾 Danh sách xe ra/vào</h1>
      <p>Ghi nhận tự động từ trạm quét</p>
    </div>

    <div class="user-controls">
      <form action="logout.php" method="post" style="display:inline;">
        <button type="submit" class="logout-btn">Đăng xuất</button>
      </form>
    </div>
  </header>

  <!-- Sidebar -->
  <nav id="sidebar">
    <ul>
      <li><a href="scan_auto.php">🎥 Trực tiếp</a></li>
      <li><a href="vehicle_history.php" class="active">🧾 Danh sách xe ra/vào</a></li>
    </ul>
  </nav>

  <!-- Nội dung chính -->
  <div class="content">
    <div class="wrap">
      <section class="panel">
        <h2>📋 Lịch sử xe ra/vào</h2>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>UID RFID</th>
                <th>Biển số</th>
                <th>Loại xe</th>
                <th>Thời gian vào</th>
                <th>Thời gian ra</th>
                <th>Trạng thái</th>
                <th>Phí (VNĐ)</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): 
                  $status = $row['out_time'] ? '<span class="status-out">Đã ra</span>' : '<span class="status-in">Đang gửi</span>';
                ?>
                <tr>
                  <td><?= htmlspecialchars($row['id']) ?></td>
                  <td><?= htmlspecialchars($row['uid']) ?></td>
                  <td><?= htmlspecialchars($row['plate']) ?></td>
                  <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
                  <td><?= htmlspecialchars($row['in_time']) ?></td>
                  <td><?= htmlspecialchars($row['out_time'] ?? '-') ?></td>
                  <td><?= $status ?></td>
                  <td><?= number_format($row['fee'], 0, ',', '.') ?></td>
                </tr>
                <?php endwhile; ?>
              <?php else: ?>
                <tr><td colspan="8">Không có dữ liệu xe ra/vào.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <footer>© 2025 Parking Management System</footer>
    </div>
  </div>

<script>
  // Sidebar toggle
  const sidebar = document.getElementById('sidebar');
  const menuBtn = document.getElementById('menuBtn');
  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
  });

  // Các link chưa làm
  document.querySelectorAll('#sidebar a[data-page]').forEach(a=>{
    a.addEventListener('click',(e)=>{ e.preventDefault(); alert('Tính năng đang phát triển'); });
  });
</script>
</body>
</html>
