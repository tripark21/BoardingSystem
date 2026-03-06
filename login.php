<?php
require_once 'config/database.php';
if (isLoggedIn()) {
  if (isAdmin()) header("Location: /boarding_system/admin/dashboard.php");
else header("Location: /boarding_system/tenant/rooms.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($conn, $_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        try {
            $user = dbOne($conn,
                "SELECT id, username, password, role FROM users WHERE username = ?",
                [$username]
            );
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role']     = $user['role'];
                header("Location: " . ($user['role'] === 'admin'
                    ? "/boarding_system/admin/dashboard.php"
                    : "/boarding_system/tenant/rooms.php"));
                exit;
            } else {
                $error = "Incorrect username or password. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in both fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sign In — BoardingEase</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,700;1,400&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
    :root{
      --orange:#E8650A; --orange-lt:#F47B2A; --orange-pale:#FEF0E6;
      --cream:#FDF6EE; --cream-dark:#F5E8D4;
      --brown:#3D2008; --brown-mid:#7A4818; --text-soft:#A07050;
      --white:#FFFFFF; --border:#EDE0CE;
    }
    body{
      font-family:'DM Sans',sans-serif;
      background:var(--cream); color:var(--brown);
      min-height:100vh; display:flex; overflow:hidden;
    }
    .left-panel{
      flex:1; background:linear-gradient(145deg, var(--orange) 0%, #B84A06 100%);
      position:relative; overflow:hidden;
      display:flex; flex-direction:column; justify-content:space-between;
      padding:48px 56px;
    }
    .left-panel::before{
      content:''; position:absolute; top:-120px; right:-120px;
      width:400px; height:400px; border-radius:50%;
      background:rgba(255,255,255,.07); pointer-events:none;
    }
    .left-panel::after{
      content:''; position:absolute; bottom:-80px; left:-80px;
      width:320px; height:320px; border-radius:50%;
      background:rgba(0,0,0,.08); pointer-events:none;
    }
    .lp-top{position:relative;z-index:1;}
    .lp-brand{display:flex;align-items:center;gap:12px;text-decoration:none;margin-bottom:60px;}
    .lp-brand-icon{width:44px;height:44px;border-radius:12px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:22px;border:1px solid rgba(255,255,255,.25);}
    .lp-brand-name{font-family:'Playfair Display',serif;font-size:22px;font-weight:700;color:#fff;}
    .lp-headline{font-family:'Playfair Display',serif;font-size:44px;font-weight:700;color:#fff;line-height:1.1;margin-bottom:18px;}
    .lp-headline em{font-style:italic;opacity:.85;}
    .lp-desc{font-size:16px;color:rgba(255,255,255,.75);line-height:1.8;max-width:360px;}
    .lp-features{position:relative;z-index:1;display:flex;flex-direction:column;gap:14px;margin-top:48px;}
    .lp-feat{display:flex;align-items:center;gap:14px;}
    .lp-feat-icon{width:40px;height:40px;border-radius:10px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
    .lp-feat-text{font-size:14px;color:rgba(255,255,255,.85);font-weight:500;}
    .lp-bottom{position:relative;z-index:1;}
    .lp-quote{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:14px;padding:20px 24px;}
    .lp-quote p{font-size:14px;font-style:italic;color:rgba(255,255,255,.85);line-height:1.7;margin-bottom:10px;}
    .lp-quote-author{font-size:12px;color:rgba(255,255,255,.55);font-weight:500;}
    .dots-pattern{position:absolute;top:50%;right:0;transform:translateY(-50%);width:80px;height:300px;background-image:radial-gradient(circle, rgba(255,255,255,.25) 1.5px, transparent 1.5px);background-size:14px 14px;pointer-events:none;}
    .right-panel{
      width:480px; flex-shrink:0; background:var(--white);
      display:flex; flex-direction:column; justify-content:center;
      padding:60px 52px; position:relative; overflow-y:auto;
    }
    .rp-back{display:inline-flex;align-items:center;gap:6px;font-size:13px;color:var(--text-soft);text-decoration:none;margin-bottom:40px;transition:color .2s;}
    .rp-back:hover{color:var(--orange);}
    .rp-welcome{font-size:13px;font-weight:600;color:var(--orange);letter-spacing:.5px;text-transform:uppercase;margin-bottom:8px;}
    .rp-title{font-family:'Playfair Display',serif;font-size:32px;font-weight:700;color:var(--brown);margin-bottom:6px;}
    .rp-sub{font-size:14px;color:var(--text-soft);margin-bottom:36px;}
    .role-toggle{display:grid;grid-template-columns:1fr 1fr;gap:8px;background:var(--cream);border-radius:12px;padding:5px;margin-bottom:28px;border:1.5px solid var(--cream-dark);}
    .role-btn{padding:10px;border-radius:8px;border:none;font-family:'DM Sans',sans-serif;font-size:13px;font-weight:500;cursor:pointer;transition:all .2s;background:transparent;color:var(--text-soft);display:flex;align-items:center;justify-content:center;gap:7px;}
    .role-btn.active{background:var(--white);color:var(--brown);font-weight:600;box-shadow:0 2px 8px rgba(74,44,10,.10);}
    .error-box{background:#fff5f0;border:1.5px solid rgba(232,101,10,.3);border-radius:10px;padding:12px 16px;margin-bottom:22px;display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#8B3A0A;animation:shakeIn .4s ease;}
    .error-box .err-icon{font-size:16px;flex-shrink:0;margin-top:1px;}
    @keyframes shakeIn{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-5px)}40%,80%{transform:translateX(5px)}}
    .form-grp{margin-bottom:18px;}
    .form-lbl{display:block;font-size:12.5px;font-weight:600;color:var(--brown);margin-bottom:7px;}
    .form-inp-wrap{position:relative;}
    .form-inp-icon{position:absolute;left:14px;top:50%;transform:translateY(-50%);font-size:15px;color:var(--text-soft);}
    .form-inp{width:100%;padding:11px 14px 11px 42px;border:1.5px solid var(--border);border-radius:10px;font-family:'DM Sans',sans-serif;font-size:14px;color:var(--brown);background:var(--cream);outline:none;transition:all .2s;}
    .form-inp:focus{border-color:var(--orange);background:var(--white);box-shadow:0 0 0 3px rgba(232,101,10,.10);}
    .form-inp::placeholder{color:var(--text-soft);}
    .eye-btn{position:absolute;right:14px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;color:var(--text-soft);transition:color .2s;padding:2px;}
    .eye-btn:hover{color:var(--orange);}
    .form-row-extra{display:flex;align-items:center;justify-content:space-between;margin:6px 0 24px;font-size:13px;}
    .remember-wrap{display:flex;align-items:center;gap:7px;cursor:pointer;color:var(--brown-mid);}
    .remember-wrap input[type=checkbox]{width:16px;height:16px;accent-color:var(--orange);cursor:pointer;}
    .submit-btn{width:100%;padding:13px;border-radius:11px;border:none;cursor:pointer;background:var(--orange);color:#fff;font-family:'DM Sans',sans-serif;font-size:15px;font-weight:600;box-shadow:0 6px 20px rgba(232,101,10,.36);transition:all .25s;display:flex;align-items:center;justify-content:center;gap:8px;}
    .submit-btn:hover{background:var(--orange-lt);transform:translateY(-2px);box-shadow:0 10px 28px rgba(232,101,10,.46);}
    .register-link{text-align:center;margin-top:16px;font-size:13px;color:var(--text-soft);}
    .register-link a{color:var(--orange);font-weight:600;text-decoration:none;}
    .register-link a:hover{text-decoration:underline;}
    @media(max-width:820px){
      .left-panel{display:none;}
      .right-panel{width:100%;padding:48px 32px;}
    }
  </style>
</head>
<body>

<div class="left-panel">
  <div class="lp-top">
    <a href="index.php" class="lp-brand">
      <div class="lp-brand-icon">🏠</div>
      <span class="lp-brand-name">BoardingEase</span>
    </a>
    <h1 class="lp-headline">Welcome<br><em>back home.</em></h1>
    <p class="lp-desc">Sign in to your boarding house account. Manage rooms, tenants, and bills — or check your stay and payments as a tenant.</p>
    <div class="lp-features">
      <div class="lp-feat"><div class="lp-feat-icon">🛏️</div><div class="lp-feat-text">Manage rooms &amp; availability</div></div>
      <div class="lp-feat"><div class="lp-feat-icon">💳</div><div class="lp-feat-text">Track bills &amp; payments</div></div>
      <div class="lp-feat"><div class="lp-feat-icon">📋</div><div class="lp-feat-text">Book &amp; manage your stay</div></div>
      <div class="lp-feat"><div class="lp-feat-icon">📊</div><div class="lp-feat-text">Full dashboard overview</div></div>
    </div>
  </div>
  <div class="lp-bottom">
    <div class="lp-quote">
      <p>"BoardingEase made managing my boarding house so much easier — everything is in one place."</p>
      <div class="lp-quote-author">— A Happy Landlord 🏠</div>
    </div>
  </div>
  <div class="dots-pattern"></div>
</div>

<div class="right-panel">
  <a href="index.php" class="rp-back">← Back to home</a>
  <div class="rp-welcome">Welcome Back</div>
  <h2 class="rp-title">Sign in to your account</h2>
  <p class="rp-sub">Choose your role and enter your credentials below.</p>

  <div class="role-toggle" id="roleToggle">
    <button type="button" class="role-btn active" id="btnAdmin" onclick="setRole('admin')">🔑 Admin</button>
    <button type="button" class="role-btn" id="btnTenant" onclick="setRole('tenant')">🏡 Tenant</button>
  </div>

  <?php if ($error): ?>
  <div class="error-box">
    <span class="err-icon">⚠️</span>
    <div><?= htmlspecialchars($error) ?></div>
  </div>
  <?php endif; ?>

  <form method="POST" action="" id="loginForm">
    <div class="form-grp">
      <label class="form-lbl">Username</label>
      <div class="form-inp-wrap">
        <span class="form-inp-icon">👤</span>
        <input type="text" name="username" id="usernameInput" class="form-inp"
               placeholder="Enter your username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
               required autocomplete="username" autofocus>
      </div>
    </div>
    <div class="form-grp">
      <label class="form-lbl">Password</label>
      <div class="form-inp-wrap">
        <span class="form-inp-icon">🔒</span>
        <input type="password" name="password" id="passInput" class="form-inp"
               placeholder="Enter your password"
               required autocomplete="current-password">
        <button type="button" class="eye-btn" id="eyeBtn" onclick="togglePass()">👁️</button>
      </div>
    </div>
    <div class="form-row-extra">
      <label class="remember-wrap">
        <input type="checkbox" name="remember"> Remember me
      </label>
    </div>
    <button type="submit" class="submit-btn">
      <span>Sign In</span> <span>→</span>
    </button>
  </form>

  <div class="register-link">
    No account yet? <a href="register.php">Register here →</a>
  </div>

</div>

<script>
  let passVisible = false;
  function togglePass(){
    passVisible = !passVisible;
    const inp = document.getElementById('passInput');
    const btn = document.getElementById('eyeBtn');
    inp.type = passVisible ? 'text' : 'password';
    btn.textContent = passVisible ? '🙈' : '👁️';
  }
  function setRole(role){
    document.getElementById('btnAdmin').classList.toggle('active', role==='admin');
    document.getElementById('btnTenant').classList.toggle('active', role==='tenant');
    document.getElementById('usernameInput').placeholder = role==='admin' ? 'Admin username' : 'Your tenant username';
  }
</script>
</body>
</html>