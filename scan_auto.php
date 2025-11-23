  <?php
  session_start();
  include 'db_connect.php';

  // --- Kiểm tra đăng nhập ---
  if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
  }

  // --- Chỉ cho phép bảo vệ ---
  if ($_SESSION['user']['role'] !== 'baove') {
    header('Location: index.php');
    exit;
  }

  $current_user = $_SESSION['user'];
  ?>
  <!doctype html>
  <html lang="vi">
  <head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Trạm quét tự động (2 camera)</title>
    <link rel="stylesheet" href="assets/css/scan_auto.css">
    <script src="tfjs/tf.min.js"></script>
  </head>
  <body>

  <header class="topbar">
    <div class="menu-btn" id="menuBtn">
      <span></span><span></span><span></span>
    </div>
    <div class="header-title">
        <h1>🚗 Trạm quét tự động</h1>
        <p>2 camera song song • RFID • Nhận diện biển số</p>
    </div>
    <div class="user-controls">
      <form action="logout.php" method="post" style="display:inline;">
        <button type="submit" class="logout-btn">Đăng xuất</button>
      </form>
    </div>
  </header>

  <nav id="sidebar">
    <ul>
      <li><a href="scan_auto.php" class="active">🎥 Trực tiếp</a></li>
      <li><a href="vehicle_history.php">🧾 Danh sách xe ra/vào</a></li>
    </ul>
  </nav>

  <div class="content">

    <div class="slot-hud">
      <div id="slotline"><sp  an class="small">Đang tải trạng thái...</span></div>
      <div class="small" id="cfgline" style="margin-left:auto">Đang tải cấu hình...</div>
    </div>

    <div class="wrap">

      <div class="scan-zone-container">

        <!-- PANEL TRUNG TÂM QUÉT -->
        <section class="panel scan-panel">
          <h2>Trung tâm quét</h2>
          <div class="panel-body">

            <div class="cams">
              <video id="cam1" autoplay playsinline></video>
              <video id="cam2" autoplay playsinline></video>
            </div>

            <div class="result-row" style="margin-top:12px">
              <div><span class="badge">Biển số:</span> <span id="plateOut" class="badge">—</span></div>
              <div><span class="badge">Độ tin cậy:</span> <span id="confOut" class="badge">—</span></div>
              <div><span class="badge">Loại xe:</span> <span id="vehOut" class="badge">—</span></div>
            </div>

            <div class="row2" style="margin-top:8px">
              <label class="small">Chu kỳ (ms)
                <input id="period" type="number" value="1000" min="300">
              </label>
              <label class="small">Ngưỡng tin cậy
                <input id="thresh" type="number" step="0.01" min="0" max="1" value="0.8">
              </label>
              <div style="display:flex;gap:8px;align-items:flex-end">
                <label class="small" style="display:flex;align-items:center;gap:8px">
                  <input type="checkbox" id="auto" checked> Tự động
                </label>
                <button id="toggle" class="btn small" type="button">Tạm dừng</button>
              </div>
            </div>

            <div class="row" style="margin-top:12px">
              <label class="small">RFID (Enter)
                <input id="uidInput" type="text" placeholder="UID RFID">
              </label>
              <label class="small">Biển số (chỉnh tay)
                <input id="plateManual" type="text" placeholder="VD: 59A-123.45">
              </label>
              <label class="small">Sửa loại xe
                <select id="overrideType">
                  <option value="">Loại tự động</option>
                  <option value="Car">Car</option>
                  <option value="Motorcycle">Motorcycle</option>
                </select>
              </label>
            </div>

            <div style="display:flex;gap:8px;margin-top:10px">
              <button id="accept" class="btn" type="button">Chấp nhận</button>
              <div id="msg" class="small" style="align-self:center"></div>
            </div>

          </div>
        </section>

        <!-- PANEL KHU VỰC ĐỂ XE -->
        <section class="panel zone-panel">
          <h2>Khu vực để xe</h2>

          <!-- BOX HIỂN THỊ TIỀN -->
          <div id="feeBoxUI"
              style="background:#fff;padding:12px;border-radius:10px;margin-bottom:12px;
                      display:none;box-shadow:0 2px 6px rgba(0,0,0,0.05);font-size:16px;">
          </div>

          <div class="panel-body">

            <h3 class="zone-title">Xe máy</h3>
            <div id="zoneListMC" class="zone-list">Đang tải...</div>

            <h3 class="zone-title" style="margin-top:14px;">Ô tô</h3>
            <div id="zoneListCAR" class="zone-list">Đang tải...</div>

          </div>
        </section>

      </div>

      <footer>© 2025 Parking Management System</footer>
    </div>
  </div>
  <script>
  /* ====== Model AI ====== */
  let vehicleModel=null, vehicleLabels=["car","motorbike","other"];
  const BASE=location.pathname.replace(/[^/]+$/,'');
  const MODEL_URL=BASE+'models/vehicle/model.json';
  const META_URL =BASE+'models/vehicle/metadata.json';

  (async()=>{
    try{
      try{ vehicleModel = await tf.loadGraphModel(MODEL_URL); }
      catch{ vehicleModel = await tf.loadLayersModel(MODEL_URL); }

      try{
        const r = await fetch(META_URL);
        const m = r.ok ? await r.json() : null;
        if(m?.labels?.length) vehicleLabels = m.labels;
      }catch{}

      console.log('Model ready');
    }catch(e){ console.error('Model load fail', e); }
  })();

  async function classifyVehicleFromCanvas(canvasEl){
    if(!vehicleModel) return null;
    const t=tf.tidy(()=>tf.browser.fromPixels(canvasEl)
      .resizeBilinear([224,224]).toFloat().div(255).expandDims());
    const out = vehicleModel.predict(t);
    const logits = Array.isArray(out)? out[0]: out;
    const arr = await logits.data(); tf.dispose([t,out,logits]);
    let idx=0,max=arr[0]??0;
    for(let i=1;i<arr.length;i++) if(arr[i]>max){max=arr[i]; idx=i;}
    return {label: vehicleLabels[idx]||'other', score:max};
  }

  /* ====== DOM refs ====== */
  const cam1=document.getElementById('cam1');
  const cam2=document.getElementById('cam2');
  const plateOut=document.getElementById('plateOut');
  const confOut =document.getElementById('confOut');
  const vehOut  =document.getElementById('vehOut');

  const periodInp=document.getElementById('period');
  const threshInp=document.getElementById('thresh');
  const autoChk  =document.getElementById('auto');
  const toggleBtn=document.getElementById('toggle');

  const uidInput =document.getElementById('uidInput');
  const plateManual=document.getElementById('plateManual');
  const overrideType=document.getElementById('overrideType');
  const acceptBtn=document.getElementById('accept');
  const msgEl    =document.getElementById('msg');

  const slotline =document.getElementById('slotline');
  const cfgline  =document.getElementById('cfgline');
  const feeBoxUI =document.getElementById('feeBoxUI');

  /* ====== Camera ====== */
  async function initCams() {
      try {
          
          await navigator.mediaDevices.getUserMedia({ video: true });

          const devices = await navigator.mediaDevices.enumerateDevices();
          const cams = devices.filter(d => d.kind === "videoinput");

          if (cams.length === 0) {
              console.warn("Không tìm thấy camera");
              return;
          }

          async function openStream(videoEl, dev) {
              try {
                  const stream = await navigator.mediaDevices.getUserMedia({
                      video: {
                          deviceId: { exact: dev.deviceId } 
                      },
                      audio: false
                  });
                  videoEl.srcObject = stream;
              } catch (e) {
                  console.error("Không mở được camera:", dev.label, e);
              }
          }

          await openStream(cam1, cams[0]);

          if (cams.length > 1) {
              await openStream(cam2, cams[1]);
          } else {
              console.warn("Chỉ có 1 camera – Edge sẽ dùng 1 cam duy nhất");
          }

      } catch (e) {
          console.error("initCams error:", e);
      }
  }


  function grabCanvas(videoEl){
    const w=videoEl.videoWidth||640;
    const h=videoEl.videoHeight||360;
    const c=document.createElement('canvas');
    c.width=w; c.height=h;
    c.getContext('2d').drawImage(videoEl,0,0,w,h);
    return c;
  }

  function canvasToBlob(c,q=0.8){
    return new Promise(res=>c.toBlob(res,'image/jpeg',q));
  }

  /* ====== Nhận diện kết hợp 2 cam ====== */
  let running=true, timer=null, busy=false, lastVehType='';
  async function detectOnce(){
    if(busy||!running) return;
    busy=true;
    try{
      const cand = [];
      for (const vid of [cam1, cam2]) {
        if(!vid || vid.readyState<2) continue;
        const canvas = grabCanvas(vid);
        const blob   = await canvasToBlob(canvas, 0.8);
        const fd = new FormData();
        fd.append('image', blob, 'frame.jpg');

        try{
          const res = await fetch('anpr_proxy.php', {method:'POST', body:fd});
          if(!res.ok) throw new Error('Proxy '+res.status);

          const j = await res.json();
          const plate=(j.plate||'').toString().toUpperCase().trim();
          const conf = typeof j.confidence==='number'
              ? j.confidence
              : parseFloat(j.confidence)||NaN;

          const veh  = await classifyVehicleFromCanvas(canvas);
          const vtype = veh && veh.score>=0.7 ? veh.label : '';

          cand.push({plate, conf, vtype});
        }catch(e){
          console.warn('ANPR lỗi cho 1 cam', e);
        }
      }

      if (cand.length) {
        cand.sort((a,b)=> (b.conf||0) - (a.conf||0));
        const best = cand[0];

        plateOut.textContent = best.plate || '—';
        confOut.textContent  = isFinite(best.conf) ? best.conf.toFixed(2) : '—';
        vehOut.textContent   = best.vtype || '—';

        plateOut.className = 'badge ' +
          ((best.plate && isFinite(best.conf) &&
            best.conf >= parseFloat(threshInp.value||'0.8'))
            ? 'ok':'warn');

        lastVehType = best.vtype || lastVehType || '';

        if(best.plate && isFinite(best.conf) &&
          best.conf >= parseFloat(threshInp.value||'0.8')){
          plateManual.value = best.plate;
        }
      }

    }catch(e){ console.error('detectOnce', e); }
    finally{ busy=false; }
  }

  function startAuto(){
    stopAuto();
    const p=Math.max(300,parseInt(periodInp.value||'1000',10));
    timer=setInterval(detectOnce, p);
  }

  function stopAuto(){
    if(timer){ clearInterval(timer); timer=null; }
  }

  autoChk.addEventListener('change', ()=> autoChk.checked?startAuto():stopAuto());
  toggleBtn.addEventListener('click', ()=>{
    running=!running;
    toggleBtn.textContent = running ? 'Tạm dừng' : 'Tiếp tục';
  });
  async function captureFace(sessionId, uid, plate, action) {
      try {
          if (!cam2 || !cam2.srcObject) {
              console.warn("Không có cam2 để chụp mặt");
              return;
          }

          const canvas = grabCanvas(cam2);
          const blob   = await canvasToBlob(canvas, 0.9);

          const fd = new FormData();
          fd.append("face", blob, "face.jpg");
          fd.append("session_id", sessionId || "");
          fd.append("uid", uid || "");
          fd.append("plate", plate || "");
          fd.append("action", action || "");

          await fetch("save_face.php", {
              method: "POST",
              body: fd
          });
      } catch (e) {
          console.error("Lỗi captureFace", e);
      }
  }

  /* ====== Gửi RFID -> save_record.php ====== */
  let lock=false, lastUID='', lastTs=0;

  async function submitRFID(uidRaw){
      const raw = (uidRaw||'').toUpperCase().replace(/[^0-9A-F]/g,'');
      if(raw.length<4) return;

      const now=Date.now();
      if(raw===lastUID && now-lastTs<1000) return;
      lastUID=raw; lastTs=now;
      if(lock) return; lock=true;

      const autoTypeRaw = (lastVehType || '').toLowerCase();
      let vtype = document.getElementById('overrideType').value || '';

      if (!vtype) {
          if (autoTypeRaw.includes('car')) vtype = 'CAR';
          else if (autoTypeRaw.includes('motor') || autoTypeRaw.includes('bike')) vtype = 'MC';
          else vtype = 'MC';
      }

      const fd=new FormData();
      fd.append('vehicle_type', vtype);
      fd.append('uid', raw);
      fd.append('plate', (plateManual.value || plateOut.textContent || '').trim());
      fd.append('zone', 'center');

      try{
          const r = await fetch('save_record.php', {method:'POST', body:fd});
          const j = await r.json().catch(()=>({}));

          if (j.ok === false && j.action === "checkout_denied") {
              msgEl.textContent = "❌ " + (j.error || "Xe ra không đúng!");
              msgEl.style.color = "red";
              return;
          }

          if (j.ok === false) {
              msgEl.textContent = "❌ " + (j.error || "Lỗi không xác định");
              msgEl.style.color = "red";
              return;
          }
          if (j.action === "checkout" && j.fee !== null) {
              feeBoxUI.style.display = "block";
              feeBoxUI.innerHTML = `
                  <div style="font-size:18px;font-weight:bold;margin-bottom:6px;">
                      Tiền:
                      <span style="color:#d00000;">
                          ${Number(j.fee).toLocaleString("vi-VN")} VNĐ
                      </span>
                  </div>
                  <div style="color:#16a34a;font-weight:600;">
                      ✔ ${j.message}
                  </div>
              `;
          }

          msgEl.textContent = j.message || "✔ Thành công";
          msgEl.style.color = "green";
          const sessionId = j.session_id || null;

          const plateNow = (plateManual.value || plateOut.textContent || '').trim();

          await captureFace(sessionId, raw, plateNow, j.action || "");


          await refreshSlots();
          await loadZones();

      }catch(e){
          msgEl.textContent = "❌ Lỗi gửi save_record.php";
          msgEl.style.color = "red";
      }finally{
          setTimeout(()=> lock=false, 900);
      }
  }

  uidInput.addEventListener('keydown', (e)=>{
    if(e.key==='Enter'){
      submitRFID(uidInput.value);
      uidInput.value='';
    }
  });

  acceptBtn.addEventListener('click', ()=> submitRFID(uidInput.value));

  /* ====== HUD trạng thái bãi ====== */
  async function refreshSlots(){
    try{
      const r = await fetch("get_slots.php");
      const d = await r.json();

      if(d.ok){
        slotline.innerHTML =
          `🚗 Ô tô: <b>${d.car.used}/${d.car.max}</b> 
          &nbsp;&nbsp; | &nbsp;&nbsp; 
          🛵 Xe máy: <b>${d.motorcycle.used}/${d.motorcycle.max}</b>`;
      } else {
        slotline.innerHTML = "<span class='small'>Không lấy được trạng thái</span>";
      }

    }catch{
      slotline.innerHTML = "<span class='small'>Mất kết nối...</span>";
    }
  }

  /* ====== Cấu hình ====== */
  async function loadConfig(){
    try{
      const r=await fetch('get_config.php',{cache:'no-store'});
      const d=await r.json();

      if(d.ok && d.config){
        const m = d.config.scan_mode || 'both';
        const feeCar = d.config.fee_car_per_min ?? '-';
        const feeMc  = d.config.fee_mc_per_min ?? '-';
        cfgline.textContent =
          `Chế độ: ${m==='in'?'Vào':m==='out'?'Ra':'Vào & Ra'} `;
      }
    }catch{}
  }

  /* ====== Load danh sách zone ====== */
  async function loadZones() {
    try {
      const r = await fetch("api_get_zones.php");
      const j = await r.json();

      const boxMC  = document.getElementById("zoneListMC");
      const boxCAR = document.getElementById("zoneListCAR");

      if (!j.ok) {
        boxMC.innerHTML = "Lỗi tải dữ liệu";
        boxCAR.innerHTML = "Lỗi tải dữ liệu";
        return;
      }

      boxMC.innerHTML = "";
      boxCAR.innerHTML = "";

      j.zones.forEach(z => {
        const item = document.createElement("div");
        item.className = "zone-item";
        item.textContent = `${z.zone}: ${z.used}/${z.max_slots}`;

        if (z.type === "MC") boxMC.appendChild(item);
        else if (z.type === "CAR") boxCAR.appendChild(item);
      });

    } catch (e) { console.error(e); }
  }

  setInterval(loadZones, 2000);
  loadZones();

  /* ====== Boot ====== */
  (async function(){
    await initCams();
    detectOnce();
    if(autoChk.checked) startAuto();
    refreshSlots();
    loadConfig();
    setInterval(refreshSlots, 2000);
    setInterval(loadConfig, 5000);
  })();

  /* ====== Sidebar ====== */
  const sidebar = document.getElementById('sidebar');
  const menuBtn = document.getElementById('menuBtn');
  menuBtn.addEventListener('click', () => {
    sidebar.classList.toggle('active');
  });

  window.addEventListener('beforeunload', ()=>{
    [cam1,cam2].forEach(v=>{
      try{
        if(v && v.srcObject) v.srcObject.getTracks().forEach(t=>t.stop());
      }catch(e){}
    });
  });
  </script>

  </body>
  </html>
