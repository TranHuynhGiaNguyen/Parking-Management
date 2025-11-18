<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thông tin bãi giữ xe</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #1e70c7;
            --primary-hover: #1557a0;
            --secondary-color: #f8f9fa;
            --text-color: #333;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --shadow: 0 4px 12px rgba(0,0,0,0.1);
            --shadow-lg: 0 8px 25px rgba(0,0,0,0.15);
            --border-radius: 12px;
            --header-height: 60px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            background: white;
            box-shadow: var(--shadow);
            z-index: 1000;
            display: flex;
            align-items: center;
            padding: 0 20px;
        }

        .header-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-color);
        }

        .status-badge {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--success-color);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.3);
        }

        .status-indicator.offline {
            background: var(--danger-color);
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.3);
        }

        /* Main Content */
        .main-content {
            margin-top: var(--header-height);
            padding: 30px 20px;
            min-height: calc(100vh - var(--header-height));
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .card-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-hover));
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 8px;
            display: block;
        }

        .stat-label {
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .table th {
            background: var(--primary-color);
            color: white;
            padding: 16px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            padding: 16px 12px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: middle;
        }

        .table tbody tr:hover {
            background: var(--secondary-color);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        /* Search Form */
        .search-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 14px 18px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 16px;
            transition: var(--transition);
            background: var(--secondary-color);
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            background: white;
            box-shadow: 0 0 0 3px rgba(30, 112, 199, 0.1);
        }

        .btn {
            padding: 14px 24px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 112, 199, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* Search Results */
        .search-result {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid var(--border-color);
            background: var(--secondary-color);
            display: none;
        }

        .search-result.success {
            border-color: var(--success-color);
            background: rgba(40, 167, 69, 0.1);
        }

        .search-result.error {
            border-color: var(--danger-color);
            background: rgba(220, 53, 69, 0.1);
        }

        .result-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 15px;
        }

        .result-item:last-child {
            margin-bottom: 0;
        }

        .result-label {
            font-weight: 600;
            color: var(--text-color);
        }

        .result-value {
            color: var(--text-muted);
            font-weight: 500;
        }

        .highlight-fee {
            color: var(--primary-color);
            font-weight: 700;
            font-size: 18px;
        }

        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Animations */
        .fade-in {
            animation: fadeIn 0.5s ease-in-out;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
            }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header {
                padding: 0 15px;
            }

            .header-title {
                font-size: 18px;
            }

            .main-content {
                padding: 20px 15px;
            }

            .card {
                padding: 20px;
                margin-bottom: 20px;
            }

            .card-title {
                font-size: 20px;
            }

            .search-form {
                flex-direction: column;
            }

            .search-input {
                min-width: 100%;
            }

            .btn {
                width: 100%;
                padding: 16px 24px;
                justify-content: center;
            }

            .table-container {
                font-size: 12px;
            }

            .table th,
            .table td {
                padding: 12px 8px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-number {
                font-size: 28px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .stat-number {
                font-size: 28px;
            }

            .result-item {
                flex-direction: column;
                gap: 4px;
            }
        }

        @media (max-width: 480px) {
            .main-content {
                padding: 15px 10px;
            }

            .card {
                padding: 15px;
            }

            .card-title {
                font-size: 18px;
            }

            .stat-card {
                padding: 20px;
            }
            .stat-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <h1 class="header-title">🚗 Thông tin bãi giữ xe</h1>
        <div class="status-badge">
            <span class="status-indicator" id="statusIndicator"></span>
            <span id="statusText">Online</span>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="container">
            <!-- Fee Schedule -->
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
                            <tbody>
                                <!-- Data will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Vehicle Lookup -->
            <section class="fade-in">
                <div class="card">
                    <div class="card-title">Tra cứu xe của bạn</div>
                    <div class="search-form">
                        <input id="search_kw" class="search-input" placeholder="Nhập UID hoặc Biển số xe..." type="text">
                        <button class="btn" onclick="lookupUser()">
                            <span id="search-btn-text">Tra cứu</span>
                            <span id="search-loading" class="loading" style="display: none;"></span>
                        </button>
                    </div>
                    <div id="lookup_result" class="search-result"></div>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Load parking info
        function loadInfo() {
            fetch("/S/api_public_info.php")
                .then(r => r.json())
                .then(d => {
                    if (!d.ok) return;

                    // Update fee table
                    const tbody = document.querySelector("#fee_table tbody");
                    let html = '';
                    d.fee_ranges.forEach(f => {
                        html += `
                            <tr>
                                <td><strong>${f.start} - ${f.end}</strong></td>
                                <td><span style="color: var(--primary-color); font-weight: 600;">${formatPrice(f.car)}</span></td>
                                <td><span style="color: var(--primary-color); font-weight: 600;">${formatPrice(f.mc)}</span></td>
                            </tr>
                        `;
                    });
                    tbody.innerHTML = html;

                    // Update connection status
                    updateStatus(true);
                })
                .catch(err => {
                    console.error('Error loading info:', err);
                    updateStatus(false);
                });
        }

        function updateStatus(isOnline) {
            const indicator = document.getElementById('statusIndicator');
            const text = document.getElementById('statusText');
            
            if (isOnline) {
                indicator.classList.remove('offline');
                text.textContent = 'Online';
            } else {
                indicator.classList.add('offline');
                text.textContent = 'Offline';
            }
        }

        // Vehicle lookup
        function lookupUser() {
            const kw = document.getElementById("search_kw").value.trim();
            if (!kw) {
                showResult('<span style="color: var(--warning-color);">⚠️ Vui lòng nhập UID hoặc biển số xe</span>', 'error');
                return;
            }

            // Show loading state
            document.getElementById('search-btn-text').style.display = 'none';
            document.getElementById('search-loading').style.display = 'inline-block';

            fetch("/S/api_user_lookup.php?q=" + encodeURIComponent(kw))
                .then(r => r.json())
                .then(d => {
                    // Hide loading state
                    document.getElementById('search-btn-text').style.display = 'inline-block';
                    document.getElementById('search-loading').style.display = 'none';

                    if (!d.ok) {
                        showResult(`<span style='color: var(--danger-color);'>❌ ${d.msg}</span>`, 'error');
                        return;
                    }

                    const x = d.data;
                    const resultHtml = `
                        <div class="result-item">
                            <span class="result-label">🆔 UID:</span>
                            <span class="result-value">${x.uid}</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">🚗 Biển số:</span>
                            <span class="result-value"><strong>${x.plate}</strong></span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">🏍️ Loại xe:</span>
                            <span class="result-value">${x.vehicle_type}</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">📍 Vị trí đậu:</span>
                            <span class="result-value">${x.zone}</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">⏰ Vào lúc:</span>
                            <span class="result-value">${x.in_time}</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">🚪 Ra lúc:</span>
                            <span class="result-value">${x.out_time || 'Chưa ra'}</span>
                        </div>
                        <div class="result-item">
                            <span class="result-label">💰 Phí hiện tại:</span>
                            <span class="result-value highlight-fee">${formatPrice(x.fee)}</span>
                        </div>
                    `;
                    
                    showResult(resultHtml, 'success');
                })
                .catch(err => {
                    // Hide loading state
                    document.getElementById('search-btn-text').style.display = 'inline-block';
                    document.getElementById('search-loading').style.display = 'none';
                    
                    showResult('<span style="color: var(--danger-color);">❌ Lỗi kết nối. Vui lòng thử lại.</span>', 'error');
                });
        }

        function showResult(html, type) {
            const box = document.getElementById("lookup_result");
            box.innerHTML = html;
            box.className = `search-result ${type}`;
            box.style.display = 'block';
            box.classList.add('fade-in');
        }

        function formatPrice(value) {
            // Lấy sạch số: bỏ tất cả ký tự không phải số
            const num = parseInt(String(value).replace(/\D/g, ''), 10);

            if (isNaN(num)) return '0 đ';

            return num.toLocaleString('vi-VN') + ' đ';
        }

        function formatMoney(value) {
            const num = parseInt(String(value).replace(/\D/g, ''), 10);

            if (isNaN(num)) return '0';

            return num.toLocaleString('vi-VN');
        }


        // Enter key support for search
        document.getElementById('search_kw').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                lookupUser();
            }
        });

        // Initialize
        loadInfo();
        setInterval(loadInfo, 10000); // Refresh every 10 seconds

        // Add loading animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.fade-in').forEach((el, index) => {
                el.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>
</html>