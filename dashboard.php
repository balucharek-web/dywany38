<?php
require 'db.php';

$store_id = (int)$_SESSION['store_id'];
$store_name = $_SESSION['store_name'] ?? '';
$store_numer = $_SESSION['store_numer'] ?? '';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="theme-color" content="#1f6feb">
<link rel="manifest" href="manifest.json">
<link rel="icon" href="icon-192.png" type="image/png">
<link rel="apple-touch-icon" href="icon-512.png">





<style>
:root {
  --accent: #1f6feb;
  --brand: #5cb131;
  --bg: #f4f6fb;
  --text: #111;
  --white: #fff;
}

/* ====== GLOBAL ====== */
*,
*::before,
*::after {
  box-sizing: border-box;
}
html, body {
  margin: 0;
  padding: 0;
  width: 100%;
  min-height: 100%;
}
body {
  font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial;
  background: var(--bg);
  color: var(--text);
  text-align: center;
  display: flex;
  flex-direction: column;
}

/* ====== TOP BAR ====== */
.top {
  position: sticky;
  top: 0;
  left: 0;
  width: 100%;
  background: var(--white);
  box-shadow: 0 2px 6px rgba(0,0,0,0.08);
  padding: 14px 0;
  z-index: 1000;
}
.top-inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 95%;
  max-width: 1100px;
  margin: 0 auto;
  flex-wrap: wrap;
  gap: 10px;
}
.logo {
  display: flex;
  align-items: center;
  gap: 10px;
}
.logo img {
  height: 50px;
  width: auto;
  object-fit: contain;
}
.store-info {
  text-align: left;
}
.store-info strong {
  font-size: 1.25rem;
  color: var(--accent);
  display: block;
}
.store-info span {
  font-size: 0.95rem;
  color: #333;
}
.actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}
.actions a {
  text-align: center;
  background: var(--accent);
  color: white;
  padding: 10px 14px;
  border-radius: 8px;
  text-decoration: none;
  font-weight: 600;
  transition: background 0.2s;
  box-sizing: border-box;
}
.actions a:hover {
  background: #1556b8;
}

/* ====== CONTAINER ====== */
.container {
  flex: 1;
  width: 100%;
  max-width: 960px;
  margin: 0 auto;
  padding: 20px;
  box-sizing: border-box;
}

/* ====== SEARCH ====== */
.search {
  display: flex;
  gap: 8px;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  width: 100%;
}
.search input {
  flex: 1;
  min-width: 200px;
  padding: 12px;
  border-radius: 10px;
  border: 1px solid #ddd;
  font-size: 16px;
  box-sizing: border-box;
}
.buttons {
  display: flex;
  gap: 8px;
  justify-content: center;
  flex-wrap: wrap;
  margin-top: 12px;
}
.btn {
  padding: 12px 16px;
  border-radius: 10px;
  border: 0;
  background: var(--accent);
  color: white;
  font-weight: 600;
  cursor: pointer;
  transition: background 0.2s;
}
.btn:hover {
  background: #1556b8;
}
.btn.secondary {
  background: #6b7280;
}
.btn.secondary:hover {
  background: #555b65;
}

/* ====== RESULT ====== */
.result {
  margin-top: 16px;
  background: white;
  padding: 16px;
  border-radius: 12px;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
}
.row {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  justify-content: center;
}
.col {
  flex: 1 1 220px;
  border-radius: 8px;
  padding: 10px;
  background: #fbfdff;
  border: 1px solid #eef3ff;
}
.label {
  font-size: 13px;
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.val {
  font-weight: 700;
  font-size: 18px;
  margin-top: 4px;
}

/* ====== FULLSCREEN SCANNER ====== */
#fullscreen-scanner {
  display: none;
  position: fixed;
  inset: 0;
  z-index: 9999;
  width: 100vw;
  height: 100vh;
  background-color: black;
}
#scanner {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.overlay {
  position: absolute;
  inset: 0;
  pointer-events: none;
  display: flex;
  align-items: center;
  justify-content: center;
}
.scanner-frame {
  position: relative;
  width: 80%;
  max-width: 300px;
  aspect-ratio: 1;
  border: 3px solid #00ff00;
  border-radius: 15px;
  z-index: 10;
  box-shadow:
    0 0 0 200vmax rgba(0, 0, 0, 0.5),
    inset 0 0 10px #00ff00;
  animation: pulse 2s infinite ease-in-out;
}
@keyframes pulse {
  0%,100% { transform: scale(1); }
  50% { transform: scale(1.05); }
}
#close-scanner {
  position: absolute;
  top: 20px;
  right: 20px;
  z-index: 10000;
  background-color: rgba(0,0,0,0.6);
  color: white;
  font-size: 24px;
  border: none;
  border-radius: 50%;
  width: 40px;
  height: 40px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  pointer-events: auto;
}
#close-scanner:hover {
  background-color: rgba(255,0,0,0.6);
}

/* ====== RESPONSYWNOŚĆ ====== */
@media (max-width: 750px) {
  .top-inner {
    flex-direction: column;
    align-items: center;
    text-align: center;
  }
  .logo {
    flex-direction: column;
  }
  .store-info strong {
    font-size: 1.1rem;
  }
  .actions {
    justify-content: center;
    width: 100%;
  }
  .actions a {
    width: 100%;
    max-width: 320px;
  }
  .search {
    flex-direction: column;
    align-items: stretch;
  }
  .search input {
    width: 100%;
  }
  .buttons {
    flex-direction: column;
    align-items: stretch;
  }
  .btn {
    width: 100%;
    font-size: 16px;
  }
}
</style>
</head>
<body>
<div class="top">
  <div class="top-inner">
    <div class="logo">
      <img src="logolm.png" alt="Leroy Merlin Logo">
      <div class="store-info">
        <strong>Sklep <?=htmlspecialchars($store_numer)?></strong>
        <span><?=htmlspecialchars($store_name)?></span>
      </div>
    </div>
    <div class="actions">
      <a href="edit_magazyn.php">🛠️ Edytuj magazyn</a>
      <a href="logout.php">🚪 Wyloguj</a>
    </div>
  </div>
</div>

<div class="container">
  <div class="search">
    <input id="q" placeholder="Wyszukaj po: EAN, ref, lub miejsce (np. 12A)" autofocus>
  </div>

  <div id="result" class="result" style="display:none"></div>

  <div class="buttons">
    <button id="searchBtn" class="btn">🔍 Szukaj</button>
    <button id="scanBtn" class="btn secondary">📷 Skanuj</button>
  </div>
</div>

<!-- FULLSCREEN SCANNER -->
<div id="fullscreen-scanner">
  <div id="scanner"></div>
  <div class="overlay"><div class="scanner-frame"></div></div>
  <button id="close-scanner" onclick="stopScanner()">✕</button>
  <audio id="beep-sound" src="/dywany2/sounds/beep.mp3" preload="auto"></audio>
</div>

<script src="https://unpkg.com/quagga@0.12.1/dist/quagga.min.js"></script>
<script>
const storeId = <?= json_encode($store_id) ?>;

function renderProduct(data){
  const r=document.getElementById('result');
  if(!data){r.style.display='block';r.innerHTML='<div>Brak wyników.</div>';return;}
  r.style.display='block';
  r.innerHTML=`
  <div class="row">
    <div class="col"><div class="label">Nazwa</div><div class="val">${escapeHtml(data.name)}</div></div>
    <div class="col"><div class="label">Ref.</div><div class="val">${escapeHtml(data.reference)}</div></div>
    <div class="col"><div class="label">EAN</div><div class="val">${escapeHtml(data.ean)}</div></div>
    <div class="col"><div class="label">Miejsce</div><div class="val">${escapeHtml(data.location)}${escapeHtml(data.sub)}</div></div>
  </div>`;
}
function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);}

document.getElementById('searchBtn').addEventListener('click',doSearch);
document.getElementById('q').addEventListener('keydown',e=>{if(e.key==='Enter')doSearch();});
async function doSearch(){
  const q=document.getElementById('q').value.trim();if(!q)return;
  const res=await fetch('api_search.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({q,store_id:storeId})});
  const txt=await res.text();
  let r;try{r=JSON.parse(txt);}catch(e){console.error(txt);return;}
  if(r.success)renderProduct(r.data);else renderProduct(null);
}

const overlay=document.getElementById('fullscreen-scanner');
document.getElementById('scanBtn').addEventListener('click',startScanner);

function startScanner(){
  overlay.style.display='block';
  navigator.mediaDevices.enumerateDevices().then(devices=>{
    const cameras=devices.filter(d=>d.kind==='videoinput');
    if(cameras.length===0){alert('Brak kamery');return;}
    const backCamera=cameras.find(d=>d.label.toLowerCase().includes('back'))||cameras[0];
    Quagga.init({
      inputStream:{
        type:'LiveStream',
        target:document.querySelector('#scanner'),
        constraints:{
          deviceId: backCamera.deviceId ? {exact:backCamera.deviceId}:undefined,
          facingMode:'environment',
          aspectRatio:{min:1,max:2}
        }
      },
      decoder:{readers:['ean_reader','code_128_reader','code_39_reader']},
      locate:true
    },err=>{
      if(err){alert('Błąd: '+err);overlay.style.display='none';return;}
      Quagga.start();
    });

    Quagga.onDetected(function(data){
      const code=data.codeResult.code;
      console.log('Zeskanowano:',code);
      document.getElementById('q').value=code;
      document.getElementById('beep-sound').play();
      stopScanner();
      doSearch();
    });
  }).catch(err=>{
    alert('Błąd kamery: '+err.message);
    overlay.style.display='none';
  });
}

function stopScanner(){
  try{Quagga.stop();}catch(e){}
  overlay.style.display='none';
}



if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('service-worker.js')
  .then(() => console.log('✅ Service Worker registered'))
  .catch(err => console.error('❌ SW error:', err));
}




</script>
</body>
</html>
