<?php
session_start();
include 'db_connect.php';
// --- Kiểm tra quyền truy cập ---
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  header('Location: index.php');
  exit;
}


// === Xử lý thêm người dùng ===
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

// === Xử lý xóa ===
if (isset($_GET['delete'])) {
  $id = (int)$_GET['delete'];
  if ($id !== $_SESSION['user']['id']) {
    $conn->query("DELETE FROM users WHERE id=$id");
  }
  header("Location: admin_dashboard.php");
  exit;
}

// === Lấy dữ liệu ===
$users = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY id DESC");
$records = $conn->query("SELECT * FROM records ORDER BY id DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bảng điều khiển quản trị</title>
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>
  <header class="admin-header">
    <h1>🚗 Hệ thống bãi xe - Quản trị</h1>
    <div class="user-info">
      <span>Xin chào, <b><?php echo htmlspecialchars($_SESSION['user']['full_name']); ?></b></span>
      <a href="logout.php" class="logout-btn">Đăng xuất</a>
    </div>
  </header>

  <div class="admin-container">
    <aside class="sidebar">
      <ul>
        <li><a href="#users" class="active">👥 Quản lý người dùng</a></li>
        <li><a href="#records">🚘 Lịch sử xe ra/vào</a></li>
        <li><a href="#settings">⚙️ Cài đặt hệ thống</a></li>
      </ul>
    </aside>

    <main class="content">
      <!-- QUẢN LÝ NGƯỜI DÙNG -->
      <section id="users" class="section active">
        <h2>👥 Danh sách người dùng</h2>

        <!-- Thêm người dùng -->
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

        <!-- Danh sách người dùng -->
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
                  <button class="btn-edit" 
                    onclick="editUser(<?= $row['id'] ?>, '<?= $row['username'] ?>', '<?= htmlspecialchars($row['full_name']) ?>', '<?= $row['role'] ?>')">Sửa</button>
                  <?php if ($row['id'] != $_SESSION['user']['id']): ?>
                    <a href="?delete=<?= $row['id'] ?>" class="btn-delete" onclick="return confirm('Xóa tài khoản này?')">Xóa</a>
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
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($records && $records->num_rows > 0): ?>
              <?php while($r = $records->fetch_assoc()): ?>
                <tr>
                  <td><?= $r['id'] ?></td>
                  <td><?= htmlspecialchars($r['uid']) ?></td>
                  <td><?= htmlspecialchars($r['plate']) ?></td>
                  <td><?= htmlspecialchars($r['vehicle_type']) ?></td>
                  <td><?= htmlspecialchars($r['created_at'] ?? '') ?></td>
                </tr>
              <?php endwhile; ?>
            <?php else: ?>
              <tr><td colspan="5">Không có dữ liệu</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <section id="settings" class="section">
        <h2>⚙️ Cài đặt hệ thống</h2>
        <p>Trang này đang được phát triển...</p>
      </section>
    </main>
  </div>

  <!-- Modal sửa -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <h3>✏️ Sửa tài khoản</h3>
      <form method="POST" action="edit_user.php">
        <input type="hidden" name="id" id="editId">
        <input type="text" name="username" id="editUsername" readonly>
        <input type="text" name="full_name" id="editFullName" placeholder="Họ và tên">
        <select name="role" id="editRole">
          <option value="baove">Bảo vệ</option>
          <option value="admin">Quản trị</option>
        </select>
        <input type="password" name="password" id="editPassword" placeholder="Mật khẩu mới (nếu đổi)">
        <div class="modal-actions">
          <button type="submit" class="btn-save">Lưu</button>
          <button type="button" class="btn-cancel" onclick="closeModal()">Hủy</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    // Đổi tab
    document.querySelectorAll('.sidebar a').forEach(link => {
      link.addEventListener('click', e => {
        e.preventDefault();
        document.querySelectorAll('.sidebar a').forEach(a => a.classList.remove('active'));
        link.classList.add('active');
        const id = link.getAttribute('href').substring(1);
        document.querySelectorAll('.section').forEach(sec => sec.classList.remove('active'));
        document.getElementById(id).classList.add('active');
      });
    });

    // Hiển thị modal sửa
    function editUser(id, username, fullname, role) {
      document.getElementById('editId').value = id;
      document.getElementById('editUsername').value = username;
      document.getElementById('editFullName').value = fullname;
      document.getElementById('editRole').value = role;
      document.getElementById('editModal').style.display = 'flex';
    }
    function closeModal() {
      document.getElementById('editModal').style.display = 'none';
    }
  </script>
</body>
</html>
