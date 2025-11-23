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
  <link rel="stylesheet" href="assets/css/vehicle_history.css">
</head>

<body>

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

<nav id="sidebar">
  <ul>
    <li><a href="scan_auto.php">🎥 Trực tiếp</a></li>
    <li><a href="vehicle_history.php" class="active">🧾 Danh sách xe ra/vào</a></li>
  </ul>
</nav>

<div class="content">
  <div class="wrap">
    <section class="panel">
      <h2>📋 Lịch sử xe ra/vào</h2>
      <!-- Search box -->
      <div style="margin-top: 10px; margin-bottom: 15px;">
        <input id="searchInput" type="text" placeholder="Tìm biển số, loại xe..." 
              style="width: 300px; padding: 10px; border:1px solid #ccc; border-radius:6px;">
      </div>

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
              <th>Ảnh mặt</th>
            </tr>
          </thead>

          <tbody>
          <?php if ($result && $result->num_rows > 0): ?>
            <?php while($row = $result->fetch_assoc()):
                $status = $row['out_time']
                            ? '<span class="status-out">Đã ra</span>'
                            : '<span class="status-in">Đang gửi</span>';
            ?>
            <tr>
              <td><?= $row['id'] ?></td>
              <td><?= htmlspecialchars($row['uid']) ?></td>
              <td><?= htmlspecialchars($row['plate']) ?></td>
              <td><?= htmlspecialchars($row['vehicle_type']) ?></td>
              <td><?= htmlspecialchars($row['in_time']) ?></td>
              <td><?= htmlspecialchars($row['out_time'] ?? '-') ?></td>
              <td><?= $status ?></td>
              <td><?= number_format($row['fee'], 0, ',', '.') ?></td>

              <!-- Hiển thị nút Xem Ảnh -->
              <td>
                <?php
                  $sid = (int)$row['id'];
                  $face = $conn->query("
                    SELECT img_path 
                    FROM face_captures 
                    WHERE session_id = $sid 
                    ORDER BY id DESC 
                    LIMIT 1
                  ");

                  if ($face && $face->num_rows) {
                      $img = $face->fetch_assoc()['img_path'];
                      echo "<button class='view-face-btn' data-img='$img'>Xem ảnh</button>";
                  } else {
                      echo "<span style='color:#888'>Không có</span>";
                  }
                ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">Không có dữ liệu xe ra/vào.</td></tr>
          <?php endif; ?>
          </tbody>

        </table>
      </div>
    </section>

    <footer>© 2025 Parking Management System</footer>
  </div>
</div>


<!-- POPUP HIỂN THỊ ẢNH FULL -->
<div id="faceModal" class="face-modal">
  <span class="close-modal">&times;</span>
  <img id="faceModalImg" src="" alt="Face Image">
</div>


<script>
// Sidebar toggle
const sidebar = document.getElementById('sidebar');
const menuBtn = document.getElementById('menuBtn');
menuBtn.addEventListener('click', () => {
  sidebar.classList.toggle('active');
});

// ===== Xem ảnh full =====
document.querySelectorAll(".view-face-btn").forEach(btn => {
    btn.addEventListener("click", function() {
        const img = this.dataset.img;
        const modal = document.getElementById("faceModal");
        const modalImg = document.getElementById("faceModalImg");

        modalImg.src = img;
        modal.style.display = "block";
    });
});

document.querySelector(".close-modal").addEventListener("click", () => {
    document.getElementById("faceModal").style.display = "none";
});

document.getElementById("faceModal").addEventListener("click", (e) => {
    if (e.target.id === "faceModal") {
        e.target.style.display = "none";
    }
});
// ===== TÌM KIẾM REALTIME =====
document.getElementById("searchInput").addEventListener("keyup", function(){
    const keyword = this.value.toLowerCase();
    const rows = document.querySelectorAll("tbody tr");

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(keyword) ? "" : "none";
    });
}); 
</script>

</body>
</html>
