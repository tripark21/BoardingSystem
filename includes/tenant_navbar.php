<?php
$current    = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

// Live unpaid bill count for badge
global $conn;
$_unpaidBills = 0;
if (isset($conn) && isset($_SESSION['user_id'])) {
    try {
        $tenantId = dbScalar($conn, "SELECT id FROM tenants WHERE user_id = ?", [$_SESSION['user_id']]);
        if ($tenantId) {
            $_unpaidBills = (int) dbScalar($conn,
                "SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND status = 'unpaid'", [$tenantId]);
        }
    } catch (Exception $e) { $_unpaidBills = 0; }
}
?>
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="logo-icon">🏠</div>
    <h2>BoardingEase</h2>
    <span>Tenant Portal</span>
  </div>

  <nav class="sidebar-nav">
    <div class="nav-section-label">Browse</div>
    <a href="/boarding_system/tenant/rooms.php"
       class="nav-item <?= ($current==='rooms.php' && $currentDir==='tenant') ? 'active' : '' ?>">
      <span class="icon">🛏️</span> Available Rooms
    </a>
    <a href="/boarding_system/tenant/booking.php"
       class="nav-item <?= ($current==='booking.php') ? 'active' : '' ?>">
      <span class="icon">📋</span> My Booking
    </a>

    <div class="nav-section-label">Finances</div>
    <a href="/boarding_system/tenant/my_bills.php"
       class="nav-item <?= ($current==='my_bills.php') ? 'active' : '' ?>"
       style="justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:12px;">
        <span class="icon">💳</span> My Bills
      </span>
      <?php if ($_unpaidBills > 0): ?>
      <span style="background:var(--danger);color:#fff;font-size:10px;font-weight:800;
                   padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;
                   animation:billPulse 2s infinite;">
        <?= $_unpaidBills ?>
      </span>
      <?php endif; ?>
    </a>

    <div class="nav-section-label">Support</div>
    <a href="/boarding_system/tenant/messages.php"
       class="nav-item <?= ($current==='messages.php') ? 'active' : '' ?>"
       style="justify-content:space-between;">
      <span style="display:flex;align-items:center;gap:12px;">
        <span class="icon">💬</span> Messages
      </span>
      <?php
      $_msgUnread = 0;
      if (isset($conn) && isset($_SESSION['user_id'])) {
          try {
              $_msgUnread = (int) dbScalar($conn,
                  "SELECT COUNT(*) FROM messages WHERE receiver_id = ? AND is_read = FALSE",
                  [$_SESSION['user_id']]);
          } catch (Exception $e) { $_msgUnread = 0; }
      }
      if ($_msgUnread > 0): ?>
      <span style="background:var(--info);color:#fff;font-size:10px;font-weight:800;
                   padding:2px 7px;border-radius:20px;min-width:20px;text-align:center;animation:billPulse 2s infinite;">
        <?= $_msgUnread ?>
      </span>
      <?php endif; ?>
    </a>

    <div class="nav-section-label">Account</div>
    <a href="/boarding_system/logout.php" class="nav-item">
      <span class="icon">🚪</span> Logout
    </a>
  </nav>

  <div class="sidebar-footer">
    <div class="user-info">
      <div class="avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'T', 0, 1)) ?></div>
      <div>
        <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Tenant') ?></div>
        <div class="user-role">Tenant</div>
      </div>
    </div>
  </div>
</aside>
<style>
@keyframes billPulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.75;transform:scale(1.1)} }
</style>
