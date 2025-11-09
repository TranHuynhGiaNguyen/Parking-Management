<?php
session_start();
include 'db_connect.php';

$error = '';

// --- Kiểm tra đăng nhập ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = trim($_POST['password'] ?? '');

  if ($username === '' || $password === '') {
    $error = "Vui lòng nhập đầy đủ thông tin!";
  } else {
    $stmt = $conn->prepare("SELECT id, username, password, full_name, role FROM users WHERE username=? LIMIT 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
      // Nếu mật khẩu lưu dạng HASH
      if (password_verify($password, $row['password'])) {
        $_SESSION['user'] = [
          'id' => $row['id'],
          'username' => $row['username'],
          'full_name' => $row['full_name'],
          'role' => $row['role']
        ];

        if ($row['role'] === 'baove' || $row['role'] === 'security') {
          header('Location: scan_auto.php');
        } elseif ($row['role'] === 'admin') {
          header('Location: admin.php');
        } else {
          $error = "Tài khoản không có quyền truy cập hệ thống!";
        }
        exit;
      } 
      // Nếu mật khẩu CSDL lưu dạng plain (vd: '123456')
      elseif ($password === $row['password']) {
        $_SESSION['user'] = [
          'id' => $row['id'],
          'username' => $row['username'],
          'full_name' => $row['full_name'],
          'role' => $row['role']
        ];

        if ($row['role'] === 'baove' || $row['role'] === 'security') {
          header('Location: scan_auto.php');
        } elseif ($row['role'] === 'admin') {
          header('Location: admin_dashboard.php');
        } else {
          $error = "Tài khoản không có quyền truy cập hệ thống!";
        }
        exit;
      } 
      else {
        $error = "Sai mật khẩu!";
      }
    } else {
      $error = "Tên đăng nhập không tồn tại!";
    }
    $stmt->close();
  }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập hệ thống</title>
  <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
  <div class="login-container">
    <div class="login-card">
      <div class="login-header">
        <div class="company-logo">
          <div class="logo-icon">🚗</div>
        </div>
        <h2>Đăng nhập hệ thống</h2>
        <p>Nhập tên đăng nhập và mật khẩu để truy cập</p>
      </div>

      <form class="login-form" id="loginForm" method="POST">
        <?php if (!empty($error)): ?>
          <div class="error-box active"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="form-group">
          <div class="input-wrapper">
            <input type="text" id="username" name="username" required autocomplete="username">
            <label for="username">Tên đăng nhập</label>
          </div>
          <span class="error-message" id="usernameError"></span>
        </div>

        <div class="form-group">
          <div class="input-wrapper password-wrapper">
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <label for="password">Mật khẩu</label>
            <button type="button" class="password-toggle" id="passwordToggle" aria-label="Hiện/ẩn mật khẩu">
              <span class="toggle-icon"></span>
            </button>
          </div>
          <span class="error-message" id="passwordError"></span>
        </div>

        <button type="submit" class="login-btn">
          <span class="btn-text">Đăng nhập</span>
        </button>
      </form>
    </div>
  </div>

  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('passwordToggle');
    const pass = document.getElementById('password');
    toggle.addEventListener('click', () => {
      pass.type = pass.type === 'password' ? 'text' : 'password';
      toggle.classList.toggle('show');
    });
  });
  </script>
</body>
</html>
