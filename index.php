<?php
// ============================================================
//  ホスト監視モニター - PHP版（単体ファイル）
//  MELCHIOR-1 / BALTHASAR-2 / CASPER-3 / ARTABAN-4 の状態監視
//  データ: DediRock上のコレクタが30秒毎に収集し、OCI-01経由で配信。
//  このページは5秒毎に ?api=1 を取得して画面リロードなしで更新する。
// ============================================================
$STATUS_URL = 'http://127.0.0.1/status-YOUR_TOKEN.json';
// 実環境のURLは magi-config.php に分離（Git管理外）。同フォルダにあれば読み込む
if (is_file(__DIR__ . '/magi-config.php')) { include __DIR__ . '/magi-config.php'; }

// ---------- APIモード: 状態JSONを中継（4秒キャッシュ） ----------
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $cache = sys_get_temp_dir() . '/magi_status_cache.json';
    $body = false;
    if (is_file($cache) && (time() - filemtime($cache)) < 4) {
        $body = file_get_contents($cache);
    } else {
        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $fetched = @file_get_contents($STATUS_URL, false, $ctx);
        if ($fetched !== false && strlen($fetched) > 20) {
            $body = $fetched;
            @file_put_contents($cache, $body);
        } elseif (is_file($cache)) {
            $body = file_get_contents($cache); // 取得失敗時は古いキャッシュで継続
        }
    }
    if ($body === false) {
        http_response_code(502);
        echo '{"error":"status unavailable"}';
    } else {
        echo $body;
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ホスト監視モニター</title>
<style>
/* 本家フォント: magi-font.woff2（使用文字のみのサブセット）を同じフォルダに置くと有効になります */
@font-face {
  font-family: 'MagiFont';
  src: url('magi-font.woff2?v=4') format('woff2');
  font-display: swap;
}
:root {
  --org: #ff7f00; --org2: #ffb35c; --red: #ff2a1a; --bg: #050505;
  --panel: rgba(13, 10, 6, 0.93); --line: #6b4200; --txt: #ffd9a0;
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  background: var(--bg); color: var(--txt);
  font-family: 'MagiFont', 'Hiragino Kaku Gothic ProN', 'Yu Gothic', sans-serif;
  padding: 14px; max-width: 920px; margin: 0 auto;
}
#bgnet { position: fixed; inset: 0; z-index: 0; pointer-events: none; }
.wrap { position: relative; z-index: 1; }
/* 文字変形: 横80% / 縦110%（本家ロゴの字形比率に寄せる） */
.tx { display: inline-block; transform: scale(0.8, 1.1); }
.txc { transform-origin: center center; }
.txl { transform-origin: left center; }
.txr { transform-origin: right center; }
header { border: 2px solid var(--org); padding: 12px 14px; position: relative;
  background: linear-gradient(180deg, rgba(26,15,0,0.94), rgba(13,10,6,0.94)); }
header h1 { color: var(--org); font-size: 28px; letter-spacing: .35em; text-align: center; line-height: 1.5; }
header .sub { text-align: center; color: var(--org2); font-size: 12px; letter-spacing: .5em; margin-top: 2px; line-height: 1.6; }
.stripe { height: 10px; margin: 10px 0;
  background: repeating-linear-gradient(45deg, var(--org) 0 14px, #000 14px 28px); opacity: .92; }
.meta { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;
  font-size: 13px; color: var(--org2); line-height: 1.6; }
.meta b { color: #fff; font-weight: normal; }
.verdict { margin: 12px 0; border: 1px solid var(--org); padding: 10px; text-align: center;
  font-size: 20px; letter-spacing: .3em; color: #7dff6a; background: rgba(10,18,6,0.93); line-height: 1.6; }
.verdict.alert { color: var(--red); border-color: var(--red); background: rgba(22,4,4,0.93);
  animation: blink 1s steps(2) infinite; }
@keyframes blink { 50% { opacity: .55; } }
.units { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
@media (max-width: 860px) { .units { grid-template-columns: 1fr; } }
.unit { border: 1px solid var(--org); background: var(--panel); padding: 12px; position: relative; }
.unit.down { border-color: var(--red); }
.hex { width: 54px; height: 60px; margin: 0 auto 6px; background: var(--org); color: #000;
  clip-path: polygon(50% 0, 100% 25%, 100% 75%, 50% 100%, 0 75%, 0 25%);
  display: flex; align-items: center; justify-content: center; font-size: 22px; }
.unit.down .hex { background: var(--red); color: #fff; animation: blink 1s steps(2) infinite; }
.unit h2 { text-align: center; color: var(--org); font-size: 19px; letter-spacing: .18em; line-height: 1.6; }
.unit .name { text-align: center; color: #fff; font-size: 14px; margin-bottom: 2px; line-height: 1.6; }
.unit .role { text-align: center; color: var(--org2); font-size: 12px; margin-bottom: 10px; line-height: 1.6; }
.lamp { text-align: center; font-size: 13px; margin-bottom: 8px; color: #7dff6a; line-height: 1.6; }
.unit.down .lamp { color: var(--red); }
.unit.stale { filter: saturate(.35); }
#age.stale, #collected.stale { color: var(--red); }
.row { display: flex; justify-content: space-between; font-size: 13px; padding: 3px 0;
  border-top: 1px dashed var(--line); line-height: 1.55; }
.row span:first-child { color: var(--org2); }
.gauge { height: 14px; border: 1px solid var(--org); margin: 4px 0 8px; position: relative; background: rgba(0,0,0,0.85); }
.gauge i { position: absolute; inset: 0; width: 0%; background: linear-gradient(90deg, #a85400, var(--org)); transition: width .6s; }
.gauge.hot i { background: linear-gradient(90deg, #a80000, var(--red)); }
.glabel { display: flex; justify-content: space-between; font-size: 12px; color: var(--org2); line-height: 1.55; }
footer { margin-top: 14px; font-size: 12px; color: #9c7433; text-align: center; line-height: 1.8; }
.err { color: var(--red); text-align: center; padding: 8px; line-height: 1.6; }
.ctl { text-align: right; margin: 0 0 10px; }
.ctl button { background: #000; color: var(--org2); border: 1px solid var(--org);
  font-family: inherit; font-size: 13px; padding: 5px 14px; cursor: pointer; letter-spacing: .15em; }
.ctl button:hover { background: #2a1800; color: #fff; }
.dials { display: flex; justify-content: space-around; margin: 4px 0 8px; }
.dial { text-align: center; }
.dial .cap { font-size: 12px; color: var(--org2); margin-top: 2px; line-height: 1.55; }
</style>
</head>
<body>
<canvas id="bgnet"></canvas>
<div class="wrap">
<header>
  <h1><span class="tx txc">ホスト監視モニター</span></h1>
  <div class="sub"><span class="tx txc">全ホスト状態監視</span></div>
</header>
<div class="stripe"></div>
<div class="meta">
  <div><span class="tx txl">現在時刻 <b id="clock">--</b></span></div>
  <div><span class="tx txc">最終収集 <b id="collected">--</b>（<b id="age">--</b>秒前）</span></div>
  <div><span class="tx txr">次回更新まで <b id="count">5</b> 秒</span></div>
</div>
<div class="verdict" id="verdict"><span class="tx txc">合議中…</span></div>
<div class="ctl"><button id="modeBtn" type="button">表示切替</button></div>
<div class="units" id="units"></div>
<div class="stripe"></div>
<footer>
  <span class="tx txc">本画面は実クラスタではなく、4台のホストの状態監視ダッシュボードです。</span><br>
  <span class="tx txc">データは DediRock 上のコレクタが30秒毎にSSHで収集しています。画面は5秒毎に自動更新されます（リロードなし）。</span>
</footer>
</div>
<script>
// ---------- 背景: ノードネットワーク（最下層・薄く・ゆっくり） ----------
(function () {
  const cv = document.getElementById('bgnet'), cx = cv.getContext('2d');
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  let W = 0, H = 0, nodes = [];
  function resize() {
    W = cv.width = window.innerWidth; H = cv.height = window.innerHeight;
    const n = Math.min(170, Math.max(40, Math.floor(W * H / 11000)));
    nodes = [];
    for (let i = 0; i < n; i++) {
      nodes.push({
        x: Math.random() * W, y: Math.random() * H,
        vx: (Math.random() - 0.5) * 0.36, vy: (Math.random() - 0.5) * 0.36
      });
    }
  }
  function draw() {
    cx.clearRect(0, 0, W, H);
    const LINK = 130;
    for (let i = 0; i < nodes.length; i++) {
      const a = nodes[i];
      for (let j = i + 1; j < nodes.length; j++) {
        const b = nodes[j], dx = a.x - b.x, dy = a.y - b.y, d = Math.hypot(dx, dy);
        if (d < LINK) {
          cx.strokeStyle = 'rgba(255,127,0,' + ((1 - d / LINK) * 0.32).toFixed(3) + ')';
          cx.lineWidth = 1.4;
          cx.beginPath(); cx.moveTo(a.x, a.y); cx.lineTo(b.x, b.y); cx.stroke();
        }
      }
    }
    cx.fillStyle = 'rgba(255,179,92,0.85)';
    for (const p of nodes) { cx.beginPath(); cx.arc(p.x, p.y, 1.8, 0, 6.2832); cx.fill(); }
  }
  function step() {
    for (const p of nodes) {
      p.x += p.vx; p.y += p.vy;
      if (p.x < 0 || p.x > W) p.vx *= -1;
      if (p.y < 0 || p.y > H) p.vy *= -1;
    }
    draw();
    requestAnimationFrame(step);
  }
  window.addEventListener('resize', resize);
  resize();
  if (reduced) { draw(); } else { step(); }
})();

// ---------- 状態表示 ----------
const $ = id => document.getElementById(id);
let nextIn = 5;

function fmtUp(sec) {
  sec = parseInt(sec || 0, 10);
  const d = Math.floor(sec / 86400), h = Math.floor(sec % 86400 / 3600), m = Math.floor(sec % 3600 / 60);
  return d + '日 ' + h + '時間 ' + m + '分';
}
function pct(used, total) { return total > 0 ? Math.round(used / total * 100) : 0; }

// ---------- ゲージ表示切替（バー / 円形メーター） ----------
let mode = 'bar';
try { mode = localStorage.getItem('hostmon-mode') || 'bar'; } catch (e) {}
let lastData = null;

function polar(cx, cy, r, deg) {
  const a = deg * Math.PI / 180;
  return [cx + r * Math.sin(a), cy - r * Math.cos(a)];
}
function arcPath(cx, cy, r, from, to) {
  const p1 = polar(cx, cy, r, from), p2 = polar(cx, cy, r, to);
  return 'M ' + p1[0].toFixed(1) + ' ' + p1[1].toFixed(1) + ' A ' + r + ' ' + r + ' 0 ' +
    (to - from > 180 ? 1 : 0) + ' 1 ' + p2[0].toFixed(1) + ' ' + p2[1].toFixed(1);
}
function dial(p, hot) {
  const col = hot ? '#ff2a1a' : '#ff7f00';
  let ticks = '';
  for (let i = 0; i <= 50; i++) {
    const a = -135 + 270 * i / 50, major = i % 5 === 0;
    const q1 = polar(60, 60, major ? 40 : 44, a), q2 = polar(60, 60, 50, a);
    ticks += '<line x1="' + q1[0].toFixed(1) + '" y1="' + q1[1].toFixed(1) + '" x2="' + q2[0].toFixed(1) +
      '" y2="' + q2[1].toFixed(1) + '" stroke="' + (major ? '#ffb35c' : '#6b4200') +
      '" stroke-width="' + (major ? 1.6 : 1) + '"/>';
  }
  const nums = [0, 50, 100].map(v => {
    const q = polar(60, 60, 30, -135 + 2.7 * v);
    return '<text x="' + q[0].toFixed(1) + '" y="' + (q[1] + 3).toFixed(1) +
      '" font-size="9" fill="#ffb35c" text-anchor="middle">' + v + '</text>';
  }).join('');
  const ang = -135 + 2.7 * Math.max(0, Math.min(100, p));
  return '<svg width="118" height="118" viewBox="0 0 120 120">' +
    '<path d="' + arcPath(60, 60, 50, -135, 135) + '" fill="none" stroke="#241600" stroke-width="3"/>' +
    (p > 0 ? '<path d="' + arcPath(60, 60, 50, -135, ang) + '" fill="none" stroke="' + col + '" stroke-width="3"/>' : '') +
    ticks + nums +
    '<g style="transform: rotate(' + ang.toFixed(1) + 'deg); transform-origin: 60px 60px; transition: transform .7s ease;">' +
    '<line x1="60" y1="62" x2="60" y2="25" stroke="#ffffff" stroke-width="2"/></g>' +
    '<circle cx="60" cy="60" r="4.5" fill="' + col + '"/>' +
    '<text x="60" y="94" font-size="16" fill="#ffffff" text-anchor="middle">' + p + '%</text></svg>';
}
function gauges(u, mp, lp) {
  const dp = u.disk_pct || 0;
  if (mode === 'dial') {
    return '<div class="dials">' +
      '<div class="dial">' + dial(mp, mp >= 85) + '<div class="cap"><span class="tx txc">メモリ使用率</span></div></div>' +
      '<div class="dial">' + dial(lp, lp >= 85) + '<div class="cap"><span class="tx txc">負荷（LOAD / 論理CPU）</span></div></div>' +
      '<div class="dial">' + dial(dp, dp >= 85) + '<div class="cap"><span class="tx txc">ディスク使用率</span></div></div>' +
      '</div>';
  }
  return '<div class="glabel"><span class="tx txl">メモリ使用率</span><span class="tx txr">' + mp + '%</span></div>' +
    '<div class="gauge' + (mp >= 85 ? ' hot' : '') + '"><i style="width:' + mp + '%"></i></div>' +
    '<div class="glabel"><span class="tx txl">負荷（LOAD / 論理CPU）</span><span class="tx txr">' + lp + '%</span></div>' +
    '<div class="gauge' + (lp >= 85 ? ' hot' : '') + '"><i style="width:' + lp + '%"></i></div>' +
    '<div class="glabel"><span class="tx txl">ディスク使用率</span><span class="tx txr">' + dp + '%</span></div>' +
    '<div class="gauge' + (dp >= 85 ? ' hot' : '') + '"><i style="width:' + dp + '%"></i></div>';
}
function applyModeBtn() {
  $('modeBtn').textContent = mode === 'bar' ? 'メーター表示に切替' : 'バー表示に切替';
}

function render(data) {
  lastData = data;
  const okCount = data.units.filter(u => u.status === 'ok').length;
  const ageSec = (Date.now() - new Date(data.collected_at).getTime()) / 1000;
  const stale = ageSec > 90;
  const v = $('verdict');
  if (stale) {
    v.innerHTML = '<span class="tx txc">警告： データ停止（' + Math.max(1, Math.round(ageSec / 60)) + '分前から更新なし）</span>'; v.className = 'verdict alert';
  } else if (okCount === data.units.length) {
    v.innerHTML = '<span class="tx txc">全ユニット正常稼働</span>'; v.className = 'verdict';
  } else {
    v.innerHTML = '<span class="tx txc">警告： ' + (data.units.length - okCount) + ' ユニット異常</span>'; v.className = 'verdict alert';
  }
  $('collected').textContent = data.collected_at.replace('T', ' ').replace('+09:00', '');
  $('units').innerHTML = data.units.map((u, i) => {
    if (u.status !== 'ok') {
      return '<div class="unit down"><div class="hex">' + (i + 1) + '</div>' +
        '<h2><span class="tx txc">' + u.name + '</span></h2>' +
        '<div class="role"><span class="tx txc">' + u.role + '</span></div>' +
        '<div class="lamp"><span class="tx txc">● 到達不能</span></div>' +
        '<div class="err"><span class="tx txc">応答がありません。対象ホストの状態を確認してください。</span></div></div>';
    }
    const mp = pct(u.mem_used_mb, u.mem_total_mb);
    const lp = Math.min(100, Math.round(u.load[0] / Math.max(u.cpu, 1) * 100));
    return '<div class="unit"><div class="hex">' + (i + 1) + '</div>' +
      '<h2><span class="tx txc">' + u.name + '</span></h2>' +
      '<div class="role"><span class="tx txc">' + u.role + '</span></div>' +
      '<div class="lamp"><span class="tx txc">● 正常稼働</span></div>' +
      gauges(u, mp, lp) +
      '<div class="row"><span class="tx txl">負荷平均</span><span class="tx txr">' + u.load.join(' / ') + '</span></div>' +
      '<div class="row"><span class="tx txl">CPU</span><span class="tx txr">' + (u.cpu_name ? u.cpu_name + ' ' : '') + '（' + u.cpu + 'コア）</span></div>' +
      '<div class="row"><span class="tx txl">メモリ</span><span class="tx txr">' + u.mem_used_mb + ' / ' + u.mem_total_mb + ' MB</span></div>' +
      '<div class="row"><span class="tx txl">スワップ</span><span class="tx txr">' + u.swap_used_mb + ' / ' + u.swap_total_mb + ' MB</span></div>' +
      '<div class="row"><span class="tx txl">ディスク</span><span class="tx txr">' + Math.round(u.disk_used_mb / 1024) + ' / ' + Math.round(u.disk_total_mb / 1024) + ' GB（' + u.disk_pct + '%）</span></div>' +
      '<div class="row"><span class="tx txl">起動時間</span><span class="tx txr">' + fmtUp(u.uptime_sec) + '</span></div>' +
      '<div class="row"><span class="tx txl">OS</span><span class="tx txr">' + u.os + '</span></div>' +
      '<div class="row"><span class="tx txl">カーネル</span><span class="tx txr">' + u.kernel + '</span></div>' +
      (u.extra ? '<div class="row"><span class="tx txl">コンテナ</span><span class="tx txr">' + u.extra + '</span></div>' : '') +
      '</div>';
  }).join('');
  const collectedMs = new Date(data.collected_at).getTime();
  $('age').textContent = Math.max(0, Math.round((Date.now() - collectedMs) / 1000));
  $('age').classList.toggle('stale', stale);
  $('collected').classList.toggle('stale', stale);
  if (stale) document.querySelectorAll('.unit').forEach(el => el.classList.add('stale'));
}

async function fetchStatus() {
  try {
    const r = await fetch('?api=1&_=' + Date.now(), { cache: 'no-store' });
    if (!r.ok) throw new Error(r.status);
    render(await r.json());
  } catch (e) {
    const v = $('verdict');
    v.innerHTML = '<span class="tx txc">警告： 状態データを取得できません</span>'; v.className = 'verdict alert';
  }
  nextIn = 5;
}
setInterval(() => {
  const now = new Date();
  $('clock').textContent = now.toLocaleString('ja-JP', { hour12: false });
  nextIn--;
  if (nextIn <= 0) { fetchStatus(); }
  $('count').textContent = Math.max(0, nextIn);
}, 1000);
$('modeBtn').addEventListener('click', () => {
  mode = mode === 'bar' ? 'dial' : 'bar';
  try { localStorage.setItem('hostmon-mode', mode); } catch (e) {}
  applyModeBtn();
  if (lastData) render(lastData);
});
applyModeBtn();
fetchStatus();
</script>
</body>
</html>
