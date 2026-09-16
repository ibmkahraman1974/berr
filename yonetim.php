<?php
/* ============================================================
   bisanat — Yönetim Paneli (yonetim.php)
   Şifre korumalı; içerik ekleme/düzenleme/silme/sıralama/taşıma
   ve sayfa metinleri düzenleme. Veri api.php üzerinden yönetilir.
   ============================================================ */
declare(strict_types=1);
$config = require __DIR__ . '/config.php';

session_name($config['session_name'] ?? 'bisanat_admin');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
]);
session_start();

/* --- çıkış --- */
if (isset($_GET['logout'])) {
    $_SESSION = []; session_destroy();
    header('Location: yonetim.php'); exit;
}

/* --- giriş (sunucu tarafı; panel HTML'i yalnızca yetkiliye servis edilir) --- */
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $pw    = (string)$_POST['password'];
    $hash  = (string)($config['password_hash'] ?? '');
    $plain = (string)($config['password_plain'] ?? '');
    $ok = ($hash !== '') ? password_verify($pw, $hash) : ($pw !== '' && hash_equals($plain, $pw));
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['bisanat_auth'] = true;
        header('Location: yonetim.php'); exit;
    } else {
        $loginError = 'Şifre yanlış.';
        usleep(400000);
    }
}
$authed = !empty($_SESSION['bisanat_auth']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<title>bisanat — Yönetim</title>
<style>
  :root{
    --bg:#14100a; --bg2:#1b150d; --card:#221a10; --card2:#2b2114;
    --line:rgba(190,150,70,.26); --line2:rgba(190,150,70,.16);
    --cream:#f3e7c9; --soft:#d8c9a6; --muted:#a08e6b;
    --gold:#d8b054; --gold2:#c79a3f; --danger:#c9564b; --ok:#4f9a5e;
    --radius:12px; --shadow:0 18px 48px rgba(0,0,0,.42);
  }
  *{box-sizing:border-box}
  html,body{margin:0;background:var(--bg);color:var(--cream);
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
    -webkit-text-size-adjust:100%}
  body{min-height:100vh}
  a{color:var(--gold)}
  h1,h2,h3{font-family:'Georgia','Cormorant Garamond',serif;font-weight:600;letter-spacing:.2px}
  button{font-family:inherit}
  input,textarea,select{font-family:inherit}

  /* ---------- GİRİŞ ---------- */
  .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
  .login-card{background:var(--card);border:1px solid var(--line);border-radius:16px;
    box-shadow:var(--shadow);max-width:400px;width:100%;padding:38px 32px;text-align:center}
  .login-card .brand{font-size:30px;color:var(--gold);letter-spacing:1px}
  .login-card .brand span{display:block;font-size:12px;letter-spacing:3px;color:var(--muted);
    text-transform:uppercase;margin-top:6px;font-family:sans-serif}
  .login-card p{color:var(--soft);font-size:14px;margin:18px 0 22px}
  .login-card input{width:100%;padding:14px 16px;border-radius:10px;border:1px solid var(--line);
    background:var(--bg2);color:var(--cream);font-size:16px;margin-bottom:14px;text-align:center}
  .login-card input:focus{outline:none;border-color:var(--gold)}
  .login-err{color:var(--danger);font-size:13px;margin-bottom:12px;min-height:18px}
  .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;cursor:pointer;
    border-radius:999px;border:1px solid var(--gold2);background:linear-gradient(180deg,var(--gold),var(--gold2));
    color:#241a08;font-weight:700;padding:13px 26px;font-size:15px;transition:filter .15s;min-height:46px}
  .btn:hover{filter:brightness(1.07)}
  .btn.ghost{background:transparent;color:var(--gold);font-weight:600}
  .btn.ghost:hover{background:rgba(200,150,60,.1)}
  .btn.danger{background:transparent;border-color:rgba(201,86,75,.55);color:#e0897f;font-weight:600}
  .btn.danger:hover{background:rgba(201,86,75,.12)}
  .btn.sm{padding:8px 14px;font-size:13px;min-height:38px}
  .btn.block{width:100%}
  .btn[disabled]{opacity:.5;cursor:not-allowed}

<?php if (!$authed): ?>
</style></head><body>
  <div class="login-wrap">
    <form class="login-card" method="post" autocomplete="off">
      <div class="brand">bisanat<span>Yönetim Paneli</span></div>
      <p>İçerik yönetimi için şifrenizi girin.</p>
      <input type="password" name="password" placeholder="Şifre" autofocus required>
      <div class="login-err"><?php echo htmlspecialchars($loginError, ENT_QUOTES); ?></div>
      <button class="btn block" type="submit">Giriş Yap</button>
    </form>
  </div>
</body></html>
<?php exit; endif; ?>

  /* ---------- DÜZEN ---------- */
  .topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:14px;
    padding:12px 20px;background:rgba(20,15,8,.92);backdrop-filter:blur(8px);
    border-bottom:1px solid var(--line)}
  .topbar .brand{font-family:'Georgia',serif;font-size:22px;color:var(--gold);letter-spacing:.5px}
  .topbar .brand small{font-size:11px;letter-spacing:2px;color:var(--muted);text-transform:uppercase;margin-left:8px}
  .topbar .spacer{flex:1}
  .save-state{font-size:12.5px;color:var(--muted);margin-right:4px;min-width:96px;text-align:right}
  .save-state.dirty{color:var(--gold)}
  .save-state.ok{color:var(--ok)}
  .save-state.err{color:var(--danger)}

  .tabs{display:flex;gap:4px;padding:0 14px;background:var(--bg2);border-bottom:1px solid var(--line2);
    overflow-x:auto;position:sticky;top:57px;z-index:40}
  .tab{flex:none;padding:14px 18px;border:none;background:none;color:var(--muted);
    font-size:14.5px;font-weight:600;cursor:pointer;border-bottom:3px solid transparent;white-space:nowrap}
  .tab.active{color:var(--gold);border-bottom-color:var(--gold)}
  .wrap{max-width:1080px;margin:0 auto;padding:22px 16px 120px}
  .panel{display:none} .panel.active{display:block}
  .hint{color:var(--muted);font-size:12.5px;line-height:1.5;margin:2px 0 0}
  .section-note{color:var(--soft);font-size:13.5px;line-height:1.6;
    background:var(--bg2);border:1px solid var(--line2);border-radius:10px;padding:12px 15px;margin-bottom:18px}

  /* form alanları */
  label.fl{display:block;font-size:11px;letter-spacing:1.4px;text-transform:uppercase;
    color:var(--gold2);font-weight:700;margin:0 0 6px}
  .inp,.ta,.sel{width:100%;padding:11px 13px;border-radius:9px;border:1px solid var(--line);
    background:var(--bg2);color:var(--cream);font-size:15px}
  .inp:focus,.ta:focus,.sel:focus{outline:none;border-color:var(--gold)}
  .ta{resize:vertical;min-height:74px;line-height:1.5}
  .field{margin-bottom:18px}
  .cat-toolbar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-bottom:16px}
  .cat-toolbar .sel{max-width:340px}
  .count-badge{font-size:12.5px;color:var(--muted)}

  /* yükleme bölgesi */
  .drop{border:2px dashed var(--line);border-radius:12px;padding:20px;text-align:center;
    color:var(--soft);background:var(--bg2);cursor:pointer;transition:border-color .15s,background .15s;margin-bottom:20px}
  .drop.drag{border-color:var(--gold);background:rgba(200,150,60,.08)}
  .drop strong{color:var(--gold);display:block;font-size:15px;margin-bottom:4px}
  .drop small{color:var(--muted)}
  .up-list{margin:10px 0 0;font-size:12.5px;color:var(--soft)}

  /* görsel kartları */
  .cards{display:flex;flex-direction:column;gap:14px}
  .pc{display:flex;gap:14px;background:var(--card);border:1px solid var(--line2);border-radius:12px;
    padding:12px;position:relative}
  .pc.drag-over{border-color:var(--gold);box-shadow:0 0 0 2px rgba(216,176,84,.3)}
  .pc .thumb{width:96px;height:128px;flex:none;border-radius:8px;overflow:hidden;
    background:linear-gradient(160deg,#2a2013,#352814);position:relative}
  .pc .thumb img{width:100%;height:100%;object-fit:cover;display:block}
  .pc .thumb.broken::after{content:'görsel yok';position:absolute;inset:0;display:flex;
    align-items:center;justify-content:center;font-size:11px;color:var(--muted);text-align:center;padding:6px}
  .pc .body{flex:1;min-width:0;display:flex;flex-direction:column;gap:8px}
  .pc .fname{font-size:12px;color:var(--muted);word-break:break-all;display:flex;align-items:center;gap:8px}
  .pc .fname .ord{color:var(--gold2);font-weight:700}
  .pc .row{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
  .pc .move-sel{max-width:220px}
  .icon-btn{width:38px;height:38px;flex:none;border-radius:8px;border:1px solid var(--line);
    background:var(--bg2);color:var(--soft);font-size:17px;cursor:pointer;display:inline-flex;
    align-items:center;justify-content:center;line-height:1}
  .icon-btn:hover{border-color:var(--gold);color:var(--gold)}
  .icon-btn[disabled]{opacity:.35;cursor:not-allowed}
  .pc .handle{cursor:grab;touch-action:none}
  .pc-actions{display:flex;flex-direction:column;gap:6px;align-items:center;justify-content:flex-start}
  .empty-msg{color:var(--muted);text-align:center;padding:40px 10px;font-size:14px}

  /* kaydet çubuğu (alt) */
  .savebar{position:fixed;left:0;right:0;bottom:0;z-index:60;display:flex;gap:12px;align-items:center;
    padding:12px 18px;background:rgba(20,15,8,.95);backdrop-filter:blur(8px);border-top:1px solid var(--line)}
  .savebar .spacer{flex:1}
  .savebar .msg{font-size:13px;color:var(--muted)}

  .toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%);z-index:100;
    background:#2a2114;border:1px solid var(--line);color:var(--cream);padding:12px 20px;border-radius:10px;
    box-shadow:var(--shadow);font-size:14px;opacity:0;pointer-events:none;transition:opacity .25s,transform .25s;max-width:90vw;text-align:center}
  .toast.show{opacity:1;transform:translateX(-50%) translateY(-6px)}
  .toast.err{border-color:rgba(201,86,75,.6)}
  .toast.ok{border-color:rgba(79,154,94,.6)}

  @media(max-width:640px){
    .pc .thumb{width:74px;height:100px}
    .topbar .brand small{display:none}
    .wrap{padding:16px 12px 120px}
  }
</style>
</head>
<body>
  <div class="topbar">
    <div class="brand">bisanat <small>Yönetim</small></div>
    <div class="spacer"></div>
    <span class="save-state" id="saveState">yükleniyor…</span>
    <a class="btn ghost sm" href="index.html" target="_blank" rel="noopener">Siteyi Aç ↗</a>
    <a class="btn ghost sm" href="yonetim.php?logout=1">Çıkış</a>
  </div>

  <div class="tabs">
    <button class="tab active" data-tab="gorseller">Galeri Görselleri</button>
    <button class="tab" data-tab="metinler">Sayfa Metinleri</button>
    <button class="tab" data-tab="hyper">Sanatta Bir Yaklaşım</button>
    <button class="tab" data-tab="kategoriler">Kategoriler</button>
  </div>

  <div class="wrap">
    <!-- GÖRSELLER -->
    <section class="panel active" id="panel-gorseller">
      <div class="section-note">
        Kategori seçin; yeni görsel yükleyin, başlık ve açıklama girin, kartları silin,
        <b>▲▼</b> ile sırayı değiştirin veya “Kategoriye taşı” ile başka bölüme aktarın.
        Değişiklikler <b>Kaydet</b> dedikten sonra yayına girer.
      </div>
      <div class="cat-toolbar">
        <select class="sel" id="catSelect"></select>
        <span class="count-badge" id="catCount"></span>
      </div>
      <div class="drop" id="dropZone">
        <strong>＋ Yeni görsel yükle</strong>
        <small>Görselleri buraya sürükleyin ya da tıklayıp seçin — webp, jpg, png</small>
        <div class="up-list" id="upList"></div>
      </div>
      <input type="file" id="fileInput" accept=".webp,.jpg,.jpeg,.png,image/*" multiple hidden>
      <div class="cards" id="cards"></div>
    </section>

    <!-- METİNLER -->
    <section class="panel" id="panel-metinler">
      <div class="section-note">Anasayfa, hakkında ve iletişim bölümlerindeki metinler. Boş bırakılan alan sitedeki varsayılanı kullanır.</div>
      <div id="textFields"></div>
    </section>

    <!-- HYPER -->
    <section class="panel" id="panel-hyper">
      <div class="section-note">“Sanatta Bir Yaklaşım” bölümündeki öne çıkan kart başlık ve açıklamaları.</div>
      <div id="hyperFields"></div>
    </section>

    <!-- KATEGORİLER -->
    <section class="panel" id="panel-kategoriler">
      <div class="section-note">Kategori adı ve giriş (intro) metinleri. <b>▲▼</b> ile kategorilerin sitedeki sırasını değiştirebilirsiniz.</div>
      <div id="catFields"></div>
    </section>
  </div>

  <div class="savebar">
    <span class="msg" id="saveMsg">Değişiklikler otomatik kaydedilmez.</span>
    <div class="spacer"></div>
    <button class="btn" id="saveBtn">Kaydet</button>
  </div>
  <div class="toast" id="toast"></div>

<script>
/* ============================================================
   Sayfa metinleri alan tanımı (index.html ile birebir aynı anahtarlar)
   ============================================================ */
const TEXT_FIELDS = [
  {key:'heroEyebrow', label:'Hero — Üst Etiket', mode:'text'},
  {key:'heroTitle',   label:'Hero — Ana Başlık', mode:'html', hint:'HTML kullanılabilir (ör. &lt;br&gt;, &lt;em&gt;vurgu&lt;/em&gt;).'},
  {key:'heroSub',     label:'Hero — Alt Metin',  mode:'html', hint:'HTML kullanılabilir (ör. &lt;span class="brand-hl"&gt;bisanat&lt;/span&gt;).'},
  {key:'stat1Num',   label:'Arşiv İstatistik 1 — Sayı',   mode:'text'},
  {key:'stat1Label', label:'Arşiv İstatistik 1 — Etiket', mode:'text'},
  {key:'stat2Num',   label:'Arşiv İstatistik 2 — Sayı',   mode:'text'},
  {key:'stat2Label', label:'Arşiv İstatistik 2 — Etiket', mode:'text'},
  {key:'stat3Num',   label:'Arşiv İstatistik 3 — Sayı',   mode:'text'},
  {key:'stat3Label', label:'Arşiv İstatistik 3 — Etiket', mode:'text'},
  {key:'stat4Num',   label:'Arşiv İstatistik 4 — Sayı',   mode:'text'},
  {key:'stat4Label', label:'Arşiv İstatistik 4 — Etiket', mode:'text'},
  {key:'bulentBio', label:'Bülent İşcan — Biyografi', mode:'paragraphs', hint:'Paragraflar arasına boş satır bırakın.'},
  {key:'senayBio',  label:'Şenay İşcan — Biyografi',  mode:'paragraphs', hint:'Paragraflar arasına boş satır bırakın.'},
  {key:'hyperCardTitle', label:'Hiperrealizm Kartı — Başlık', mode:'text'},
  {key:'hyperCardBody',  label:'Hiperrealizm Kartı — Metin',  mode:'paragraphs', hint:'Paragraflar arasına boş satır bırakın. Vurgu için &lt;em class="gold-text"&gt;...&lt;/em&gt;.'},
  {key:'contactBig', label:'İletişim — Vurgu Cümlesi', mode:'text'},
  {key:'siteFooter', label:'Footer Metni', mode:'html'},
];

let DATA = null;
let curSlug = null;
let dirty = false;

const $  = s => document.querySelector(s);
const el = (t,c) => { const e=document.createElement(t); if(c) e.className=c; return e; };
function esc(s){ return String(s==null?'':s).replace(/[&<>"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[m])); }
function imgURL(slug,file){ return encodeURI('assets/'+slug+'/'+file); } /* / .. korunur, boşluk/Türkçe kodlanır */
function catName(slug){ return (DATA.categories[slug] && DATA.categories[slug].name) || slug; }

function setDirty(v){
  dirty=v;
  const s=$('#saveState');
  if(v){ s.textContent='kaydedilmedi'; s.className='save-state dirty'; }
  else { s.textContent='güncel'; s.className='save-state ok'; }
}
function toast(msg,type){
  const t=$('#toast'); t.textContent=msg; t.className='toast show '+(type||'');
  clearTimeout(t._t); t._t=setTimeout(()=>t.className='toast '+(type||''),2600);
}

/* ---------- API ---------- */
async function api(action, opts){
  const r = await fetch('api.php?action='+action, opts||{});
  let j; try{ j=await r.json(); }catch(e){ throw new Error('Sunucu yanıtı okunamadı.'); }
  if(!r.ok || !j.ok) throw new Error(j && j.error ? j.error : ('Hata ('+r.status+')'));
  return j;
}

/* ============================================================
   YÜKLEME
   ============================================================ */
async function loadAll(){
  try{
    const j = await api('load');
    DATA = j.data;
    if(!Array.isArray(DATA.order)) DATA.order=[];
    DATA.categories=DATA.categories||{}; DATA.pieces=DATA.pieces||{};
    DATA.texts=DATA.texts||{}; DATA.hyper=DATA.hyper||{};
    buildCatSelect();
    curSlug = DATA.order[0] || Object.keys(DATA.pieces)[0] || null;
    if(curSlug) $('#catSelect').value=curSlug;
    renderCards();
    renderTextFields();
    renderHyperFields();
    renderCatFields();
    setDirty(false);
  }catch(err){
    $('#saveState').textContent='hata'; $('#saveState').className='save-state err';
    toast('İçerik yüklenemedi: '+err.message,'err');
  }
}

/* ============================================================
   GÖRSELLER
   ============================================================ */
function buildCatSelect(){
  const sel=$('#catSelect'); sel.innerHTML='';
  DATA.order.forEach(slug=>{
    const o=el('option'); o.value=slug; o.textContent=catName(slug)+'  ('+((DATA.pieces[slug]||[]).length)+')';
    sel.appendChild(o);
  });
}
function moveSelectOptions(exclude){
  return DATA.order.filter(s=>s!==exclude)
    .map(s=>`<option value="${esc(s)}">${esc(catName(s))}</option>`).join('');
}
function renderCards(){
  const wrap=$('#cards'); wrap.innerHTML='';
  if(!curSlug){ wrap.innerHTML='<div class="empty-msg">Kategori yok.</div>'; return; }
  const list=DATA.pieces[curSlug]||(DATA.pieces[curSlug]=[]);
  $('#catCount').textContent=list.length+' görsel';
  if(!list.length){ wrap.innerHTML='<div class="empty-msg">Bu kategoride henüz görsel yok. Yukarıdan yükleyebilirsiniz.</div>'; return; }

  list.forEach((p,idx)=>{
    const card=el('div','pc'); card.dataset.idx=idx; card.draggable=false;
    card.innerHTML=`
      <div class="pc-actions">
        <button class="icon-btn handle" title="Sürükle" data-act="handle">⋮⋮</button>
        <button class="icon-btn" title="Yukarı" data-act="up" ${idx===0?'disabled':''}>▲</button>
        <button class="icon-btn" title="Aşağı" data-act="down" ${idx===list.length-1?'disabled':''}>▼</button>
      </div>
      <div class="thumb"><img alt="" loading="lazy" src="${imgURL(curSlug,p.file)}"
           onerror="this.parentNode.classList.add('broken');this.remove();"></div>
      <div class="body">
        <div class="fname"><span class="ord">#${idx+1}</span><span>${esc(p.file)}</span></div>
        <div class="field" style="margin:0">
          <label class="fl">Başlık</label>
          <input class="inp" data-f="title" value="${esc(p.title||'')}" placeholder="(boşsa dosya adından üretilir)">
        </div>
        <div class="field" style="margin:0">
          <label class="fl">Açıklama</label>
          <textarea class="ta" data-f="desc" placeholder="(boşsa otomatik açıklama kullanılır)">${esc(p.desc||'')}</textarea>
        </div>
        <div class="row">
          <select class="sel move-sel" data-act="move" title="Başka kategoriye taşı">
            <option value="">↔ Kategoriye taşı…</option>
            ${moveSelectOptions(curSlug)}
          </select>
          <button class="btn danger sm" data-act="del">Sil</button>
        </div>
      </div>`;

    // alan düzenleme
    card.querySelector('[data-f="title"]').addEventListener('input',e=>{ p.title=e.target.value; setDirty(true); });
    card.querySelector('[data-f="desc"]').addEventListener('input',e=>{ p.desc=e.target.value; setDirty(true); });
    // sıralama
    card.querySelector('[data-act="up"]').addEventListener('click',()=>reorder(idx,idx-1));
    card.querySelector('[data-act="down"]').addEventListener('click',()=>reorder(idx,idx+1));
    // sil
    card.querySelector('[data-act="del"]').addEventListener('click',()=>delPiece(idx));
    // taşı
    card.querySelector('[data-act="move"]').addEventListener('change',e=>{
      const to=e.target.value; if(to) movePiece(idx,to);
    });
    // sürükle-bırak (masaüstü)
    const handle=card.querySelector('[data-act="handle"]');
    handle.addEventListener('mousedown',()=>{ card.draggable=true; });
    card.addEventListener('dragstart',e=>{ card.classList.add('dragging'); e.dataTransfer.effectAllowed='move'; e.dataTransfer.setData('text/plain',idx); });
    card.addEventListener('dragend',()=>{ card.draggable=false; card.classList.remove('dragging'); document.querySelectorAll('.pc.drag-over').forEach(c=>c.classList.remove('drag-over')); });
    card.addEventListener('dragover',e=>{ e.preventDefault(); card.classList.add('drag-over'); });
    card.addEventListener('dragleave',()=>card.classList.remove('drag-over'));
    card.addEventListener('drop',e=>{ e.preventDefault(); const from=parseInt(e.dataTransfer.getData('text/plain'),10); card.classList.remove('drag-over'); if(!isNaN(from)) reorder(from,idx); });

    wrap.appendChild(card);
  });
}
function reorder(from,to){
  const list=DATA.pieces[curSlug]; if(to<0||to>=list.length||from===to) return;
  const [it]=list.splice(from,1); list.splice(to,0,it);
  setDirty(true); renderCards();
}
function delPiece(idx){
  const list=DATA.pieces[curSlug]; const p=list[idx];
  if(!confirm('“'+(p.title||p.file)+'” kartı listeden silinsin mi?\n\n(Tamam: listeden çıkarılır. Ardından dosyayı da sunucudan silmek isteyip istemediğiniz sorulur.)')) return;
  list.splice(idx,1); setDirty(true); buildCatSelect(); $('#catSelect').value=curSlug; renderCards();
  // dosyayı da sunucudan silme seçeneği
  if(confirm('Görsel dosyası da sunucudan (assets/'+curSlug+'/) silinsin mi?\nBu işlem geri alınamaz. İptal ederseniz dosya kalır, yalnızca listeden çıkar.')){
    api('delete_image',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({slug:curSlug,file:p.file})})
      .then(()=>toast('Dosya silindi.','ok'))
      .catch(e=>toast('Dosya silinemedi: '+e.message,'err'));
  }
}
async function movePiece(idx,to){
  const list=DATA.pieces[curSlug]; const p=list[idx];
  const from=curSlug;
  try{
    const j=await api('move_image',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({from,to,file:p.file})});
    if(j.file && j.file!==p.file) p.file=j.file; // çakışma olduysa yeni ad
    list.splice(idx,1);
    (DATA.pieces[to]||(DATA.pieces[to]=[])).push(p);
    setDirty(true); buildCatSelect(); $('#catSelect').value=curSlug; renderCards();
    toast('“'+catName(to)+'” kategorisine taşındı'+(j.note?(' — '+j.note):''),'ok');
  }catch(err){ toast('Taşınamadı: '+err.message,'err'); renderCards(); }
}

/* yükleme */
async function uploadFiles(files){
  if(!curSlug){ toast('Önce kategori seçin.','err'); return; }
  const upList=$('#upList');
  for(const file of files){
    const row=el('div'); row.textContent='⏳ '+file.name+' yükleniyor…'; upList.appendChild(row);
    try{
      const fd=new FormData(); fd.append('file',file); fd.append('slug',curSlug);
      const j=await api('upload',{method:'POST',body:fd});
      (DATA.pieces[curSlug]||(DATA.pieces[curSlug]=[])).push({file:j.file,title:'',desc:''});
      row.textContent='✓ '+j.file;
      setDirty(true); buildCatSelect(); $('#catSelect').value=curSlug; renderCards();
    }catch(err){ row.textContent='✕ '+file.name+' — '+err.message; }
  }
  setTimeout(()=>{ upList.innerHTML=''; }, 4000);
  toast('Yükleme tamam. Başlık/açıklamaları girip Kaydet’e basın.','ok');
}

/* ============================================================
   METİNLER / HYPER / KATEGORİLER
   ============================================================ */
function renderTextFields(){
  const wrap=$('#textFields'); wrap.innerHTML='';
  TEXT_FIELDS.forEach(f=>{
    const val=DATA.texts[f.key]||'';
    const fld=el('div','field');
    const multiline=(f.mode!=='text');
    fld.innerHTML=`<label class="fl">${f.label}</label>`+
      (multiline
        ? `<textarea class="ta" style="min-height:${f.mode==='paragraphs'?'110':'70'}px">${esc(val)}</textarea>`
        : `<input class="inp" value="${esc(val)}">`)+
      (f.hint?`<p class="hint">${f.hint}</p>`:'');
    const inp=fld.querySelector('textarea,input');
    inp.addEventListener('input',e=>{ DATA.texts[f.key]=e.target.value; setDirty(true); });
    wrap.appendChild(fld);
  });
}
function renderHyperFields(){
  const wrap=$('#hyperFields'); wrap.innerHTML='';
  const keys=Object.keys(DATA.hyper||{});
  if(!keys.length){ wrap.innerHTML='<div class="empty-msg">Kart bulunamadı.</div>'; return; }
  keys.forEach(k=>{
    const h=DATA.hyper[k]||(DATA.hyper[k]={title:'',desc:''});
    const box=el('div','field');
    box.style.cssText='background:var(--card);border:1px solid var(--line2);border-radius:12px;padding:14px 16px';
    box.innerHTML=`
      <div style="font-size:11px;letter-spacing:1.4px;text-transform:uppercase;color:var(--muted);margin-bottom:10px">${esc(k)}</div>
      <div class="field" style="margin-bottom:12px"><label class="fl">Başlık</label>
        <input class="inp" data-f="title" value="${esc(h.title||'')}"></div>
      <div class="field" style="margin:0"><label class="fl">Açıklama</label>
        <textarea class="ta" data-f="desc" style="min-height:90px">${esc(h.desc||'')}</textarea></div>`;
    box.querySelector('[data-f="title"]').addEventListener('input',e=>{ h.title=e.target.value; setDirty(true); });
    box.querySelector('[data-f="desc"]').addEventListener('input',e=>{ h.desc=e.target.value; setDirty(true); });
    wrap.appendChild(box);
  });
}
function renderCatFields(){
  const wrap=$('#catFields'); wrap.innerHTML='';
  DATA.order.forEach((slug,i)=>{
    const c=DATA.categories[slug]||(DATA.categories[slug]={name:'',introTitle:'',introParagraphs:''});
    const box=el('div','field');
    box.style.cssText='background:var(--card);border:1px solid var(--line2);border-radius:12px;padding:14px 16px';
    box.innerHTML=`
      <div class="row" style="display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <span style="font-size:12px;color:var(--muted)">#${i+1} · ${esc(slug)}</span>
        <div style="flex:1"></div>
        <button class="icon-btn" data-act="up" ${i===0?'disabled':''} title="Sırayı yukarı al">▲</button>
        <button class="icon-btn" data-act="down" ${i===DATA.order.length-1?'disabled':''} title="Sırayı aşağı al">▼</button>
      </div>
      <div class="field" style="margin-bottom:12px"><label class="fl">Kategori Adı</label>
        <input class="inp" data-f="name" value="${esc(c.name||'')}"></div>
      <div class="field" style="margin-bottom:12px"><label class="fl">Giriş Başlığı</label>
        <input class="inp" data-f="introTitle" value="${esc(c.introTitle||'')}"></div>
      <div class="field" style="margin:0"><label class="fl">Giriş Metni (paragraflar)</label>
        <textarea class="ta" data-f="introParagraphs" style="min-height:100px">${esc(c.introParagraphs||'')}</textarea>
        <p class="hint">Paragraflar arasına boş satır bırakın.</p></div>`;
    box.querySelector('[data-f="name"]').addEventListener('input',e=>{ c.name=e.target.value; setDirty(true); buildCatSelect(); if(curSlug)$('#catSelect').value=curSlug; });
    box.querySelector('[data-f="introTitle"]').addEventListener('input',e=>{ c.introTitle=e.target.value; setDirty(true); });
    box.querySelector('[data-f="introParagraphs"]').addEventListener('input',e=>{ c.introParagraphs=e.target.value; setDirty(true); });
    box.querySelector('[data-act="up"]').addEventListener('click',()=>reorderCat(i,i-1));
    box.querySelector('[data-act="down"]').addEventListener('click',()=>reorderCat(i,i+1));
    wrap.appendChild(box);
  });
}
function reorderCat(from,to){
  if(to<0||to>=DATA.order.length) return;
  const [s]=DATA.order.splice(from,1); DATA.order.splice(to,0,s);
  setDirty(true); renderCatFields(); buildCatSelect(); if(curSlug)$('#catSelect').value=curSlug;
}

/* ============================================================
   KAYDET
   ============================================================ */
async function saveAll(){
  const btn=$('#saveBtn'); btn.disabled=true; btn.textContent='Kaydediliyor…';
  try{
    await api('save',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({data:DATA})});
    setDirty(false); toast('Kaydedildi. Değişiklikler yayında.','ok');
    $('#saveMsg').textContent='Son kayıt: '+new Date().toLocaleTimeString('tr-TR');
  }catch(err){ toast('Kaydedilemedi: '+err.message,'err'); }
  finally{ btn.disabled=false; btn.textContent='Kaydet'; }
}

/* ============================================================
   OLAYLAR
   ============================================================ */
document.querySelectorAll('.tab').forEach(t=>{
  t.addEventListener('click',()=>{
    document.querySelectorAll('.tab').forEach(x=>x.classList.remove('active'));
    document.querySelectorAll('.panel').forEach(x=>x.classList.remove('active'));
    t.classList.add('active');
    $('#panel-'+t.dataset.tab).classList.add('active');
  });
});
$('#catSelect').addEventListener('change',e=>{ curSlug=e.target.value; $('#upList').innerHTML=''; renderCards(); });
$('#saveBtn').addEventListener('click',saveAll);

const dz=$('#dropZone'), fi=$('#fileInput');
dz.addEventListener('click',()=>fi.click());
fi.addEventListener('change',e=>{ if(e.target.files.length) uploadFiles(e.target.files); fi.value=''; });
['dragenter','dragover'].forEach(ev=>dz.addEventListener(ev,e=>{ e.preventDefault(); dz.classList.add('drag'); }));
['dragleave','drop'].forEach(ev=>dz.addEventListener(ev,e=>{ e.preventDefault(); if(ev==='drop'){} dz.classList.remove('drag'); }));
dz.addEventListener('drop',e=>{ const f=e.dataTransfer.files; if(f&&f.length) uploadFiles(f); });

window.addEventListener('keydown',e=>{ if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='s'){ e.preventDefault(); saveAll(); } });
window.addEventListener('beforeunload',e=>{ if(dirty){ e.preventDefault(); e.returnValue=''; } });

loadAll();
</script>
</body>
</html>
