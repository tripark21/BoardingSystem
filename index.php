<?php
require_once 'config/database.php';
if (isLoggedIn()) {
    if (isAdmin()) header("Location: /boarding_system/admin/dashboard.php");
    else header("Location: /boarding_system/tenant/rooms.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BoardingEase — Feel at Home</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,500&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
    :root {
      --orange:       #E8650A;
      --orange-lt:    #F47B2A;
      --orange-pale:  #FEF0E6;
      --cream:        #FDF6EE;
      --cream-dark:   #F5E8D4;
      --brown:        #3D2008;
      --brown-mid:    #7A4818;
      --text-soft:    #A07050;
      --white:        #FFFFFF;
      --shadow-warm:  0 12px 48px rgba(232,101,10,0.18);
    }
    html { scroll-behavior: smooth; }
    body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--brown); overflow-x: hidden; }

    /* ── NAV ── */
    nav {
      position: fixed; top: 0; inset-inline: 0; z-index: 100;
      height: 68px; padding: 0 64px;
      display: flex; align-items: center; justify-content: space-between;
      background: rgba(253,246,238,0.85); backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(232,101,10,0.10);
      transition: box-shadow .3s;
    }
    nav.scrolled { box-shadow: 0 4px 28px rgba(74,44,10,0.10); }
    .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .brand-icon {
      width: 40px; height: 40px; border-radius: 12px; background: var(--orange);
      display: flex; align-items: center; justify-content: center; font-size: 19px;
      box-shadow: 0 4px 14px rgba(232,101,10,0.35);
    }
    .brand-name { font-family:'Playfair Display',serif; font-size:20px; font-weight:700; color:var(--brown); }
    .brand-name b { color:var(--orange); font-weight:700; }
    .nav-right { display:flex; gap:10px; align-items:center; }
    .nbtn { padding:8px 22px; border-radius:8px; font-size:14px; font-weight:500; text-decoration:none; font-family:'DM Sans',sans-serif; transition:all .2s; cursor:pointer; }
    .nbtn-ghost { color:var(--brown-mid); border:1.5px solid var(--cream-dark); background:transparent; }
    .nbtn-ghost:hover { background:var(--cream-dark); }
    .nbtn-fill { background:var(--orange); color:#fff; border:none; box-shadow:0 4px 14px rgba(232,101,10,.32); }
    .nbtn-fill:hover { background:var(--orange-lt); transform:translateY(-1px); box-shadow:0 6px 20px rgba(232,101,10,.42); }

    /* ── HERO ── */
    .hero {
      display:grid; grid-template-columns:1fr 1fr; min-height:100vh; padding-top:68px;
      position:relative; overflow:hidden;
    }
    .hero-bg-blob {
      position:absolute; border-radius:50%; pointer-events:none;
    }
    .blob-1 { width:700px; height:700px; top:-200px; right:-150px;
      background:radial-gradient(circle, rgba(232,101,10,.09) 0%, transparent 70%); }
    .blob-2 { width:500px; height:500px; bottom:-100px; left:10%;
      background:radial-gradient(circle, rgba(245,232,212,.9) 0%, transparent 70%); }

    .hero-left {
      display:flex; flex-direction:column; justify-content:center;
      padding: 80px 56px 80px 80px; position:relative; z-index:2;
    }
    .htag {
      display:inline-flex; align-items:center; gap:8px;
      padding:6px 15px; border-radius:30px; width:fit-content; margin-bottom:28px;
      background:var(--orange-pale); border:1px solid rgba(232,101,10,.22);
      color:var(--orange); font-size:11.5px; font-weight:600; letter-spacing:.8px; text-transform:uppercase;
      animation: fadeUp .6s ease both;
    }
    .htag .pulse { width:7px; height:7px; border-radius:50%; background:var(--orange); animation:pulse 2s infinite; }
    .hero-h1 {
      font-family:'Playfair Display',serif; font-size:60px; line-height:1.08; font-weight:700;
      color:var(--brown); margin-bottom:22px;
      animation: fadeUp .7s .1s ease both;
    }
    .hero-h1 em { font-style:italic; color:var(--orange); }
    .hero-p {
      font-size:17px; color:var(--text-soft); line-height:1.8; max-width:430px; margin-bottom:42px;
      animation: fadeUp .7s .2s ease both;
    }
    .hero-btns { display:flex; gap:14px; align-items:center; animation: fadeUp .7s .3s ease both; }
    .hbtn-main {
      padding:15px 36px; border-radius:12px; background:var(--orange); color:#fff;
      font-family:'DM Sans',sans-serif; font-size:15px; font-weight:600; text-decoration:none;
      box-shadow:0 6px 24px rgba(232,101,10,.40); display:inline-flex; align-items:center; gap:8px;
      transition:all .25s;
    }
    .hbtn-main:hover { background:var(--orange-lt); transform:translateY(-2px); box-shadow:0 10px 32px rgba(232,101,10,.50); }
    .hbtn-link {
      font-size:14px; font-weight:500; color:var(--brown-mid); text-decoration:none;
      display:inline-flex; align-items:center; gap:6px; transition:color .2s;
    }
    .hbtn-link:hover { color:var(--orange); }
    .hbtn-link .arr { transition:transform .2s; }
    .hbtn-link:hover .arr { transform:translateX(4px); }

    .hero-stats {
      display:flex; gap:36px; margin-top:56px;
      padding-top:32px; border-top:1.5px solid var(--cream-dark);
      animation: fadeUp .7s .4s ease both;
    }
    .hstat-val { font-family:'Playfair Display',serif; font-size:30px; font-weight:700; color:var(--brown); line-height:1; }
    .hstat-lbl { font-size:12px; color:var(--text-soft); margin-top:4px; font-weight:500; }
    .hstat-div { width:1px; background:var(--cream-dark); }

    /* ── HERO RIGHT ── */
    .hero-right {
      background:linear-gradient(140deg, var(--orange-pale) 0%, var(--cream-dark) 100%);
      display:flex; align-items:center; justify-content:center;
      position:relative; overflow:hidden;
    }

    /* CSS house */
    .house-wrap { position:relative; width:320px; height:360px; animation:floatY 5s ease-in-out infinite alternate; }
    .house-wrap::after {
      content:''; position:absolute; bottom:-10px; left:50%; transform:translateX(-50%);
      width:260px; height:14px; border-radius:50%;
      background:rgba(74,44,10,.10); filter:blur(6px);
    }
    .h-body {
      position:absolute; bottom:38px; left:50%; transform:translateX(-50%);
      width:210px; height:160px; background:var(--white); border-radius:6px 6px 4px 4px;
      box-shadow:0 10px 40px rgba(74,44,10,.14);
    }
    .h-roof {
      position:absolute; bottom:192px; left:50%; transform:translateX(-50%);
      width:0; height:0;
      border-left:122px solid transparent; border-right:122px solid transparent;
      border-bottom:86px solid var(--orange);
      filter:drop-shadow(0 -4px 10px rgba(232,101,10,.22));
    }
    .h-roof-inner {
      position:absolute; bottom:188px; left:50%; transform:translateX(-50%);
      width:0; height:0;
      border-left:112px solid transparent; border-right:112px solid transparent;
      border-bottom:76px solid var(--orange-lt);
    }
    .h-chimney {
      position:absolute; bottom:268px; right:100px;
      width:24px; height:42px; background:var(--brown-mid); border-radius:3px 3px 0 0;
    }
    .h-smoke { position:absolute; bottom:310px; right:100px; }
    .h-smoke span {
      position:absolute; width:13px; height:13px; border-radius:50%;
      background:rgba(255,255,255,.75); animation:smokeUp 2.5s ease-in infinite;
    }
    .h-smoke span:nth-child(2) { animation-delay:.85s; left:-3px; }
    .h-smoke span:nth-child(3) { animation-delay:1.7s; left:5px; }

    .h-door {
      position:absolute; bottom:38px; left:50%; transform:translateX(-50%);
      width:46px; height:68px; background:var(--brown); border-radius:23px 23px 0 0;
    }
    .h-door::after { content:''; position:absolute; top:26px; right:8px; width:6px; height:6px; border-radius:50%; background:var(--orange); }
    .h-win {
      position:absolute; width:42px; height:42px;
      background:linear-gradient(135deg,#fff9f0,#fdecd8);
      border-radius:5px; border:3px solid var(--cream-dark);
    }
    .h-win::before,.h-win::after { content:''; position:absolute; background:var(--cream-dark); }
    .h-win::before { left:50%; top:0; bottom:0; width:2px; transform:translateX(-50%); }
    .h-win::after  { top:50%; left:0; right:0; height:2px; transform:translateY(-50%); }
    .wl { top:60px; left:26px; animation:winGlow 4s ease-in-out infinite alternate; }
    .wr { top:60px; right:26px; animation:winGlow 4s 2s ease-in-out infinite alternate; }

    .h-path {
      position:absolute; bottom:36px; left:50%; transform:translateX(-50%);
      width:50px; height:38px;
      background:linear-gradient(180deg, var(--cream-dark), var(--orange-pale));
      clip-path:polygon(18% 0%,82% 0%,100% 100%,0% 100%);
    }
    .tree { position:absolute; }
    .tr-trunk { width:10px; height:26px; background:var(--brown-mid); border-radius:3px; margin:0 auto; }
    .tr-top  { width:0;height:0; border-left:22px solid transparent; border-right:22px solid transparent; border-bottom:40px solid #6BAA3A; position:relative;top:4px;left:-6px; }
    .tr-top2 { width:0;height:0; border-left:17px solid transparent; border-right:17px solid transparent; border-bottom:32px solid #7DC44A; position:relative;top:-20px;left:-1px; }
    .t1 { bottom:34px; left:22px; }
    .t2 { bottom:34px; right:24px; transform:scale(.83); }

    /* floating badges */
    .fbadge {
      position:absolute; background:var(--white); border-radius:14px; padding:11px 16px;
      box-shadow:0 8px 28px rgba(74,44,10,.14); display:flex; align-items:center; gap:9px;
      font-size:13px; font-weight:600; color:var(--brown); animation:fbFloat 3s ease-in-out infinite alternate;
    }
    .fbadge-icon { font-size:20px; }
    .fbadge-sub { font-size:11px; color:var(--text-soft); font-weight:400; }
    .fb1 { top:72px; right:40px; animation-delay:0s; }
    .fb2 { bottom:110px; left:28px; animation-delay:1.5s; }
    .fb3 { top:190px; right:24px; animation-delay:.9s; }

    /* ── FEATURES ── */
    .features { background:var(--white); padding:100px 80px; }
    .eyebrow { text-align:center; font-size:11.5px; font-weight:600; letter-spacing:2px; text-transform:uppercase; color:var(--orange); margin-bottom:10px; }
    .sec-title { font-family:'Playfair Display',serif; font-size:42px; font-weight:700; color:var(--brown); text-align:center; line-height:1.2; margin-bottom:10px; }
    .sec-sub { text-align:center; font-size:16px; color:var(--text-soft); max-width:480px; margin:0 auto 60px; line-height:1.7; }
    .feat-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:22px; max-width:1020px; margin:0 auto; }
    .fcard {
      padding:32px 28px; border-radius:18px; border:1.5px solid var(--cream-dark);
      background:var(--cream); position:relative; overflow:hidden; transition:all .25s;
    }
    .fcard::after { content:''; position:absolute; top:0;left:0;right:0; height:3px; background:linear-gradient(90deg,var(--orange),var(--orange-lt)); transform:scaleX(0); transform-origin:left; transition:transform .3s; }
    .fcard:hover { border-color:transparent; box-shadow:var(--shadow-warm); transform:translateY(-4px); }
    .fcard:hover::after { transform:scaleX(1); }
    .fcard-icon { width:50px;height:50px; border-radius:14px; background:var(--orange-pale); display:flex;align-items:center;justify-content:center; font-size:23px; margin-bottom:18px; transition:background .2s; }
    .fcard:hover .fcard-icon { background:rgba(232,101,10,.16); }
    .fcard h3 { font-size:15.5px; font-weight:600; color:var(--brown); margin-bottom:8px; }
    .fcard p  { font-size:13.5px; color:var(--text-soft); line-height:1.7; }

    /* ── ROLES ── */
    .roles { background:linear-gradient(135deg, var(--cream-dark) 0%, var(--orange-pale) 100%); padding:100px 80px; }
    .roles-grid { display:grid; grid-template-columns:1fr 1fr; gap:28px; max-width:840px; margin:0 auto; }
    .rcard {
      background:var(--white); border-radius:22px; padding:44px 36px; text-align:center;
      box-shadow:0 4px 32px rgba(74,44,10,.07); border:2px solid transparent; transition:all .28s;
    }
    .rcard:hover { border-color:var(--orange); box-shadow:var(--shadow-warm); transform:translateY(-6px); }
    .rcard-emoji { font-size:52px; display:block; margin-bottom:18px; }
    .rcard h3 { font-family:'Playfair Display',serif; font-size:22px; font-weight:700; color:var(--brown); margin-bottom:10px; }
    .rcard p  { font-size:14px; color:var(--text-soft); line-height:1.7; margin-bottom:24px; }
    .rcard-list { list-style:none; text-align:left; margin-bottom:28px; display:flex;flex-direction:column;gap:9px; }
    .rcard-list li { font-size:13px; color:var(--brown-mid); display:flex;align-items:center;gap:8px; }
    .rcard-list li::before { content:'✓'; color:var(--orange); font-weight:700; flex-shrink:0; }
    .rbtn-fill {
      display:block;width:100%;padding:13px;border-radius:10px;
      background:var(--orange);color:#fff;font-family:'DM Sans',sans-serif;
      font-size:14px;font-weight:600;text-decoration:none;text-align:center;
      box-shadow:0 4px 14px rgba(232,101,10,.30); transition:all .2s;
    }
    .rbtn-fill:hover { background:var(--orange-lt); transform:translateY(-1px); }
    .rbtn-out {
      display:block;width:100%;padding:12px;border-radius:10px;
      background:transparent;color:var(--orange);border:2px solid var(--orange);
      font-family:'DM Sans',sans-serif;font-size:14px;font-weight:600;
      text-decoration:none;text-align:center; transition:all .2s;
    }
    .rbtn-out:hover { background:var(--orange-pale); }

    footer { background:var(--brown); color:rgba(253,246,238,.5); text-align:center; padding:28px; font-size:13px; }
    footer b { color:var(--orange); }

    /* ── KEYFRAMES ── */
    @keyframes fadeUp { from{opacity:0;transform:translateY(22px)} to{opacity:1;transform:translateY(0)} }
    @keyframes floatY { from{transform:translateY(0)} to{transform:translateY(-16px)} }
    @keyframes fbFloat { from{transform:translateY(0) rotate(-1deg)} to{transform:translateY(-10px) rotate(1deg)} }
    @keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(.75)} }
    @keyframes smokeUp { 0%{opacity:.9;transform:translateY(0) scale(.8)} 100%{opacity:0;transform:translateY(-38px) scale(1.7)} }
    @keyframes winGlow { 0%{background:linear-gradient(135deg,#fff9f0,#fdecd8)} 100%{background:linear-gradient(135deg,#fff3de,#ffe9bc);box-shadow:0 0 12px rgba(232,101,10,.22)} }

    @media(max-width:900px){
      .hero{grid-template-columns:1fr}
      .hero-right{display:none}
      .hero-left{padding:80px 32px}
      .hero-h1{font-size:42px}
      nav{padding:0 24px}
      .features,.roles{padding:70px 28px}
      .feat-grid{grid-template-columns:1fr 1fr}
      .roles-grid{grid-template-columns:1fr;max-width:420px}
    }
    @media(max-width:600px){
      .feat-grid{grid-template-columns:1fr}
      .hero-h1{font-size:34px}
    }
  </style>
</head>
<body>

<nav id="topnav">
  <a href="index.php" class="brand">
    <div class="brand-icon">🏠</div>
    <span class="brand-name">Boarding<b>Ease</b></span>
  </a>
  <div class="nav-right">
    <a href="#features" class="nbtn nbtn-ghost">Features</a>
    <a href="login.php" class="nbtn nbtn-fill">Sign In →</a>
  </div>
</nav>

<!-- ── HERO ── -->
<section class="hero">
  <div class="hero-bg-blob blob-1"></div>
  <div class="hero-bg-blob blob-2"></div>

  <div class="hero-left">
    <div class="htag">
      <span class="pulse"></span>
      General Boarding House Management
    </div>
    <h1 class="hero-h1">Your <em>home away</em><br>from home,<br>managed with care.</h1>
    <p class="hero-p">BoardingEase helps landlords manage rooms and payments, and lets tenants book and track their bills — all in one warm, friendly place.</p>
    <div class="hero-btns">
      <a href="login.php" class="hbtn-main">Get Started <span>→</span></a>
      <a href="#features" class="hbtn-link">See Features <span class="arr">↓</span></a>
    </div>
    <div class="hero-stats">
      <div>
        <div class="hstat-val">2</div>
        <div class="hstat-lbl">User Roles</div>
      </div>
      <div class="hstat-div"></div>
      <div>
        <div class="hstat-val">100%</div>
        <div class="hstat-lbl">Easy to Use</div>
      </div>
      <div class="hstat-div"></div>
      <div>
        <div class="hstat-val">Free</div>
        <div class="hstat-lbl">Open System</div>
      </div>
    </div>
  </div>

  <div class="hero-right">
    <div class="house-wrap">
      <div class="h-chimney"></div>
      <div class="h-smoke"><span></span><span></span><span></span></div>
      <div class="h-roof"></div>
      <div class="h-roof-inner"></div>
      <div class="h-body">
        <div class="h-win wl"></div>
        <div class="h-win wr"></div>
        <div class="h-door"></div>
      </div>
      <div class="h-path"></div>
      <div class="tree t1"><div class="tr-top"></div><div class="tr-top2"></div><div class="tr-trunk"></div></div>
      <div class="tree t2"><div class="tr-top"></div><div class="tr-top2"></div><div class="tr-trunk"></div></div>
    </div>

    <div class="fbadge fb1">
      <span class="fbadge-icon">🛏️</span>
      <div><div>Room Available</div><div class="fbadge-sub">View & Book Now</div></div>
    </div>
    <div class="fbadge fb2">
      <span class="fbadge-icon">✅</span>
      <div><div>Bill Paid!</div><div class="fbadge-sub">₱3,500 — Monthly Rent</div></div>
    </div>
    <div class="fbadge fb3">
      <span class="fbadge-icon">👥</span>
      <div><div>New Tenant</div><div class="fbadge-sub">Booking Approved</div></div>
    </div>
  </div>
</section>

<!-- ── FEATURES ── -->
<section class="features" id="features">
  <p class="eyebrow">What's inside</p>
  <h2 class="sec-title">Everything you need to run<br>your boarding house</h2>
  <p class="sec-sub">A complete system built for general boarding houses — no complexity, just what matters.</p>
  <div class="feat-grid">
    <div class="fcard">
      <div class="fcard-icon">🛏️</div>
      <h3>Room Management</h3>
      <p>Add, update, and track all your rooms. See which are available, occupied, or under maintenance at a glance.</p>
    </div>
    <div class="fcard">
      <div class="fcard-icon">👥</div>
      <h3>Tenant Records</h3>
      <p>Keep full records of all tenants — rooms, move-in dates, contacts, and account credentials.</p>
    </div>
    <div class="fcard">
      <div class="fcard-icon">💳</div>
      <h3>Billing & Payments</h3>
      <p>Generate monthly bills, track who's paid and who hasn't, and keep your cash flow organized.</p>
    </div>
    <div class="fcard">
      <div class="fcard-icon">📋</div>
      <h3>Booking Requests</h3>
      <p>Tenants can browse available rooms and request online. Admin approves or rejects — simple as that.</p>
    </div>
    <div class="fcard">
      <div class="fcard-icon">📊</div>
      <h3>Admin Dashboard</h3>
      <p>See occupancy rate, total tenants, pending bills, and monthly revenue all in one view.</p>
    </div>
    <div class="fcard">
      <div class="fcard-icon">🔒</div>
      <h3>Secure & Separate</h3>
      <p>Admins and tenants each have their own portal. Role-based access keeps everything safe and organized.</p>
    </div>
  </div>
</section>

<!-- ── ROLES ── -->
<section class="roles" id="how-it-works">
  <p class="eyebrow">Two portals</p>
  <h2 class="sec-title">Sign in as Admin or Tenant</h2>
  <p class="sec-sub">Each role gets a tailored experience built for exactly what they need to do.</p>
  <div class="roles-grid">
    <div class="rcard">
      <span class="rcard-emoji">🔑</span>
      <h3>Admin Portal</h3>
      <p>For the landlord or property manager. Full control over the entire boarding house.</p>
      <ul class="rcard-list">
        <li>Manage all rooms &amp; pricing</li>
        <li>Add &amp; oversee tenants</li>
        <li>Generate &amp; track bills</li>
        <li>Approve booking requests</li>
        <li>Dashboard with full overview</li>
      </ul>
      <a href="login.php" class="rbtn-fill">Login as Admin →</a>
    </div>
    <div class="rcard">
      <span class="rcard-emoji">🏡</span>
      <h3>Tenant Portal</h3>
      <p>For boarders and renters. A simple, friendly portal to manage your stay.</p>
      <ul class="rcard-list">
        <li>Browse available rooms</li>
        <li>Submit booking requests</li>
        <li>View current room &amp; status</li>
        <li>Check bills &amp; payment history</li>
        <li>See upcoming due dates</li>
      </ul>
      <a href="login.php" class="rbtn-out">Login as Tenant →</a>
    </div>
  </div>
</section>

<footer>
  <p>© <?= date('Y') ?> <b>BoardingEase</b> — Built with care for boarding house owners &amp; tenants.</p>
</footer>

<script>
  const nav = document.getElementById('topnav');
  window.addEventListener('scroll', () => nav.classList.toggle('scrolled', scrollY > 20));
</script>
</body>
</html>
