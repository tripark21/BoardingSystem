<?php
$current    = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

global $conn;
$_navPending = 0;
if (isset($conn)) {
    try { $_navPending = (int) dbScalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'"); }
    catch (Exception $e) { $_navPending = 0; }
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🏠</div>
    <h2>BoardingEase</h2>
    <span>Management System</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Main</div>
    <a href="/boarding_system/admin/dashboard.php"
       class="nav-item <?= ($current==='dashboard.php' && $currentDir==='admin') ? 'active' : '' ?>">
      <span class="icon">📊</span> Dashboard
    </a>

    <div class="nav-section-label">Management</div>
    <a href="/boarding_system/admin/rooms.php"
       class="nav-item <?= ($current==='rooms.php' && $currentDir==='admin') ? 'active' : '' ?>">
      <span class="icon">🛏️</span> Rooms
    </a>
    <a href="/boarding_system/admin/tenants.php"
       class="nav-item <?= ($current==='tenants.php') ? 'active' : '' ?>">
      <span class="icon">👥</span> Tenants
    </a>
    <a href="/boarding_system/admin/messages.php"
       class="nav-item <?= ($current==='messages.php') ? 'active' : '' ?>"
       style="justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:12px;">
        <span class="icon">💬</span> Messages
      </span>
      <?php
      $_msgUnread = 0;
      if (isset($conn)) {
          try {
              $_msgUnread = (int) dbScalar($conn,
                  "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE",
                  [$_SESSION['user_id'] ?? 0]);
          } catch (Exception $e) { $_msgUnread = 0; }
      }
      if ($_msgUnread > 0): ?>
      <span style="background:var(--info);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;animation:navPulse 2s infinite;">
        <?= $_msgUnread ?>
      </span>
      <?php endif; ?>
    </a>
    <a href="/boarding_system/admin/bookings.php"
       class="nav-item <?= ($current==='bookings.php') ? 'active' : '' ?>"
       style="justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:12px;">
        <span class="icon">📋</span> Bookings
      </span>
      <?php if ($_navPending > 0): ?>
      <span style="background:var(--accent);color:#fff;font-size:10px;font-weight:800;padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;animation:navPulse 2s infinite;">
        <?= $_navPending ?>
      </span>
      <?php endif; ?>
    </a>
    <a href="/boarding_system/admin/bills.php"
       class="nav-item <?= ($current==='bills.php') ? 'active' : '' ?>">
      <span class="icon">📄</span> Bills &amp; Payments
    </a>

    <div class="nav-section-label">Account</div>
    <a href="/boarding_system/logout.php" class="nav-item">
      <span class="icon">🚪</span> Logout
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?></div>
        <div class="user-role">Administrator</div>
      </div>
    </div>
  </div>
</aside>
<style>
@keyframes navPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.75;transform:scale(1.1)} }
</style>
