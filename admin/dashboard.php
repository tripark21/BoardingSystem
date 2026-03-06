<?php
require_once '../config/database.php';
requireAdmin();

$totalRooms      = dbScalar($conn, "SELECT COUNT(*) FROM rooms");
$occupiedRooms   = dbScalar($conn, "SELECT COUNT(*) FROM rooms WHERE status = 'occupied'");
$availableRooms  = dbScalar($conn, "SELECT COUNT(*) FROM rooms WHERE status = 'available'");
$totalTenants    = dbScalar($conn, "SELECT COUNT(*) FROM tenants");
$pendingBills    = dbScalar($conn, "SELECT COUNT(*) FROM bills WHERE status = 'unpaid'");
$monthRevenue    = dbScalar($conn,
    "SELECT COALESCE(SUM(amount), 0) FROM bills
     WHERE status = 'paid'
       AND EXTRACT(MONTH FROM paid_date) = EXTRACT(MONTH FROM CURRENT_DATE)
       AND EXTRACT(YEAR  FROM paid_date) = EXTRACT(YEAR  FROM CURRENT_DATE)"
);
$pendingBookings     = dbScalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$recentBookingsList  = dbAll($conn,
    "SELECT b.*, r.room_number, r.type, r.price
     FROM bookings b JOIN rooms r ON b.room_id = r.id
     WHERE b.status = 'pending'
     ORDER BY b.id DESC LIMIT 5"
);

$recentTenants = dbAll($conn,
    "SELECT t.*, r.room_number
     FROM tenants t LEFT JOIN rooms r ON t.room_id = r.id
     ORDER BY t.id DESC LIMIT 5"
);
$recentBills = dbAll($conn,
    "SELECT b.*, t.full_name
     FROM bills b JOIN tenants t ON b.tenant_id = t.id
     ORDER BY b.id DESC LIMIT 5"
);

$occupancyPct = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;
$maintenanceRooms = max(0, $totalRooms - $occupiedRooms - $availableRooms);

$pageTitle = "Dashboard";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>Dashboard</h1>
        <p><?= date('l, F j, Y') ?></p>
      </div>
      <div class="topbar-right">
        <?php if ($pendingBookings > 0): ?>
        <a href="tenants.php" class="btn btn-accent btn-sm">
          📋 <?= $pendingBookings ?> Pending Booking<?= $pendingBookings > 1 ? 's' : '' ?>
        </a>
        <?php endif; ?>
        <a href="bills.php" class="btn btn-outline btn-sm">➕ Add Bill</a>
      </div>
    </div>

    <div class="page-body">

      <!-- STAT CARDS -->
      <div class="stats-grid">
        <div class="stat-card blue">
          <div class="stat-icon blue">🛏️</div>
          <div>
            <div class="stat-value" style="color:var(--primary)"><?= $totalRooms ?></div>
            <div class="stat-label">Total Rooms</div>
            <div class="stat-change up">↑ <?= $availableRooms ?> available</div>
          </div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon green">👥</div>
          <div>
            <div class="stat-value" style="color:var(--success)"><?= $totalTenants ?></div>
            <div class="stat-label">Active Tenants</div>
            <div class="stat-change up">↑ <?= $occupiedRooms ?> occupied rooms</div>
          </div>
        </div>
        <div class="stat-card gold">
          <div class="stat-icon gold">💰</div>
          <div>
            <div class="stat-value" style="color:var(--accent)">₱<?= number_format($monthRevenue) ?></div>
            <div class="stat-label">Revenue This Month</div>
            <div class="stat-change up">↑ Collected payments</div>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon red">📄</div>
          <div>
            <div class="stat-value" style="color:var(--danger)"><?= $pendingBills ?></div>
            <div class="stat-label">Unpaid Bills</div>
            <?php if ($pendingBills > 0): ?>
            <div class="stat-change down">⚠️ Needs attention</div>
            <?php else: ?>
            <div class="stat-change up">✓ All clear</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- OCCUPANCY BAR -->
      <?php if ($totalRooms > 0): ?>
      <div class="card mb-4" style="margin-bottom:20px;">
        <div class="card-body">
          <div class="flex justify-between items-center" style="margin-bottom:10px;">
            <span style="font-size:13px;font-weight:700;">Occupancy Rate</span>
            <span style="font-size:13px;font-weight:800;color:var(--primary);"><?= $occupancyPct ?>%</span>
          </div>
          <div style="background:var(--bg);border-radius:8px;height:10px;overflow:hidden;">
            <div style="background:linear-gradient(90deg,var(--primary),var(--primary-light));height:100%;border-radius:8px;width:<?= $occupancyPct ?>%;transition:width 1s;"></div>
          </div>
          <div class="flex gap-3 mt-1" style="margin-top:8px;font-size:12px;color:var(--text-muted);">
            <span>🟦 Occupied: <?= $occupiedRooms ?></span>
            <span>🟩 Available: <?= $availableRooms ?></span>
            <span>⬜ Maintenance: <?= $maintenanceRooms ?></span>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- TWO COLUMNS -->
      <div class="grid-2">

        <!-- RECENT TENANTS -->
        <div class="card">
          <div class="card-header">
            <h3>Recent Tenants</h3>
            <a href="tenants.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding-top:12px;">
            <?php if (!empty($recentTenants)): ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Tenant</th><th>Room</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentTenants as $t): ?>
                  <tr>
                    <td>
                      <div style="display:flex;align-items:center;gap:10px;">
                        <div class="avatar" style="width:30px;height:30px;font-size:11px;"><?= strtoupper(substr($t['full_name'],0,1)) ?></div>
                        <div>
                          <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($t['full_name']) ?></div>
                          <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($t['email'] ?? '') ?></div>
                        </div>
                      </div>
                    </td>
                    <td><?= $t['room_number'] ? 'Room '.htmlspecialchars($t['room_number']) : '<span class="badge badge-gray">Unassigned</span>' ?></td>
                    <td><span class="badge badge-success">Active</span></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="text-center text-muted" style="padding:30px 0;">
              <div style="font-size:32px;margin-bottom:8px;">👥</div>
              <p>No tenants yet.</p>
              <a href="tenants.php" class="btn btn-primary btn-sm" style="margin-top:8px;">Add Tenant</a>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RECENT BILLS -->
        <div class="card">
          <div class="card-header">
            <h3>Recent Bills</h3>
            <a href="bills.php" class="btn btn-outline btn-sm">View All</a>
          </div>
          <div class="card-body" style="padding-top:12px;">
            <?php if (!empty($recentBills)): ?>
            <div class="table-wrap">
              <table>
                <thead><tr><th>Tenant</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                  <?php foreach ($recentBills as $b):
                    $today = date('Y-m-d');
                    $isOverdue = ($b['status'] === 'unpaid' && $b['due_date'] < $today);
                  ?>
                  <tr>
                    <td>
                      <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($b['full_name']) ?></div>
                      <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($b['description'] ?? 'Monthly Rent') ?></div>
                    </td>
                    <td style="font-weight:700;">₱<?= number_format($b['amount'],2) ?></td>
                    <td>
                      <?php if ($b['status'] === 'paid'): ?>
                        <span class="badge badge-success">✓ Paid</span>
                      <?php elseif ($isOverdue): ?>
                        <span class="badge badge-danger">Overdue</span>
                      <?php else: ?>
                        <span class="badge badge-warning">Pending</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <?php else: ?>
            <div class="text-center text-muted" style="padding:30px 0;">
              <div style="font-size:32px;margin-bottom:8px;">📄</div>
              <p>No bills generated yet.</p>
              <a href="bills.php" class="btn btn-primary btn-sm" style="margin-top:8px;">Create Bill</a>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>

      <!-- PENDING BOOKINGS PANEL -->
      <?php if (!empty($recentBookingsList)): ?>
      <div class="card" style="margin-top:20px;">
        <div class="card-header">
          <h3>🔔 Pending Booking Requests</h3>
          <a href="bookings.php" class="btn btn-accent btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding-top:12px;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>Applicant</th><th>Contact</th><th>Room</th><th>Price</th><th>Actions</th></tr>
              </thead>
              <tbody>
                <?php foreach ($recentBookingsList as $b): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="avatar" style="width:32px;height:32px;font-size:12px;"><?= strtoupper(substr($b['name'] ?? 'A',0,1)) ?></div>
                      <div style="font-weight:600;font-size:13px;"><?= htmlspecialchars($b['name'] ?? '—') ?></div>
                    </div>
                  </td>
                  <td>
                    <div style="font-size:12px;"><?= htmlspecialchars($b['email'] ?? '—') ?></div>
                    <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($b['phone'] ?? '') ?></div>
                  </td>
                  <td><span class="badge badge-primary">Room <?= htmlspecialchars($b['room_number']) ?></span></td>
                  <td style="font-weight:700;">₱<?= number_format($b['price'],2) ?>/mo</td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <form method="POST" action="bookings.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">✅ Approve</button>
                      </form>
                      <form method="POST" action="bookings.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">❌ Reject</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
