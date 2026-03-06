<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    header("Location: " . (isAdmin() ? "/boarding_system/admin/dashboard.php" : "/boarding_system/tenant/rooms.php"));
    exit;
}

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $role     = 'tenant'; // Only tenants can self-register

    if (!$username || !$password) {
        $msg = "Please fill in all fields."; $msgType = 'warning';
    } elseif (strlen($username) < 3) {
        $msg = "Username must be at least 3 characters."; $msgType = 'warning';
    } elseif (strlen($password) < 8) {
        $msg = "Password must be at least 8 characters."; $msgType = 'warning';
    } elseif ($password !== $confirm) {
        $msg = "Passwords do not match."; $msgType = 'warning';
    } else {
        try {
            // Check for duplicate username with a friendly message
            $exists = dbScalar($conn, "SELECT COUNT(*) FROM users WHERE username = ?", [$username]);
            if ($exists > 0) {
                $msg = "The username \"" . htmlspecialchars($username) . "\" is already taken. Please choose a different one.";
                $msgType = 'warning';
            } else {
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                dbExec($conn,
                    "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
                    [$username, $hashed, $role]
                );
                $msg = "✅ Account created! You can now <a href='login.php' style='font-weight:700;'>sign in here →</a>";
                $msgType = 'success';
            }
        } catch (PDOException $e) {
            $msg = "A system error occurred. Please try again later.";
            $msgType = 'danger';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Register — BoardingEase</title>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    body{font-family:'DM Sans',sans-serif;background:linear-gradient(135deg,#1a3c5e 0%,#0f2442 100%);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;}
    .page-container{display:flex;gap:40px;align-items:center;width:100%;max-width:1000px;}
    .register-banner{flex:1;color:#fff;}
    .register-banner h1{font-size:42px;font-weight:800;margin-bottom:16px;letter-spacing:-0.5px;}
    .register-banner p{font-size:16px;color:rgba(255,255,255,0.75);line-height:1.7;margin-bottom:32px;}
    .features-list{display:flex;flex-direction:column;gap:14px;}
    .feature-item{display:flex;align-items:center;gap:12px;font-size:14px;color:rgba(255,255,255,0.8);}
    .feature-icon{font-size:18px;width:32px;height:32px;background:rgba(255,255,255,0.12);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
    .card{background:#fff;border-radius:20px;padding:44px;width:420px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,0.3);}
    .logo{font-size:48px;text-align:center;margin-bottom:12px;}
    h2{font-size:26px;font-weight:800;text-align:center;color:#1a2332;margin-bottom:6px;letter-spacing:-0.5px;}
    .sub{font-size:13px;color:#6b7a8d;text-align:center;margin-bottom:28px;}
    .alert{padding:14px 16px;border-radius:12px;font-size:13px;margin-bottom:20px;border:1px solid;animation:slideDown 0.3s ease;line-height:1.5;}
    @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
    .alert-success{background:#e6f7f0;color:#1a6b47;border-color:#b2edd0;}
    .alert-danger{background:#fdecea;color:#c53030;border-color:#f5c6cb;}
    .alert-warning{background:#fdf3e0;color:#92600a;border-color:#f5d993;}
    .form-group{margin-bottom:18px;}
    label{display:block;font-size:12.5px;font-weight:600;color:#1a2332;margin-bottom:8px;}
    .input-wrap{position:relative;}
    .input-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;pointer-events:none;}
    input{width:100%;padding:11px 14px 11px 38px;border:1.5px solid #dde3ed;border-radius:10px;font-family:inherit;font-size:14px;color:#1a2332;outline:none;transition:all 0.2s;background:#fff;}
    input::placeholder{color:#9ca3af;}
    input:focus{border-color:#1a3c5e;box-shadow:0 0 0 3px rgba(26,60,94,0.1);}
    .password-wrap{position:relative;}
    .eye-btn{position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:15px;color:#6b7a8d;}
    .strength-bar{height:4px;border-radius:2px;margin-top:6px;background:#e5e7eb;overflow:hidden;}
    .strength-fill{height:100%;border-radius:2px;transition:all 0.3s;width:0;}
    .strength-text{font-size:11px;margin-top:4px;color:#6b7a8d;}
    .account-type-display{background:linear-gradient(135deg,#e8f0f9,#f0f5fb);padding:14px 16px;border-radius:10px;margin-bottom:18px;border:1px solid #dde3ed;}
    .account-type-label{font-size:12px;font-weight:600;color:#6b7a8d;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;}
    .account-type-value{font-size:15px;color:#1a3c5e;font-weight:700;}
    .btn{width:100%;padding:14px;border-radius:10px;border:none;background:linear-gradient(135deg,#1a3c5e,#2a5580);color:#fff;font-family:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:all 0.3s;margin-top:8px;box-shadow:0 4px 12px rgba(26,60,94,0.25);}
    .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(26,60,94,0.35);}
    .login-link{text-align:center;margin-top:24px;font-size:13px;color:#6b7a8d;}
    .login-link a{color:#1a3c5e;font-weight:700;text-decoration:none;}
    .login-link a:hover{text-decoration:underline;}
    .note{background:linear-gradient(135deg,#fdf3e0,#fef8f0);border:1px solid #f5d993;border-radius:10px;padding:12px 14px;font-size:12.5px;color:#92600a;margin-bottom:20px;display:flex;align-items:flex-start;gap:8px;}
    @media(max-width:768px){.register-banner{display:none;}.card{width:100%;padding:32px;}}
  </style>
</head>
<body>
<div class="page-container">
  <div class="register-banner">
    <h1>Join BoardingEase</h1>
    <p>Create your tenant account and find your perfect boarding house. Browse available rooms and manage your stay.</p>
    <div class="features-list">
      <div class="feature-item"><span class="feature-icon">🛏️</span><span>Browse available rooms</span></div>
      <div class="feature-item"><span class="feature-icon">📋</span><span>Easy booking requests</span></div>
      <div class="feature-item"><span class="feature-icon">💳</span><span>Track bills &amp; payments</span></div>
      <div class="feature-item"><span class="feature-icon">📊</span><span>Full tenant dashboard</span></div>
    </div>
  </div>

  <div class="card">
    <div class="logo">🏠</div>
    <h2>Create Account</h2>
    <p class="sub">Register as a tenant to get started</p>

    <div class="note">
      <span>✨</span>
      <span><strong>Tenant Registration</strong> — Create your account to access the full tenant portal and manage your booking.</span>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label>Username * <span style="font-size:11px;font-weight:400;color:#9ca3af;">(min. 3 characters)</span></label>
        <div class="input-wrap">
          <span class="input-icon">👤</span>
          <input type="text" name="username" placeholder="Choose a unique username" minlength="3" required
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Password * <span style="font-size:11px;font-weight:400;color:#9ca3af;">(min. 8 characters)</span></label>
        <div class="input-wrap password-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" name="password" id="passwordInput" placeholder="At least 8 characters" minlength="8" required oninput="checkStrength(this.value)">
          <button type="button" class="eye-btn" onclick="togglePass('passwordInput', this)">👁️</button>
        </div>
        <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        <div class="strength-text" id="strengthText"></div>
      </div>
      <div class="form-group">
        <label>Confirm Password *</label>
        <div class="input-wrap password-wrap">
          <span class="input-icon">🔒</span>
          <input type="password" name="confirm_password" id="confirmInput" placeholder="Re-enter your password" required>
          <button type="button" class="eye-btn" onclick="togglePass('confirmInput', this)">👁️</button>
        </div>
      </div>
      <div class="account-type-display">
        <div class="account-type-label">Account Type</div>
        <div class="account-type-value">🏡 Tenant Account</div>
      </div>
      <button type="submit" class="btn">Create My Account →</button>
    </form>

    <div class="login-link">
      Already have an account? <a href="login.php">Sign in →</a>
    </div>
  </div>
</div>

<script>
function togglePass(inputId, btn) {
  const inp = document.getElementById(inputId);
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  btn.textContent = show ? '🙈' : '👁️';
}

function checkStrength(val) {
  const fill = document.getElementById('strengthFill');
  const text = document.getElementById('strengthText');
  let score = 0;
  if (val.length >= 8)  score++;
  if (val.length >= 12) score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  if (/[^A-Za-z0-9]/.test(val)) score++;

  const levels = [
    { pct: '0%',   color: '#e5e7eb', label: '' },
    { pct: '25%',  color: '#ef4444', label: 'Weak' },
    { pct: '50%',  color: '#f97316', label: 'Fair' },
    { pct: '75%',  color: '#eab308', label: 'Good' },
    { pct: '100%', color: '#22c55e', label: 'Strong' },
  ];
  const lvl = levels[Math.min(score, 4)];
  fill.style.width = lvl.pct;
  fill.style.background = lvl.color;
  text.textContent = lvl.label ? 'Strength: ' + lvl.label : '';
  text.style.color = lvl.color;
}
</script>
</body>
</html>
