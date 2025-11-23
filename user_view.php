<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin bãi giữ xe</title>
    <link rel="stylesheet" href="assets/css/user_view.css">
</head>

<body>
    <header class="header">
        <h1 class="header-title">🚗 Thông tin bãi giữ xe</h1>
        <div class="status-badge">
            <span class="status-indicator" id="statusIndicator"></span>
            <span id="statusText">Online</span>
        </div>
    </header>

    <main class="main-content">
        <div class="container">

            <section class="fade-in">
                <div class="card">
                    <div class="card-title">Phí gửi xe theo khung giờ</div>
                    <div class="table-container">
                        <table class="table" id="fee_table">
                            <thead>
                                <tr>
                                    <th>Khung giờ</th>
                                    <th>Phí ô tô (đ/giờ)</th>
                                    <th>Phí xe máy (đ/giờ)</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section class="fade-in">
                <div class="card">
                    <div class="card-title">Tra cứu xe của bạn</div>
                    <div class="search-form">
                        <input id="search_kw" class="search-input" placeholder="Nhập biển số xe VD:94AE10102" type="text">
                        <button class="btn" onclick="lookupUser()">
                            <span id="search-btn-text">Tra cứu</span>
                            <span id="search-loading" class="loading" style="display:none;"></span>
                        </button>
                    </div>

                    <div id="lookup_result" class="search-result"></div>
                </div>
            </section>

        </div>
    </main>

<script>
function loadInfo() {
    fetch("api_public_info.php")
        .then(r => r.json())
        .then(d => {
            if (!d.ok) return;
            const tbody = document.querySelector("#fee_table tbody");
            let html = '';
            d.fee_ranges.forEach(f => {
                html += `
                    <tr>
                        <td><strong>${f.start} - ${f.end}</strong></td>
                        <td>${Number(f.car).toLocaleString('vi-VN')} đ</td>
                        <td>${Number(f.mc).toLocaleString('vi-VN')} đ</td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            updateStatus(true);
        })
        .catch(() => updateStatus(false));
}

function lookupUser() {
    const kw = document.getElementById("search_kw").value.trim();
    if (!kw) {
        showResult("<span style='color:#d9534f;'>⚠️ Nhập UID hoặc Biển số</span>");
        return;
    }

    document.getElementById('search-btn-text').style.display = 'none';
    document.getElementById('search-loading').style.display = 'inline-block';

    fetch("api_user_lookup.php?q=" + encodeURIComponent(kw))
        .then(r => r.json())
        .then(d => {
            document.getElementById('search-btn-text').style.display = 'inline-block';
            document.getElementById('search-loading').style.display = 'none';

            if (!d.ok) {
                showResult(`<span style='color:#d9534f;'>❌ ${d.msg}</span>`);
                return;
            }

            const x = d.data;
            const html = `
                <div class="result-item"><span class="result-label">🆔 UID:</span><span class="result-value">${x.uid}</span></div>
                <div class="result-item"><span class="result-label">🚗 Biển số:</span><span class="result-value"><strong>${x.plate}</strong></span></div>
                <div class="result-item"><span class="result-label">🏍️ Loại xe:</span><span class="result-value">${x.vehicle_type}</span></div>
                <div class="result-item"><span class="result-label">📍 Vị trí đậu:</span><span class="result-value">${x.zone}</span></div>
                <div class="result-item"><span class="result-label">⏰ Vào lúc:</span><span class="result-value">${x.in_time}</span></div>
                <div class="result-item"><span class="result-label">🚪 Ra lúc:</span><span class="result-value">${x.out_time || "Đang gửi"}</span></div>
                <div class="result-item"><span class="result-label">💰 Phí hiện tại:</span>
                    <span class="result-value highlight-fee">${Number(x.fee).toLocaleString("vi-VN")} đ</span>
                </div>
            `;
            showResult(html);
        })
        .catch(() => {
            document.getElementById('search-btn-text').style.display = 'inline-block';
            document.getElementById('search-loading').style.display = 'none';
            showResult("<span style='color:red;'>❌ Lỗi kết nối</span>");
        });
}

function showResult(html) {
    const box = document.getElementById("lookup_result");
    box.innerHTML = html;
    box.style.display = "block";
}

function updateStatus(ok) {
    const ind = document.getElementById("statusIndicator");
    const txt = document.getElementById("statusText");
    if (ok) {
        ind.classList.remove("offline");
        txt.textContent = "Online";
    } else {
        ind.classList.add("offline");
        txt.textContent = "Offline";
    }
}

loadInfo();
setInterval(loadInfo, 8000);

document.getElementById("search_kw").addEventListener("keypress", e => {
    if (e.key === "Enter") lookupUser();
});
</script>

</body>
</html>
