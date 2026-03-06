<?php
require_once '../config/database.php';
requireAdmin();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'approve') {
            $booking_id = intval($_POST['booking_id']);
            $booking    = dbOne($conn, "SELECT * FROM bookings WHERE id = ?", [$booking_id]);
            if (!$booking) throw new InvalidArgumentException("Booking not found.");

            $conn->beginTransaction();

            // Mark booking approved & room occupied
            dbExec($conn, "UPDATE bookings SET status = 'approved' WHERE id = ?", [$booking_id]);
            dbExec($conn, "UPDATE rooms SET status = 'occupied' WHERE id = ?", [$booking['room_id']]);

            // The tenant who booked is already a registered user (booking stores user_id).
            // Just ensure a tenants record exists linked to that user.
            $existing_user_id = $booking['user_id'] ?? null;

            if ($existing_user_id) {
                $tenantExists = dbScalar($conn, "SELECT COUNT(*) FROM tenants WHERE user_id = ?", [$existing_user_id]);
                if (!$tenantExists) {
                    dbExec($conn,
                        "INSERT INTO tenants (user_id, full_name, email, phone, room_id, move_in_date)
                         VALUES (?, ?, ?, ?, ?, CURRENT_DATE)",
                        [
                            $existing_user_id,
                            cleanInput($booking['name']  ?? 'Tenant'),
                            cleanInput($booking['email'] ?? ''),
                            cleanInput($booking['phone'] ?? ''),
                            $booking['room_id']
                        ]
                    );
                } else {
                    // Update their room assignment
                    dbExec($conn,
                        "UPDATE tenants SET room_id = ?, move_in_date = CURRENT_DATE WHERE user_id = ?",
                        [$booking['room_id'], $existing_user_id]
                    );
                }
            }

            $conn->commit();

            // Show the tenant's login info so admin can contact them
            $tenant_user = $existing_user_id
                ? dbOne($conn, "SELECT username FROM users WHERE id = ?", [$existing_user_id])
                : null;
            $tenantUsername = $tenant_user['username'] ?? '—';

            $msg = "✅ Booking approved! Tenant <strong>" . htmlspecialchars($booking['name']) . "</strong> has been assigned to Room <strong>" . htmlspecialchars(dbScalar($conn, "SELECT room_number FROM rooms WHERE id = ?", [$booking['room_id']])) . "</strong>. "
                 . "Their login username is: <strong>" . htmlspecialchars($tenantUsername) . "</strong>. "
                 . "You can now generate a bill for them in <a href='bills.php'>Bills &amp; Payments</a>.";
            $msgType = 'success';
        }

        if ($action === 'reject') {
            $booking_id = intval($_POST['booking_id']);
            dbExec($conn, "UPDATE bookings SET status = 'rejected' WHERE id = ?", [$booking_id]);
            $msg = "Booking rejected."; $msgType = 'warning';
        }

        if ($action === 'delete') {
            $booking_id = intval($_POST['booking_id']);
            dbExec($conn, "DELETE FROM bookings WHERE id = ?", [$booking_id]);
            $msg = "Booking record deleted."; $msgType = 'warning';
        }

    } catch (InvalidArgumentException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $msg = $e->getMessage(); $msgType = 'warning';
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $msg = "Database error: " . $e->getMessage(); $msgType = 'danger';
    }
}

// Fetch bookings
$filter  = cleanInput($_GET['status'] ?? '');
$bSql    = "SELECT b.*, r.room_number, r.type AS room_type, r.price
            FROM bookings b
            JOIN rooms r ON b.room_id = r.id
            WHERE 1=1";
$bParams = [];
if ($filter) { $bSql .= " AND b.status = ?"; $bParams[] = $filter; }
$bSql .= " ORDER BY b.id DESC";
$bookings = dbAll($conn, $bSql, $bParams);

$countPending  = dbScalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
$countApproved = dbScalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'approved'");
$countRejected = dbScalar($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'rejected'");

$pageTitle = "Bookings";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>📋 Booking Requests</h1>
        <p>Review and manage room booking applications</p>
      </div>
      <?php if ($countPending > 0): ?>
      <div class="topbar-right">
        <span class="badge badge-warning" style="font-size:13px;padding:7px 14px;animation:pulse 2s infinite;">
          🔔 <?= $countPending ?> pending review
        </span>
      </div>
      <?php endif; ?>
    </div>

    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
      <?php endif; ?>

      <!-- SUMMARY STATS -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
        <div class="stat-card gold">
          <div class="stat-icon gold">⏳</div>
          <div>
            <div class="stat-value" style="color:var(--warning);"><?= $countPending ?></div>
            <div class="stat-label">Pending Review</div>
          </div>
        </div>
        <div class="stat-card green">
          <div class="stat-icon green">✅</div>
          <div>
            <div class="stat-value" style="color:var(--success);"><?= $countApproved ?></div>
            <div class="stat-label">Approved</div>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon red">❌</div>
          <div>
            <div class="stat-value" style="color:var(--danger);"><?= $countRejected ?></div>
            <div class="stat-label">Rejected</div>
          </div>
        </div>
      </div>

      <!-- FILTER -->
      <div class="search-bar" style="margin-bottom:16px;">
        <div style="display:flex;gap:8px;">
          <a href="bookings.php"          class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
          <a href="?status=pending"  class="btn btn-sm <?= $filter==='pending'  ? 'btn-primary' : 'btn-outline' ?>">⏳ Pending</a>
          <a href="?status=approved" class="btn btn-sm <?= $filter==='approved' ? 'btn-primary' : 'btn-outline' ?>">✅ Approved</a>
          <a href="?status=rejected" class="btn btn-sm <?= $filter==='rejected' ? 'btn-primary' : 'btn-outline' ?>">❌ Rejected</a>
        </div>
      </div>

      <!-- BOOKINGS TABLE -->
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>Applicant</th>
                  <th>Contact</th>
                  <th>Room</th>
                  <th>Message</th>
                  <th>Date</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($bookings)): foreach ($bookings as $b): ?>
                <tr>
                  <td style="color:var(--text-muted);font-size:12px;">#<?= $b['id'] ?></td>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="avatar" style="width:34px;height:34px;font-size:13px;flex-shrink:0;">
                        <?= strtoupper(substr($b['name'] ?? 'A', 0, 1)) ?>
                      </div>
                      <div style="font-weight:700;font-size:13px;"><?= htmlspecialchars($b['name'] ?? '—') ?></div>
                    </div>
                  </td>
                  <td>
                    <?php if (!empty($b['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($b['email']) ?>" style="font-size:13px;color:var(--primary);">
                      📧 <?= htmlspecialchars($b['email']) ?>
                    </a><br>
                    <?php endif; ?>
                    <?php if (!empty($b['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($b['phone']) ?>" style="font-size:12px;color:var(--text-muted);">
                      📞 <?= htmlspecialchars($b['phone']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (empty($b['email']) && empty($b['phone'])): ?>
                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <span class="badge badge-primary">Room <?= htmlspecialchars($b['room_number']) ?></span>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">
                      <?= htmlspecialchars($b['room_type']) ?> · ₱<?= number_format($b['price'],2) ?>/mo
                    </div>
                  </td>
                  <td style="font-size:12px;color:var(--text-muted);max-width:160px;">
                    <?= $b['message'] ? htmlspecialchars(mb_strimwidth($b['message'], 0, 60, '…')) : '<em>—</em>' ?>
                  </td>
                  <td style="font-size:12px;color:var(--text-muted);">
                    <?= !empty($b['created_at']) ? date('M d, Y', strtotime($b['created_at'])) : '—' ?>
                  </td>
                  <td>
                    <?php if ($b['status'] === 'pending'): ?>
                      <span class="badge badge-warning">⏳ Pending</span>
                    <?php elseif ($b['status'] === 'approved'): ?>
                      <span class="badge badge-success">✅ Approved</span>
                    <?php else: ?>
                      <span class="badge badge-danger">❌ Rejected</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                      <?php if ($b['status'] === 'pending'): ?>
                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">✅ Approve</button>
                      </form>
                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="reject">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">❌ Reject</button>
                      </form>
                      <?php else: ?>
                      <form method="POST" onsubmit="return confirm('Delete this booking record?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm">🗑️</button>
                      </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                  <td colspan="8" style="text-align:center;padding:50px;color:var(--text-muted);">
                    <div style="font-size:36px;margin-bottom:8px;">📋</div>
                    <div>No booking requests found.</div>
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }
</style>

<?php include '../includes/footer.php'; ?>
