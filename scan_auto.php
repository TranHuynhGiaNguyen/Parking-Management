<?php /* scan_auto.php */ ?>
<!doctype html>
<html lang="vi">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Tự quét biển số & phân loại xe</title>
  <style>
    body{font-family:system-ui,Arial;margin:16px}
    .flex{display:flex;gap:16px;flex-wrap:wrap}
    video,img{max-width:100%;border:1px solid #ccc;border-radius:8px}
    .card{border:1px solid #e5e7eb;border-radius:10px;padding:12px}
    .row{margin-top:10px}
    .badge{display:inline-block;padding:4px 8px;border-radius:8px;background:#eef}
    .ok{background:#e6ffed} .warn{background:#fff8e1}
    .btn{padding:9px 14px;border:0;border-radius:8px;background:#111;color:#fff;cursor:pointer}
    input[type=text]{padding:10px;border:1px solid #ccc;border-radius:8px;width:260px}
  </style>

  <!-- Thư viện TensorFlow.js -->
  <script src="tfjs/tf.min.js"></script>
  <script>
  // --- Nạp model AI ---
  let vehicleModel=null, vehicleLabels=["car","motorbike","other"];
  const BASE = location.pathname.replace(/[^/]+$/, ''); // "/GuiXe/"
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
  </script>
</head>

<body>
  <h2>Tự quét biển số và phân loại xe</h2>
  <div class="flex">
    <div class="card">
      <video id="cam" autoplay playsinline width="640" height="360"></video>
      <div class="row">
        <label><input type="checkbox" id="auto" checked/> Tự động quét</label>
        <label style="margin-left:12px">Chu kỳ (ms):
          <input type="number" id="period" value="1000" min="300" step="100" style="width:90px"/></label>
        <label style="margin-left:12px">Ngưỡng tin cậy:
          <input type="number" id="thresh" value="0.8" min="0" max="1" step="0.01" style="width:70px"/></label>
        <button id="toggle" class="btn" style="margin-left:12px">Tạm dừng</button>
      </div>
      <div class="row">Trạng thái: <span id="status" class="badge">Khởi động…</span></div>
    </div>

    <div class="card">
      <h3>Kết quả</h3>
      <div class="row"><img id="preview" alt="khung đã chụp" style="max-width:320px"/></div>
      <div class="row">
        <div>Biển số: <span id="plate" class="badge">—</span></div>
        <div>Độ tin cậy: <span id="conf" class="badge">—</span></div>
        <div>Loại xe: <span id="veh" class="badge">—</span></div>
      </div>
      <div class="row">
        <label>Chỉnh tay (nếu cần):</label><br/>
        <input type="text" id="plateManual" placeholder="VD: 59A-123.45"/>
      </div>
      <div class="row">
        <button id="accept" class="btn">Chấp nhận</button>
      </div>
    </div>
  </div>

  <div class="row">
    <label>Quét thẻ RFID:</label><br/>
    <input type="text" id="uidInput" placeholder="Đưa thẻ vào đầu đọc." autofocus>
  </div>

<script>
const video=document.getElementById('cam');
const autoChk=document.getElementById('auto');
const periodInp=document.getElementById('period');
const threshInp=document.getElementById('thresh');
const toggleBtn=document.getElementById('toggle');
const statusEl=document.getElementById('status');
const preview=document.getElementById('preview');
const plateEl=document.getElementById('plate');
const confEl=document.getElementById('conf');
const vehEl=document.getElementById('veh');
const plateManual=document.getElementById('plateManual');
const acceptBtn=document.getElementById('accept');
const uidInput=document.getElementById('uidInput');

let timer=null, busy=false, running=true, vehicleType='';

// --- Khởi tạo camera ---
async function initCam(){
  try{
    const stream=await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'},audio:false});
    video.srcObject=stream;
    await new Promise(res=>{ video.onloadedmetadata=()=>res(); });
    await video.play();
    statusEl.textContent='Camera sẵn sàng';
  }catch(e){
    statusEl.textContent='Không truy cập camera: '+e.message;
  }
}

// --- Chụp ảnh từ video ---
function captureCanvas(){
  const w=video.videoWidth||640, h=video.videoHeight||360;
  const c=document.createElement('canvas'); c.width=w; c.height=h;
  c.getContext('2d').drawImage(video,0,0,w,h);
  preview.src=c.toDataURL('image/jpeg',0.85);
  return c;
}
async function captureBlob(){
  const c=captureCanvas();
  const b=await fetch(preview.src).then(r=>r.blob());
  return {canvas:c,blob:b};
}

// --- Hàm chính: quét và nhận diện ---
async function detectOnce(){
  if(busy||!running) return;
  busy=true; statusEl.textContent='Đang quét…';
  try{
    const {canvas,blob}=await captureBlob();
    const fd=new FormData(); fd.append('image',blob,'frame.jpg');
    const res=await fetch('anpr_proxy.php',{method:'POST',body:fd});
    if(!res.ok) throw new Error('Proxy lỗi '+res.status);
    const json=await res.json();

    const plate=(json.plate||'').toString().toUpperCase().trim();
    const conf=typeof json.confidence==='number'?json.confidence:NaN;
    plateEl.textContent=plate||'—';
    confEl.textContent=isFinite(conf)?conf.toFixed(2):'—';

    // Phân loại loại xe bằng model AI
    const veh=await classifyVehicleFromCanvas(canvas);
    vehicleType=(veh && veh.score>=0.7)?veh.label:'';
    vehEl.textContent=vehicleType||'—';

    const thresh=parseFloat(threshInp.value||'0.8');
    if(plate && isFinite(conf) && conf>=thresh){
      plateManual.value=plate;
      plateEl.className='badge ok';
      statusEl.textContent='Đã nhận diện đạt ngưỡng';
    }else{
      plateEl.className='badge warn';
      statusEl.textContent='Cần xác nhận hoặc chỉnh tay';
    }
  }catch(e){
    statusEl.textContent='Lỗi: '+e.message;
  }finally{ busy=false; }
}

// --- Tự động quét ---
function startAuto(){
  stopAuto();
  const p=Math.max(300,parseInt(periodInp.value||'1000',10));
  timer=setInterval(detectOnce,p);
  statusEl.textContent='Tự động quét mỗi '+p+' ms';
}
function stopAuto(){ if(timer){clearInterval(timer);timer=null;} }

autoChk.addEventListener('change',()=>{autoChk.checked?startAuto():stopAuto();});
toggleBtn.addEventListener('click',()=>{running=!running;toggleBtn.textContent=running?'Tạm dừng':'Tiếp tục';});
acceptBtn.addEventListener('click',()=>{alert('Chấp nhận biển: '+(plateManual.value||'(rỗng)'));});

// --- Gửi dữ liệu RFID + loại xe ---
let lock=false,lastUID='',lastTs=0;
uidInput.addEventListener('keydown',async(e)=>{
  if(e.key!=='Enter'||lock) return;
  const raw=uidInput.value.toUpperCase().replace(/[^0-9A-F]/g,'');
  uidInput.value=''; if(raw.length<4) return;
  const now=Date.now(); if(raw===lastUID&&now-lastTs<1000) return;
  lastUID=raw; lastTs=now; lock=true;

  const fd=new FormData();
  fd.append('uid',raw);
  fd.append('plate',(plateManual.value||plateEl.textContent||'').trim());
  fd.append('vehicle_type',vehicleType||'');

  try{ await fetch('save_record.php',{method:'POST',body:fd}); }
  finally{ setTimeout(()=>lock=false,1000); }
});

window.addEventListener('beforeunload',()=>{if(video.srcObject){video.srcObject.getTracks().forEach(t=>t.stop());}});
initCam().then(()=>{if(autoChk.checked)startAuto();});
</script>
</body>
</html>
