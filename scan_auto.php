
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Tự quét biển số & phân loại xe (4 camera)</title>
  <link rel="stylesheet" href="assets/css/scan_auto.css">
  <!-- TensorFlow.js (giữ đường dẫn của bạn nếu có local tfjs ) -->
  <script src="tfjs/tf.min.js"></script>
</head>
<body>
 <header class="topbar">
  <div class="menu-btn" id="menuToggle">
    <span></span><span></span><span></span>
  </div>
  <div class="header-title">
    <h1>🚗 Parking Management — ANPR Scanner (4 camera)</h1>
    <p>Tự quét biển số và phân loại xe</p>
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
    <li><a href="scan_auto.php">🎥 Trực tiếp (4 camera)</a></li>
    <li><a href="#" data-page="history">🧾 Danh sách xe ra/vào</a></li>
    <li><a href="#" data-page="revenue">💰 Quản lý doanh thu</a></li>
    <li><a href="#" data-page="settings">⚙️ Cài đặt hệ thống</a></li>
  </ul>
</nav>

  <main class="main-grid">
    <!-- Bên trái -->
    <section class="side" id="sideLeft">
      <div class="zone-header">
        <strong>Left Zone</strong>
        <div class="controls">
          <label><input type="checkbox" id="auto" checked> Tự động</label>
          <label>Chu kỳ(ms):
            <input id="period" type="number" value="1000" min="300" class="small">
          </label>
          <label>Ngưỡng:
            <input id="thresh" type="number" step="0.01" min="0" max="1" value="0.8" class="small">
          </label>
          <button id="toggle" class="btn small">Tạm dừng</button>
        </div>
      </div>

      <div class="cam-row">
        <video id="cam1" autoplay playsinline></video>
        <video id="cam2" autoplay playsinline></video>
      </div>

      <div class="result-panel" aria-labelledby="leftResult">
        <h3 id="leftResult">Kết quả bên trái</h3>
        <div class="result-row">
          <div><span class="label">Biển số:</span> <span id="plateL" class="badge">—</span></div>
          <div><span class="label">Độ tin cậy:</span> <span id="confL" class="badge">—</span></div>
          <div><span class="label">Loại xe:</span> <span id="vehL" class="badge">—</span></div>
        </div>

        <div class="input-group">
          <label class="label">Chỉnh tay:</label>
          <input id="plateManualL" type="text" placeholder="VD: 59A-123.45">
        </div>

        <div class="input-group">
          <label class="label">RFID (trái):</label>
          <input id="uidInputL" type="text" placeholder="Quét thẻ và nhấn Enter">
        </div>

        <div class="actions">
          <button id="acceptL" class="btn">Chấp nhận</button>
        </div>
      </div>
    </section>

    <!-- Bên phải -->
    <section class="side" id="sideRight">
      <div class="zone-header">
        <strong>Right Zone</strong>
        <div class="note">Nền trắng — không hiển thị preview</div>
      </div>

      <div class="cam-row">
        <video id="cam3" autoplay playsinline></video>
        <video id="cam4" autoplay playsinline></video>
      </div>

      <div class="result-panel" aria-labelledby="rightResult">
        <h3 id="rightResult">Kết quả bên phải</h3>
        <div class="result-row">
          <div><span class="label">Biển số:</span> <span id="plateR" class="badge">—</span></div>
          <div><span class="label">Độ tin cậy:</span> <span id="confR" class="badge">—</span></div>
          <div><span class="label">Loại xe:</span> <span id="vehR" class="badge">—</span></div>
        </div>

        <div class="input-group">
          <label class="label">Chỉnh tay:</label>
          <input id="plateManualR" type="text" placeholder="VD: 60B-789.01">
        </div>

        <div class="input-group">
          <label class="label">RFID (phải):</label>
          <input id="uidInputR" type="text" placeholder="Quét thẻ và nhấn Enter">
        </div>

        <div class="actions">
          <button id="acceptR" class="btn">Chấp nhận</button>
        </div>
      </div>
    </section>
  </main>

  <footer>© 2025 Parking Management System</footer>

  <!-- ====== JS: kết hợp logic gốc của bạn, mở rộng cho 4 camera ====== -->
<script>
/* ----------------- Model AI (giữ nguyên từ bạn) ----------------- */
let vehicleModel = null, vehicleLabels = ["car","motorbike","other"];
const BASE = location.pathname.replace(/[^/]+$/, '');
const MODEL_URL = BASE + 'models/vehicle/model.json';
const META_URL  = BASE + 'models/vehicle/metadata.json';

(async()=>{
  try{
    try{ vehicleModel = await tf.loadGraphModel(MODEL_URL); }
    catch{ vehicleModel = await tf.loadLayersModel(MODEL_URL); }
    try{
      const r = await fetch(META_URL);
      const m = r.ok ? await r.json() : null;
      if(m?.labels?.length) vehicleLabels = m.labels;
    }catch{}
    console.log('Model OK', MODEL_URL, vehicleLabels);
  }catch(e){ console.error('Model FAIL', e); }
})();

async function classifyVehicleFromCanvas(canvasEl){
  if(!vehicleModel) return null;
  const t=tf.tidy(()=>tf.browser.fromPixels(canvasEl)
      .resizeBilinear([224,224]).toFloat().div(255).expandDims());
  const out = vehicleModel.predict(t);
  const logits = Array.isArray(out)? out[0]: out;
  const arr = await logits.data();
  tf.dispose([t,out,logits]);
  let idx=0,max=arr[0]??0; for(let i=1;i<arr.length;i++) if(arr[i]>max){max=arr[i]; idx=i;}
  return {label: vehicleLabels[idx]||'other', score:max};
}

/* ----------------- Các phần tử DOM ----------------- */
const vidEls = [
  document.getElementById('cam1'),
  document.getElementById('cam2'),
  document.getElementById('cam3'),
  document.getElementById('cam4')
];

const plateL = document.getElementById('plateL');
const confL  = document.getElementById('confL');
const vehL   = document.getElementById('vehL');
const plateManualL = document.getElementById('plateManualL');
const uidInputL = document.getElementById('uidInputL');
const acceptL = document.getElementById('acceptL');

const plateR = document.getElementById('plateR');
const confR  = document.getElementById('confR');
const vehR   = document.getElementById('vehR');
const plateManualR = document.getElementById('plateManualR');
const uidInputR = document.getElementById('uidInputR');
const acceptR = document.getElementById('acceptR');

const autoChk=document.getElementById('auto');
const periodInp=document.getElementById('period');
const threshInp=document.getElementById('thresh');
const toggleBtn=document.getElementById('toggle');

/* ----------------- Trạng thái quét nội bộ ----------------- */
let timer=null;
let running=true;
let busy = [false,false,false,false];
let vehicleTypeForCam = ['','','',''];

/* ----------------- Khởi tạo camera ----------------- */
async function initAllCams(){
  try{
    const devices = await navigator.mediaDevices.enumerateDevices();
    const cams = devices.filter(d => d.kind === 'videoinput');
    if(cams.length === 0){
      console.warn('Không tìm thấy camera nào');
      return;
    }
    for(let i=0;i<vidEls.length;i++){
      const device = cams[i] || cams[i % cams.length];
      try{
        const stream = await navigator.mediaDevices.getUserMedia({
          video: { deviceId: device.deviceId },
          audio: false
        });
        vidEls[i].srcObject = stream;
      }catch(e){
        console.warn('Không thể mở camera index', i, e);
      }
    }
  }catch(e){
    console.error('Lỗi enumerate devices', e);
  }
}

/* ----------------- Capture từ video ----------------- */
function captureCanvasFromVideo(videoEl){
  const w = videoEl.videoWidth || 640;
  const h = videoEl.videoHeight || 360;
  const c = document.createElement('canvas');
  c.width = w; c.height = h;
  const ctx = c.getContext('2d');
  ctx.drawImage(videoEl, 0, 0, w, h);
  return c;
}
function canvasToBlob(canvas, quality=0.85){
  return new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
}

/* ----------------- Quét từng camera ----------------- */
async function detectOnceForCam(camIndex){
  if(busy[camIndex] || !running) return;
  const vid = vidEls[camIndex];
  if(!vid || !(vid.readyState >= 2)) return;
  busy[camIndex] = true;

  try{
    const canvas = captureCanvasFromVideo(vid);
    const blob = await canvasToBlob(canvas, 0.85);
    if(!blob){ busy[camIndex]=false; return; }

    const fd = new FormData();
    fd.append('image', blob, 'frame.jpg');

    const res = await fetch('anpr_proxy.php', { method: 'POST', body: fd });
    if(!res.ok) throw new Error('Proxy lỗi '+res.status);
    const json = await res.json();

    const plate = (json.plate || '').toString().toUpperCase().trim();
    const conf = typeof json.confidence === 'number' ? json.confidence : parseFloat(json.confidence) || NaN;

    const veh = await classifyVehicleFromCanvas(canvas);
    const vehicleType = (veh && veh.score>=0.7) ? veh.label : '';

    // Gán kết quả theo khu vực
    if(camIndex <= 1){
      plateL.textContent = plate || '—';
      confL.textContent = isFinite(conf) ? conf.toFixed(2) : '—';
      vehL.textContent = vehicleType || '—';
      if(plate && isFinite(conf) && conf >= parseFloat(threshInp.value || '0.8')){
        plateManualL.value = plate;
        plateL.className = 'badge ok';
      }else{
        plateL.className = 'badge warn';
      }
      vehicleTypeForCam[camIndex] = vehicleType;
    } else {
      plateR.textContent = plate || '—';
      confR.textContent = isFinite(conf) ? conf.toFixed(2) : '—';
      vehR.textContent = vehicleType || '—';
      if(plate && isFinite(conf) && conf >= parseFloat(threshInp.value || '0.8')){
        plateManualR.value = plate;
        plateR.className = 'badge ok';
      }else{
        plateR.className = 'badge warn';
      }
      vehicleTypeForCam[camIndex] = vehicleType;
    }
  }catch(e){
    console.error('Lỗi detect cam', camIndex, e);
  }finally{
    busy[camIndex] = false;
  }
}

/* ----------------- Auto scan ----------------- */
function detectAllOnce(){ for(let i=0;i<vidEls.length;i++) detectOnceForCam(i); }
function startAuto(){ stopAuto(); const p=Math.max(300,parseInt(periodInp.value||'1000',10)); timer=setInterval(detectAllOnce,p); }
function stopAuto(){ if(timer){ clearInterval(timer); timer=null; } }

/* ----------------- Event listeners ----------------- */
autoChk.addEventListener('change', ()=> autoChk.checked ? startAuto() : stopAuto());
toggleBtn.addEventListener('click', ()=> { running = !running; toggleBtn.textContent = running ? 'Tạm dừng' : 'Tiếp tục'; });

acceptL.addEventListener('click', ()=> alert('Chấp nhận biển (trái): ' + (plateManualL.value || '(rỗng)')));
acceptR.addEventListener('click', ()=> alert('Chấp nhận biển (phải): ' + (plateManualR.value || '(rỗng)')));

/* ----------------- RFID GỬI VỚI ZONE + VEHICLE_TYPE ----------------- */
let lockL=false, lastUIDL='', lastTsL=0;
uidInputL.addEventListener('keydown', async (e)=>{
  if(e.key !== 'Enter' || lockL) return;
  const raw = uidInputL.value.toUpperCase().replace(/[^0-9A-F]/g,'');
  uidInputL.value = ''; if(raw.length < 4) return;
  const now = Date.now(); if(raw === lastUIDL && now - lastTsL < 1000) return;
  lastUIDL = raw; lastTsL = now; lockL = true;

  const fd = new FormData();
  fd.append('uid', raw);
  fd.append('plate', (plateManualL.value || plateL.textContent || '').trim());
  fd.append('vehicle_type', vehicleTypeForCam[0] || vehicleTypeForCam[1] || 'car');
  fd.append('zone', 'left'); // 🆕 xác định khu vực

  try{ await fetch('save_record.php', { method:'POST', body: fd }); console.log('RFID trái:', raw); }
  finally{ setTimeout(()=> lockL=false, 1000); }
});

let lockR=false, lastUIDR='', lastTsR=0;
uidInputR.addEventListener('keydown', async (e)=>{
  if(e.key !== 'Enter' || lockR) return;
  const raw = uidInputR.value.toUpperCase().replace(/[^0-9A-F]/g,'');
  uidInputR.value = ''; if(raw.length < 4) return;
  const now = Date.now(); if(raw === lastUIDR && now - lastTsR < 1000) return;
  lastUIDR = raw; lastTsR = now; lockR = true;

  const fd = new FormData();
  fd.append('uid', raw);
  fd.append('plate', (plateManualR.value || plateR.textContent || '').trim());
  fd.append('vehicle_type', vehicleTypeForCam[2] || vehicleTypeForCam[3] || 'car');
  fd.append('zone', 'right');

  try{ await fetch('save_record.php', { method:'POST', body: fd }); console.log('RFID phải:', raw); }
  finally{ setTimeout(()=> lockR=false, 1000); }
});

/* ----------------- Khi đóng trang ----------------- */
window.addEventListener('beforeunload', ()=>{
  vidEls.forEach(v=>{
    try{ if(v && v.srcObject) v.srcObject.getTracks().forEach(t=>t.stop()); }catch(e){}
  });
});

/* ----------------- Menu toggle ----------------- */
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
menuToggle.addEventListener('click', () => sidebar.classList.toggle('active'));

document.querySelectorAll('#sidebar a[data-page]').forEach(a => {
  a.addEventListener('click', (e) => {
    e.preventDefault();
    sidebar.classList.remove('active');
    const page = a.getAttribute('data-page');
    showPage(page);
  });
});

function showPage(page) {
  switch(page) {
    case 'camera': alert('🎥 Trực tiếp (4 camera)'); break;
    case 'history': alert('📋 Danh sách xe ra/vào'); break;
    case 'revenue': alert('💰 Quản lý doanh thu'); break;
    case 'settings': alert('⚙️ Cài đặt hệ thống'); break;
  }
}

/* ----------------- Khởi tạo ----------------- */
initAllCams().then(()=>{ if(autoChk.checked) startAuto(); });
</script>
</body>
</html>