<?php
require_once '../config/database.php';
requireTenant();

$user_id   = $_SESSION['user_id'];
$tenant    = dbOne($conn,
    "SELECT t.*, r.room_number, r.type, r.price, r.description AS room_desc
     FROM tenants t LEFT JOIN rooms r ON t.room_id = r.id
     WHERE t.user_id = ?", [$user_id]);
$tenant_id = $tenant['id'] ?? 0;

$bills = dbAll($conn,
    "SELECT * FROM bills WHERE tenant_id = ? ORDER BY due_date DESC", [$tenant_id]);

$totalPaid   = dbScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM bills WHERE tenant_id = ? AND status = 'paid'",   [$tenant_id]);
$totalUnpaid = dbScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM bills WHERE tenant_id = ? AND status = 'unpaid'", [$tenant_id]);
$nextDue     = dbOne($conn,
    "SELECT * FROM bills WHERE tenant_id = ? AND status = 'unpaid' ORDER BY due_date ASC LIMIT 1", [$tenant_id]);

$today = date('Y-m-d');

$pageTitle = "My Bills";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/tenant_navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>💳 My Bills</h1>
        <p>Your billing history and payment status</p>
      </div>
    </div>

    <div class="page-body">

      <!-- OVERDUE ALERT -->
      <?php
      $overdueCount = 0;
      foreach ($bills as $b) {
          if ($b['status'] === 'unpaid' && $b['due_date'] < $today) $overdueCount++;
      }
      if ($overdueCount > 0): ?>
      <div class="alert alert-danger" style="margin-bottom:20px;">
        ⚠️ You have <strong><?= $overdueCount ?> overdue bill<?= $overdueCount > 1 ? 's' : '' ?></strong>.
        Please contact the admin immediately to arrange payment.
      </div>
      <?php endif; ?>

      <!-- NEXT DUE ALERT -->
      <?php if ($nextDue && $nextDue['due_date'] >= $today): ?>
      <div class="alert alert-warning" style="margin-bottom:20px;">
        📅 Your next payment of <strong>₱<?= number_format($nextDue['amount'],2) ?></strong>
        (<?= htmlspecialchars($nextDue['description']) ?>) is due on
        <strong><?= date('F d, Y', strtotime($nextDue['due_date'])) ?></strong>.
        Please pay at the admin office before the due date.
      </div>
      <?php endif; ?>

      <!-- SUMMARY CARDS -->
      <div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:24px;">
        <div class="stat-card green">
          <div class="stat-icon green">✅</div>
          <div>
            <div class="stat-value" style="color:var(--success);font-size:22px;">₱<?= number_format($totalPaid,2) ?></div>
            <div class="stat-label">Total Paid</div>
          </div>
        </div>
        <div class="stat-card red">
          <div class="stat-icon red">⏳</div>
          <div>
            <div class="stat-value" style="color:var(--danger);font-size:22px;">₱<?= number_format($totalUnpaid,2) ?></div>
            <div class="stat-label">Outstanding Balance</div>
          </div>
        </div>
        <div class="stat-card gold">
          <div class="stat-icon gold">📅</div>
          <div>
            <?php if ($nextDue): ?>
            <div class="stat-value" style="color:var(--accent);font-size:18px;">₱<?= number_format($nextDue['amount'],2) ?></div>
            <div class="stat-label">Due <?= date('M d', strtotime($nextDue['due_date'])) ?></div>
            <?php else: ?>
            <div class="stat-value" style="color:var(--success);font-size:18px;">All Clear</div>
            <div class="stat-label">No pending bills</div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- BILLS TABLE -->
      <div class="card" style="margin-bottom:20px;">
        <div class="card-header"><h3>Billing History</h3></div>
        <div class="card-body" style="padding-top:12px;">
          <?php if (!empty($bills)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr><th>#</th><th>Description</th><th>Amount</th><th>Due Date</th><th>Status</th><th>Paid On</th></tr>
              </thead>
              <tbody>
                <?php foreach ($bills as $b):
                  $isOverdue = ($b['status'] === 'unpaid' && $b['due_date'] < $today);
                ?>
                <tr>
                  <td style="color:var(--text-muted);font-size:12px;">#<?= $b['id'] ?></td>
                  <td style="font-weight:600;font-size:13px;"><?= htmlspecialchars($b['description']) ?></td>
                  <td style="font-weight:700;font-size:15px;">₱<?= number_format($b['amount'],2) ?></td>
                  <td style="font-size:13px;"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                  <td>
                    <?php if ($b['status'] === 'paid'): ?>
                      <span class="badge badge-success">✅ Paid</span>
                    <?php elseif ($isOverdue): ?>
                      <span class="badge badge-danger">⚠️ Overdue</span>
                    <?php else: ?>
                      <span class="badge badge-warning">⏳ Pending</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px;color:var(--text-muted);">
                    <?= $b['paid_date'] ? date('M d, Y', strtotime($b['paid_date'])) : '—' ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center text-muted" style="padding:50px 0;">
            <div style="font-size:40px;margin-bottom:10px;">💳</div>
            <p>No bills yet. The admin will generate your first bill soon.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- HOW TO PAY -->
      <div class="card" style="border-color:var(--primary);">
        <div class="card-body">
          <div style="font-size:18px;font-weight:800;margin-bottom:16px;color:var(--primary);">💡 How to Pay Your Rent</div>
          <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;">

            <div style="background:var(--bg);border-radius:10px;padding:16px;">
              <div style="font-size:22px;margin-bottom:8px;">🏢</div>
              <div style="font-weight:700;font-size:13px;margin-bottom:4px;">Visit the Admin Office</div>
              <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                Come in person and pay cash or check. Ask for an official receipt.
              </div>
            </div>

            <?php if ($tenant && !empty($tenant['room_number'])): ?>
            <div style="background:var(--bg);border-radius:10px;padding:16px;">
              <div style="font-size:22px;margin-bottom:8px;">🏡</div>
              <div style="font-weight:700;font-size:13px;margin-bottom:4px;">Your Room</div>
              <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                Room <strong><?= htmlspecialchars($tenant['room_number']) ?></strong> —
                <?= htmlspecialchars($tenant['type']) ?><br>
                ₱<?= number_format($tenant['price'],2) ?>/month
              </div>
            </div>
            <?php endif; ?>

            <div style="background:var(--bg);border-radius:10px;padding:16px;">
              <div style="font-size:22px;margin-bottom:8px;">📞</div>
              <div style="font-weight:700;font-size:13px;margin-bottom:4px;">Contact Admin</div>
              <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                For billing questions or payment arrangements,<br>
                reach out to the property admin directly.
              </div>
            </div>

            <div style="background:#fff8e1;border-radius:10px;padding:16px;border:1px solid #ffe082;">
              <div style="font-size:22px;margin-bottom:8px;">📌</div>
              <div style="font-weight:700;font-size:13px;margin-bottom:4px;">Important Reminders</div>
              <div style="font-size:12px;color:var(--text-muted);line-height:1.6;">
                • Always pay before the due date<br>
                • Keep your official receipt<br>
                • Late payments may incur penalties
              </div>
            </div>

          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
