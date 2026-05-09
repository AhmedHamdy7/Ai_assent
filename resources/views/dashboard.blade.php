<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>{{ config('app.name', 'hamdix') }} · operating layer</title>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Geist+Mono:wght@400;500;600&family=Geist:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />

<script src="https://cdn.tailwindcss.com"></script>
<script>
  tailwind.config = {
    theme: {
      extend: {
        fontFamily: {
          sans: ['Geist', 'ui-sans-serif', 'system-ui'],
          mono: ['Geist Mono', 'ui-monospace'],
          serif: ['Instrument Serif', 'serif'],
        },
        colors: {
          ink: { 0: '#06080D', 1: '#0A0D14', 2: '#0F131C', 3: '#161B26' },
          line: 'rgba(255,255,255,0.07)',
          fog: 'rgba(255,255,255,0.55)',
          cyan: { glow: '#5BD4FF' },
          azure: { glow: '#5C7BFF' },
        },
        boxShadow: {
          'edge': 'inset 0 1px 0 rgba(255,255,255,0.06), 0 1px 0 rgba(0,0,0,0.4)',
          'glow-cyan': '0 0 0 1px rgba(91,212,255,0.25), 0 0 24px -4px rgba(91,212,255,0.35)',
          'glow-azure': '0 0 0 1px rgba(92,123,255,0.25), 0 0 24px -4px rgba(92,123,255,0.35)',
        },
      },
    },
  };
</script>

<style>
  :root {
    --bg: #06080D;
    --surface: rgba(255,255,255,0.035);
    --surface-2: rgba(255,255,255,0.055);
    --line: rgba(255,255,255,0.07);
    --line-2: rgba(255,255,255,0.12);
    --text: rgba(255,255,255,0.94);
    --fog: rgba(255,255,255,0.55);
    --mute: rgba(255,255,255,0.38);
    --cyan: #5BD4FF;
    --azure: #5C7BFF;
    --aurora: #9D7BFF;
    --positive: #5EE7A8;
    --warn: #FFC56B;
    --danger: #FF7A8A;
    --radius: 16px;
  }

  html, body { background: var(--bg); color: var(--text); }
  body {
    font-family: 'Geist', ui-sans-serif, system-ui;
    font-feature-settings: "ss01", "cv11";
    -webkit-font-smoothing: antialiased;
    letter-spacing: -0.01em;
  }
  @media (min-width: 1024px) {
    body { overflow: hidden; }
  }
  @media (max-width: 1023px) {
    body { overflow-x: hidden; }
  }

  .scene-bg {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background:
      radial-gradient(1100px 700px at 78% -10%, rgba(92,123,255,0.18), transparent 60%),
      radial-gradient(900px 600px at -10% 10%, rgba(91,212,255,0.12), transparent 60%),
      radial-gradient(1200px 800px at 50% 110%, rgba(157,123,255,0.10), transparent 60%),
      linear-gradient(180deg, #07090F 0%, #06080D 100%);
  }
  .scene-grid {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background-image:
      linear-gradient(to right, rgba(255,255,255,0.025) 1px, transparent 1px),
      linear-gradient(to bottom, rgba(255,255,255,0.025) 1px, transparent 1px);
    background-size: 56px 56px;
    mask-image: radial-gradient(ellipse 80% 60% at 50% 40%, black 50%, transparent 100%);
  }
  .scene-noise {
    position: fixed; inset: 0; z-index: 0; pointer-events: none; opacity: .35;
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='160'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 0.05 0'/></filter><rect width='160' height='160' filter='url(%23n)'/></svg>");
    mix-blend-mode: overlay;
  }

  .cursor-light {
    position: fixed; inset: 0; z-index: 0; pointer-events: none;
    background: radial-gradient(360px 360px at var(--mx, 50%) var(--my, 30%), rgba(91,212,255,0.10), transparent 60%);
    transition: background 200ms ease;
  }

  .glass {
    background: var(--surface);
    border: 1px solid var(--line);
    backdrop-filter: blur(22px) saturate(140%);
    -webkit-backdrop-filter: blur(22px) saturate(140%);
    border-radius: var(--radius);
    box-shadow: inset 0 1px 0 rgba(255,255,255,0.05), 0 30px 60px -30px rgba(0,0,0,0.6);
    position: relative;
  }
  .glass::after {
    content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
    background: linear-gradient(135deg, rgba(255,255,255,0.06), rgba(255,255,255,0) 30%, rgba(255,255,255,0) 70%, rgba(255,255,255,0.04));
    mix-blend-mode: overlay;
  }
  .glass-strong {
    background: var(--surface-2);
    border: 1px solid var(--line-2);
  }

  .ring-aurora { position: relative; }
  .ring-aurora::before {
    content: ""; position: absolute; inset: -1px; border-radius: inherit; padding: 1px;
    background: conic-gradient(from var(--angle, 0deg), rgba(91,212,255,0.0) 0%, rgba(91,212,255,0.55) 25%, rgba(92,123,255,0.55) 50%, rgba(157,123,255,0.0) 70%, rgba(91,212,255,0.0) 100%);
    -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
    -webkit-mask-composite: xor; mask-composite: exclude;
    pointer-events: none;
    background: linear-gradient(135deg, rgba(91,212,255,0.0) 0%, rgba(91,212,255,0.45) 30%, rgba(92,123,255,0.45) 70%, rgba(91,212,255,0.0) 100%);
  }

  @keyframes pulseRing { 0% { box-shadow: 0 0 0 0 rgba(94,231,168,0.55); } 70% { box-shadow: 0 0 0 10px rgba(94,231,168,0); } 100% { box-shadow: 0 0 0 0 rgba(94,231,168,0); } }
  .pulse-dot { animation: pulseRing 1.8s ease-out infinite; }
  @keyframes pulseRingCyan { 0% { box-shadow: 0 0 0 0 rgba(91,212,255,0.55); } 70% { box-shadow: 0 0 0 10px rgba(91,212,255,0); } 100% { box-shadow: 0 0 0 0 rgba(91,212,255,0); } }
  .pulse-cyan { animation: pulseRingCyan 1.8s ease-out infinite; }

  @keyframes fadeUp { from { opacity: 0; transform: translateY(10px) scale(0.995); filter: blur(6px); } to { opacity: 1; transform: translateY(0) scale(1); filter: blur(0); } }
  .reveal { opacity: 0; animation: fadeUp 700ms cubic-bezier(.2,.7,.2,1) forwards; }

  @keyframes streamScroll { from { transform: translateY(0); } to { transform: translateY(-50%); } }

  @keyframes caret { 50% { opacity: 0; } }
  .caret { display: inline-block; width: 0.55ch; height: 1em; background: var(--cyan); margin-left: 2px; vertical-align: -2px; animation: caret 1s steps(1) infinite; box-shadow: 0 0 8px var(--cyan); }

  ::-webkit-scrollbar { width: 10px; height: 10px; }
  ::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.07); border-radius: 99px; }
  ::-webkit-scrollbar-track { background: transparent; }

  input, button, select, textarea { background: transparent; color: inherit; outline: none; }

  .magnet { transition: transform 220ms cubic-bezier(.2,.7,.2,1), box-shadow 220ms ease, background 220ms ease; }
  .magnet:hover { box-shadow: 0 0 0 1px rgba(91,212,255,0.35), 0 0 30px -8px rgba(91,212,255,0.5); }

  .spark { stroke: var(--cyan); fill: none; stroke-width: 1.5; }

  @keyframes waveBar { 0%,100% { transform: scaleY(.3); } 50% { transform: scaleY(1); } }

  ::selection { background: rgba(91,212,255,0.3); }
  *:focus { outline: none; }

  .chip { font-family: 'Geist Mono', ui-monospace; font-size: 11px; letter-spacing: 0.04em; padding: 3px 8px; border-radius: 6px; border: 1px solid var(--line); color: var(--fog); }

  .underglow-cyan { box-shadow: 0 30px 80px -30px rgba(91,212,255,0.25), 0 1px 0 rgba(255,255,255,0.04) inset; }
  .underglow-azure { box-shadow: 0 30px 80px -30px rgba(92,123,255,0.30), 0 1px 0 rgba(255,255,255,0.04) inset; }

  .tabular { font-variant-numeric: tabular-nums; }
</style>
</head>
<body>

<div class="scene-bg"></div>
<div class="scene-grid"></div>
<div class="scene-noise"></div>
<div class="cursor-light" id="cursorLight"></div>
<canvas id="particles" class="fixed inset-0 z-0 pointer-events-none"></canvas>

<div id="root" class="relative z-10"></div>

<!-- Diagnostic banner: shows clear error if React fails to mount within 6s -->
<div id="boot-diag" style="display:none;position:fixed;top:14px;left:50%;transform:translateX(-50%);z-index:9999;max-width:92vw;background:rgba(255,122,138,0.10);border:1px solid rgba(255,122,138,0.4);color:#FF7A8A;padding:14px 18px;border-radius:12px;font-family:'Geist Mono',ui-monospace;font-size:12.5px;line-height:1.6;backdrop-filter:blur(20px);">
  <div style="font-weight:600;letter-spacing:0.08em;text-transform:uppercase;font-size:11px;margin-bottom:6px;">Dashboard failed to load</div>
  <div id="boot-diag-msg" style="color:rgba(255,255,255,0.85);"></div>
</div>

<script>
  // Capture every JS error + unhandled rejection so we can SHOW it in the banner.
  window.__BOOT_ERRORS = [];
  function recordError(label, err) {
    var msg = '';
    if (err && err.message) msg = err.message;
    else if (typeof err === 'string') msg = err;
    else msg = String(err);
    if (err && err.filename) msg += '  @ ' + err.filename + ':' + (err.lineno || '?') + ':' + (err.colno || '?');
    window.__BOOT_ERRORS.push(label + ': ' + msg);
  }
  window.addEventListener('error', function (e) {
    recordError('error', e.error || e);
  }, true);
  window.addEventListener('unhandledrejection', function (e) {
    recordError('promise', e.reason || e);
  });

  // Fail-loud: if React didn't mount in 6s, show what's missing + the actual errors.
  window.__BOOT_TIMER = setTimeout(function () {
    var diag = document.getElementById('boot-diag');
    var msg  = document.getElementById('boot-diag-msg');
    if (!diag || !msg) return;
    var problems = [];
    if (typeof React === 'undefined') problems.push('React did not load (CDN blocked or slow)');
    if (typeof ReactDOM === 'undefined') problems.push('ReactDOM did not load');
    if (typeof Babel === 'undefined') problems.push('Babel did not load');
    if (typeof tailwind === 'undefined') problems.push('Tailwind Play CDN did not load');
    var root = document.getElementById('root');
    var notMounted = root && !root.firstChild;
    if (problems.length === 0 && notMounted) {
      problems.push('Scripts loaded but React did not mount.');
    }
    var errors = window.__BOOT_ERRORS || [];
    if (problems.length === 0 && errors.length === 0) return; // all good
    diag.style.display = 'block';
    var html = problems.map(function (p) { return '• ' + p; }).join('<br>');
    if (errors.length > 0) {
      html += '<br><br><span style="color:rgba(255,255,255,0.85);font-weight:600">Captured errors:</span><br>';
      html += errors.slice(0, 6).map(function (e) {
        return '<span style="color:rgba(255,255,255,0.75)">• ' + e.replace(/[<>&]/g, function (c) { return ({'<':'&lt;','>':'&gt;','&':'&amp;'})[c]; }) + '</span>';
      }).join('<br>');
    }
    msg.innerHTML = html;
  }, 6000);
</script>

<script crossorigin src="https://unpkg.com/react@18.3.1/umd/react.production.min.js"></script>
<script crossorigin src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.production.min.js"></script>
<script crossorigin src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js"></script>
<script>
  // CDN fallback: if unpkg failed, try jsdelivr.
  (function () {
    function load(src, cb) {
      var s = document.createElement('script');
      s.src = src;
      s.crossOrigin = 'anonymous';
      s.onload = cb;
      s.onerror = function () { console.error('Failed to load', src); cb && cb(false); };
      document.head.appendChild(s);
    }
    var todo = [];
    if (typeof React === 'undefined') todo.push('https://cdn.jsdelivr.net/npm/react@18.3.1/umd/react.production.min.js');
    if (typeof ReactDOM === 'undefined') todo.push('https://cdn.jsdelivr.net/npm/react-dom@18.3.1/umd/react-dom.production.min.js');
    if (typeof Babel === 'undefined') todo.push('https://cdn.jsdelivr.net/npm/@babel/standalone@7.29.0/babel.min.js');
    todo.forEach(function (src) { load(src); });
  })();
</script>

<script>
  (() => {
    const cl = document.getElementById('cursorLight');
    window.addEventListener('pointermove', (e) => {
      cl.style.setProperty('--mx', e.clientX + 'px');
      cl.style.setProperty('--my', e.clientY + 'px');
    });

    const c = document.getElementById('particles');
    const ctx = c.getContext('2d');
    let w, h, parts;
    const resize = () => {
      w = c.width = window.innerWidth * devicePixelRatio;
      h = c.height = window.innerHeight * devicePixelRatio;
      c.style.width = window.innerWidth + 'px';
      c.style.height = window.innerHeight + 'px';
      const N = 60;
      parts = Array.from({length: N}, () => ({
        x: Math.random()*w, y: Math.random()*h,
        vx: (Math.random()-.5)*0.15*devicePixelRatio,
        vy: (Math.random()-.5)*0.15*devicePixelRatio,
        r: Math.random()*1.4*devicePixelRatio + 0.4*devicePixelRatio,
        hue: Math.random() < 0.5 ? '91,212,255' : '92,123,255',
        a: 0.15 + Math.random()*0.4,
      }));
    };
    resize();
    window.addEventListener('resize', resize);
    const tick = () => {
      ctx.clearRect(0,0,w,h);
      for (const p of parts) {
        p.x += p.vx; p.y += p.vy;
        if (p.x < 0 || p.x > w) p.vx *= -1;
        if (p.y < 0 || p.y > h) p.vy *= -1;
        const grad = ctx.createRadialGradient(p.x,p.y,0,p.x,p.y,p.r*8);
        grad.addColorStop(0, `rgba(${p.hue},${p.a})`);
        grad.addColorStop(1, `rgba(${p.hue},0)`);
        ctx.fillStyle = grad;
        ctx.beginPath(); ctx.arc(p.x,p.y,p.r*8,0,Math.PI*2); ctx.fill();
      }
      requestAnimationFrame(tick);
    };
    tick();
  })();
</script>

<script>
  window.__TWEAKS_DEFAULTS = { accent: "cyan", density: "comfortable", glow: 1, particles: true, grid: true, monoData: true };
  window.__DASH_DATA = @json($bootData);
  window.__DASH_REFRESH_URL = "{{ url('/dashboard/data') }}";
  window.__DASH_SESSION_URL = "{{ url('/dashboard/sessions') }}";
  window.__DASH_CHAT_URL    = "{{ url('/dashboard/chat') }}";
  window.__DASH_VOICE_URL   = "{{ url('/dashboard/chat/voice') }}";
  window.__DASH_NEW_SESSION_URL = "{{ url('/dashboard/sessions/new') }}";
</script>

<script type="text/babel" src="{{ asset('dash-assets/src/lib.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/icons.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/widgets.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/screens-1.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/screens-2.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/screens-3.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/shell.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dashboard/tweaks-panel.jsx') }}"></script>
<script type="text/babel" src="{{ asset('dash-assets/src/app.jsx') }}"></script>

</body>
</html>
