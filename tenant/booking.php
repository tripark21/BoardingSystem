<?php
require_once '../config/database.php';
requireTenant();

try {
    $user_id = $_SESSION['user_id'] ?? null;
    
    if (!$user_id) {
        die("Error: User ID not found in session.");
    }

    $bookings = dbAll($conn,
        "SELECT b.*, r.room_number, r.type, r.price, r.description AS room_desc
         FROM bookings b JOIN rooms r ON b.room_id = r.id
         WHERE b.user_id = ? ORDER BY b.id DESC",
        [$user_id]
    );

    $tenant = dbOne($conn,
        "SELECT t.*, r.room_number, r.type, r.price
         FROM tenants t LEFT JOIN rooms r ON t.room_id = r.id
         WHERE t.user_id = ?",
        [$user_id]
    );

    // Count unpaid bills
    $unpaidCount = 0;
    if ($tenant && !empty($tenant['id'])) {
        $unpaidCount = (int) dbScalar($conn,
            "SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND status = 'unpaid'", 
            [$tenant['id']]
        );
    }
} catch (Exception $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}

$pageTitle = "My Booking";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/tenant_navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>📋 My Booking</h1>
        <p>Your room assignment and booking history</p>
      </div>
    </div>

    <div class="page-body">

      <!-- CURRENT ROOM STATUS -->
      <?php if ($tenant && $tenant['room_number']): ?>
      <div class="card" style="margin-bottom:20px;border-color:var(--success);background:linear-gradient(135deg,#f6fffa 0%,#fff 100%);">
        <div class="card-body">
          <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="font-size:52px;line-height:1;">🏡</div>
            <div style="flex:1;min-width:180px;">
              <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:var(--success);margin-bottom:4px;">
                ✅ Booking Approved — You are Assigned
              </div>
              <div style="font-size:26px;font-weight:800;color:var(--primary);margin-bottom:2px;">
                Room <?= htmlspecialchars($tenant['room_number'] ?? '') ?>
              </div>
              <div style="color:var(--text-muted);font-size:13px;">
                <?= htmlspecialchars($tenant['type'] ?? '') ?> &nbsp;·&nbsp;
                <strong>₱<?= number_format($tenant['price'] ?? 0, 2) ?>/month</strong>
              </div>
              <?php if (!empty($tenant['move_in_date'])): ?>
              <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">
                📅 Moved in: <?= date('F d, Y', strtotime($tenant['move_in_date'])) ?>
              </div>
              <?php endif; ?>
            </div>
            <div style="text-align:right;">
              <?php if ($unpaidCount > 0): ?>
              <a href="my_bills.php" class="btn btn-danger btn-sm" style="margin-bottom:8px;display:block;">
                ⚠️ <?= $unpaidCount ?> Unpaid Bill<?= $unpaidCount > 1 ? 's' : '' ?>
              </a>
              <?php else: ?>
              <span class="badge badge-success" style="font-size:13px;padding:6px 14px;display:block;margin-bottom:8px;">✅ Bills Clear</span>
              <?php endif; ?>
              <a href="my_bills.php" class="btn btn-outline btn-sm">💳 View Bills</a>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- WHAT HAPPENS NEXT (shown when approved) -->
      <?php if ($tenant && $tenant['room_number']): ?>
      <div class="card" style="margin-bottom:20px;border-color:var(--primary);">
        <div class="card-body">
          <div style="font-size:15px;font-weight:800;color:var(--primary);margin-bottom:14px;">📌 What Happens Next</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;">
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <div style="font-size:22px;flex-shrink:0;">💳</div>
              <div>
                <div style="font-weight:700;font-size:13px;margin-bottom:2px;">Pay Your Rent</div>
                <div style="font-size:12px;color:var(--text-muted);line-height:1.5;">
                  The admin will generate a monthly bill. Check <a href="my_bills.php" style="color:var(--primary);">My Bills</a> to see what's due.
                </div>
              </div>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <div style="font-size:22px;flex-shrink:0;">🏢</div>
              <div>
                <div style="font-weight:700;font-size:13px;margin-bottom:2px;">Visit the Office</div>
                <div style="font-size:12px;color:var(--text-muted);line-height:1.5;">
                  Pay in person at the admin office. Always keep your receipt.
                </div>
              </div>
            </div>
            <div style="display:flex;gap:10px;align-items:flex-start;">
              <div style="font-size:22px;flex-shrink:0;">📞</div>
              <div>
                <div style="font-weight:700;font-size:13px;margin-bottom:2px;">Contact the Admin</div>
                <div style="font-size:12px;color:var(--text-muted);line-height:1.5;">
                  For concerns about your room or billing, contact the property admin directly.
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- NO ROOM YET -->
      <?php if (!$tenant || !$tenant['room_number']): ?>
      <div class="card" style="margin-bottom:20px;">
        <div class="card-body" style="text-align:center;padding:40px;">
          <div style="font-size:48px;margin-bottom:12px;">🛏️</div>
          <h3 style="margin-bottom:8px;">No Room Assigned Yet</h3>
          <p class="text-muted" style="margin-bottom:16px;">
            Browse available rooms and submit a booking request. The admin will review and approve it.
          </p>
          <a href="rooms.php" class="btn btn-primary">🔍 Browse Rooms</a>
        </div>
      </div>
      <?php endif; ?>

      <!-- BOOKING HISTORY -->
      <div class="card">
        <div class="card-header">
          <h3>Booking Request History</h3>
          <?php if (!$tenant || !$tenant['room_number']): ?>
          <a href="rooms.php" class="btn btn-outline btn-sm">➕ New Request</a>
          <?php endif; ?>
        </div>
        <div class="card-body" style="padding-top:12px;">
          <?php if (!empty($bookings)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Room</th><th>Type</th><th>Price</th><th>Date Requested</th><th>Status</th></tr>
              </thead>
              <tbody>
                <?php foreach ($bookings as $b):
                  $status = $b['status'] ?? 'pending';
                  $statusBadge = ['pending'=>'badge-warning','approved'=>'badge-success','rejected'=>'badge-danger'][$status] ?? 'badge-gray';
                  $statusIcon  = ['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected'][$status] ?? htmlspecialchars($status);
                  $dateLabel   = !empty($b['created_at']) ? date('M d, Y', strtotime($b['created_at'])) : '—';
                ?>
                <tr>
                  <td style="color:var(--text-muted);font-size:12px;">#<?= htmlspecialchars($b['id'] ?? '') ?></td>
                  <td><span class="badge badge-primary">Room <?= htmlspecialchars($b['room_number'] ?? '') ?></span></td>
                  <td style="font-size:13px;"><?= htmlspecialchars($b['type'] ?? '') ?></td>
                  <td style="font-weight:700;">₱<?= number_format($b['price'] ?? 0, 2) ?>/mo</td>
                  <td style="font-size:12px;color:var(--text-muted);"><?= htmlspecialchars($dateLabel) ?></td>
                  <td><span class="badge <?= htmlspecialchars($statusBadge) ?>"><?= htmlspecialchars($statusIcon) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center text-muted" style="padding:40px 0;">
            <div style="font-size:36px;margin-bottom:8px;">📋</div>
            <p>No booking requests yet.</p>
            <a href="rooms.php" class="btn btn-primary btn-sm" style="margin-top:12px;">Browse Available Rooms</a>
          </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
