<?php
session_start();
include 'db_connect.php';

// Chặn cache quay lại trang login
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// --- Kiểm tra quyền admin ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}

// === Thêm người dùng ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
  $username = trim($_POST['username']);
  $password = trim($_POST['password']);
  $full_name = trim($_POST['full_name']);
  $role = trim($_POST['role']);

  if ($username && $password && $role) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $username, $hashed, $full_name, $role);
    $stmt->execute();
    $stmt->close();
  }
  header("Location: admin_dashboard.php");
  exit;
}

// === Xóa người dùng ===
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id !== $_SESSION['user']['id']) {
    $conn->query("DELETE FROM users WHERE id=$id");
  }
  header("Location: admin_dashboard.php");
  exit;
}

// === Lấy danh sách users ===
$users = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC");

// === Lấy 10 bản ghi mới nhất từ bảng records ===
$records = $conn->query("SELECT * FROM records ORDER BY id DESC LIMIT 10");

// === DỮ LIỆU DOANH THU ===
$revenue_total = $conn->query("SELECT SUM(fee) AS total FROM vehicle_sessions")->fetch_assoc()['total'] ?? 0;

$revenue_today = $conn->query("
    SELECT SUM(fee) AS total 
    FROM vehicle_sessions 
    WHERE DATE(out_time) = CURDATE()
")->fetch_assoc()['total'] ?? 0;

$revenue_count = $conn->query("
    SELECT COUNT(*) AS cnt 
    FROM vehicle_sessions 
    WHERE fee > 0
")->fetch_assoc()['cnt'] ?? 0;

$revenue_table = $conn->query("
    SELECT id, uid, plate, fee, in_time, out_time 
    FROM vehicle_sessions 
    WHERE fee > 0 
    ORDER BY out_time DESC
    LIMIT 200
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <title>Bảng điều khiển quản trị</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>

<body>

<!-- HEADER -->
<header class="topbar">
  <div class="menu-btn" id="menuBtn">
    <span></span><span></span><span></span>
  </div>
  <h2>🚗 Bảng điều khiển Quản trị</h2>
  <form action="logout.php" method="post">
    <button type="submit" class="logout-btn">Đăng xuất</button>
  </form>
</header>

<div class="admin-container">

  <!-- SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <ul>
      <li><a href="#" class="active" onclick="showSection('users')">👥 Quản lý người dùng</a></li>
      <li><a href="#" onclick="showSection('records')">🚘 Lịch sử xe</a></li>
      <li><a href="#" onclick="showSection('revenue')">💰 Doanh thu</a></li>
      <li><a href="system_config.php">⚙️ Cài đặt hệ thống</a></li>
      <li><a href="time_fee_config.php">⏱️ Khung giờ & phí</a></li>
    </ul>
  </aside>

  <!-- CONTENT -->
  <main class="content">
    <div class="wrap">

      <!-- USERS -->
      <section id="users" class="section active">
        <h2>👥 Quản lý người dùng</h2>

        <form method="POST" class="add-user-form">
          <input type="text" name="username" placeholder="Tên đăng nhập" required>
          <input type="text" name="full_name" placeholder="Họ và tên">
          <input type="password" name="password" placeholder="Mật khẩu" required>
          <select name="role" required>
            <option value="baove">Bảo vệ</option>
            <option value="admin">Quản trị</option>
          </select>
          <button type="submit" name="add_user" class="btn-add">+ Thêm</button>
        </form>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Tên đăng nhập</th>
              <th>Họ và tên</th>
              <th>Vai trò</th>
              <th>Thao tác</th>
            </tr>
          </thead>

          <tbody>
          <?php while($row = $users->fetch_assoc()): ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['username']) ?></td>
              <td><?= htmlspecialchars($row['full_name']) ?></td>
              <td><?= htmlspecialchars($row['role']) ?></td>
              <td>
                <button class="btn-edit" onclick="openEditModal(
                    <?= $row['id'] ?>,
                    '<?= htmlspecialchars($row['username']) ?>',
                    '<?= htmlspecialchars($row['full_name']) ?>',
                    '<?= $row['role'] ?>'
                )">Sửa</button>

                <?php if ($row['id'] != $_SESSION['user']['id']): ?>
                  <a href="?delete=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Xóa người dùng?')">Xóa</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </section>


      <!-- LỊCH SỬ XE -->
      <section id="records" class="section">
        <h2>🚘 Lịch sử xe ra/vào</h2>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>UID</th>
              <th>Biển số</th>
              <th>Loại xe</th>
              <th>Giờ vào</th>
              <th>Giờ ra</th>
              <th>Phí</th>
              <th>Trạng thái</th>
            </tr>
          </thead>

          <tbody>
          <?php
            $v = $conn->query("SELECT * FROM vehicle_sessions ORDER BY in_time DESC LIMIT 100");
          ?>
          <?php while($r = $v->fetch_assoc()): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['uid']) ?></td>
              <td><?= htmlspecialchars($r['plate']) ?></td>
              <td><?= htmlspecialchars($r['vehicle_type']) ?></td>
              <td><?= $r['in_time'] ?></td>
              <td><?= $r['out_time'] ?: "-" ?></td>
              <td><?= number_format($r['fee'],0,',','.') ?></td>
              <td>
                <?= $r['out_time'] ? "<span style='color:#e67e22;'>Đã ra</span>" : "<span style='color:green;'>Đang gửi</span>" ?>
              </td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </section>


      <!-- DOANH THU -->
      <section id="revenue" class="section">
        <h2>💰 Quản lý doanh thu</h2>

        <div style="display:flex; gap:20px; margin-bottom:20px;">

          <div style="flex:1; background:#fff; padding:15px; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <h3 style="margin:0; color:#2563eb;">Doanh thu hôm nay</h3>
            <p style="font-size:26px; font-weight:600; margin:5px 0;">
              <?= number_format($revenue_today,0,',','.') ?> VNĐ
            </p>
          </div>

          <div style="flex:1; background:#fff; padding:15px; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <h3 style="margin:0; color:#2563eb;">Tổng doanh thu</h3>
            <p style="font-size:26px; font-weight:600; margin:5px 0;">
              <?= number_format($revenue_total,0,',','.') ?> VNĐ
            </p>
          </div>

          <div style="flex:1; background:#fff; padding:15px; border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1);">
            <h3 style="margin:0; color:#2563eb;">Số lượt thu phí</h3>
            <p style="font-size:26px; font-weight:600; margin:5px 0;">
              <?= $revenue_count ?>
            </p>
          </div>

        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>UID</th>
              <th>Biển số</th>
              <th>Phí</th>
              <th>Giờ vào</th>
              <th>Giờ ra</th>
            </tr>
          </thead>

          <tbody>
          <?php while($r = $revenue_table->fetch_assoc()): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= $r['uid'] ?></td>
              <td><?= $r['plate'] ?></td>
              <td><?= number_format($r['fee'],0,',','.') ?></td>
              <td><?= $r['in_time'] ?></td>
              <td><?= $r['out_time'] ?></td>
            </tr>
          <?php endwhile; ?>
          </tbody>
        </table>
      </section>

      <footer>© 2025 Parking Management System</footer>

    </div>
  </main>
</div>


<!-- Modal sửa -->
<div id="editModal" class="modal">
  <div class="modal-content">
    <h3>✏️ Sửa người dùng</h3>
    <form method="POST" action="edit_user.php">
      <input type="hidden" name="id" id="editId">
      <input type="text" name="username" id="editUsername" readonly>
      <input type="text" name="full_name" id="editFullName">
      <select name="role" id="editRole">
        <option value="baove">Bảo vệ</option>
        <option value="admin">Quản trị</option>
      </select>
      <input type="password" name="password" id="editPassword" placeholder="Mật khẩu mới (nếu đổi)">
      <div class="modal-actions">
        <button type="submit" class="btn-save">Lưu</button>
        <button type="button" class="btn-cancel" onclick="closeEditModal()">Hủy</button>
      </div>
    </form>
  </div>
</div>


<script>
  // Toggle sidebar
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('hidden');
  });

  // Chuyển section
  function showSection(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');

    document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
    document.querySelector(`.sidebar a[onclick="showSection('${id}')"]`).classList.add('active');
  }

  // Modal sửa
  function openEditModal(id, username, fullname, role) {
    document.getElementById('editModal').style.display = 'flex';
    document.getElementById('editId').value = id;
    document.getElementById('editUsername').value = username;
    document.getElementById('editFullName').value = fullname;
    document.getElementById('editRole').value = role;
  }
  function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
  }
</script>

</body>
</html>
