<?php
require_once '../config/database.php';
requireAdmin();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'generate') {
            $tenant_id   = intval($_POST['tenant_id']);
            $amount      = floatval($_POST['amount']);
            $description = cleanInput($_POST['description']);
            $due_date    = cleanInput($_POST['due_date']);

            if (!$tenant_id || $amount <= 0 || !$description || !$due_date) {
                throw new InvalidArgumentException("Please fill in all required fields.");
            }

            dbExec($conn,
                "INSERT INTO bills (tenant_id, amount, description, due_date, status)
                 VALUES (?, ?, ?, ?, 'unpaid')",
                [$tenant_id, $amount, $description, $due_date]
            );
            $msg = "Bill generated successfully!"; $msgType = 'success';
        }

        if ($action === 'mark_paid') {
            $id = intval($_POST['id']);
            dbExec($conn,
                "UPDATE bills SET status = 'paid', paid_date = CURRENT_DATE WHERE id = ?",
                [$id]
            );
            $msg = "Bill marked as paid!"; $msgType = 'success';
        }

        if ($action === 'delete') {
            $id = intval($_POST['id']);
            dbExec($conn, "DELETE FROM bills WHERE id = ?", [$id]);
            $msg = "Bill deleted."; $msgType = 'warning';
        }

        if ($action === 'generate_all') {
            $description = cleanInput($_POST['description']);
            $due_date    = cleanInput($_POST['due_date']);

            if (!$description || !$due_date) {
                throw new InvalidArgumentException("Please fill in all required fields.");
            }

            $tenants_all = dbAll($conn,
                "SELECT t.id, r.price FROM tenants t JOIN rooms r ON t.room_id = r.id WHERE t.room_id IS NOT NULL"
            );
            $count = 0;
            foreach ($tenants_all as $t) {
                // Prevent duplicate bills: skip if same tenant+description+due_date already exists
                $exists = dbScalar($conn,
                    "SELECT COUNT(*) FROM bills WHERE tenant_id = ? AND description = ? AND due_date = ?",
                    [$t['id'], $description, $due_date]
                );
                if ($exists) continue;

                dbExec($conn,
                    "INSERT INTO bills (tenant_id, amount, description, due_date, status)
                     VALUES (?, ?, ?, ?, 'unpaid')",
                    [$t['id'], $t['price'], $description, $due_date]
                );
                $count++;
            }

            if ($count === 0) {
                $msg = "No new bills generated — all tenants already have a bill for this period."; $msgType = 'warning';
            } else {
                $msg = "Generated $count bill(s) for active tenants!"; $msgType = 'success';
            }
        }

    } catch (InvalidArgumentException $e) {
        $msg = $e->getMessage(); $msgType = 'warning';
    } catch (PDOException $e) {
        $msg = "Database error: " . $e->getMessage(); $msgType = 'danger';
    }
}

// ── Fetch bills ───────────────────────────────────────────────
$filter  = cleanInput($_GET['status'] ?? '');
$bSql    = "SELECT b.*, t.full_name, r.room_number
            FROM bills b
            JOIN tenants t ON b.tenant_id = t.id
            LEFT JOIN rooms r ON t.room_id = r.id
            WHERE 1=1";
$bParams = [];
if ($filter) { $bSql .= " AND b.status = ?"; $bParams[] = $filter; }
$bSql .= " ORDER BY b.id DESC";
$bills = dbAll($conn, $bSql, $bParams);

// ── Summary stats ─────────────────────────────────────────────
$totalPaid    = dbScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM bills WHERE status = 'paid'");
$totalUnpaid  = dbScalar($conn, "SELECT COALESCE(SUM(amount),0) FROM bills WHERE status = 'unpaid'");
$countOverdue = dbScalar($conn, "SELECT COUNT(*) FROM bills WHERE status = 'unpaid' AND due_date < CURRENT_DATE");

// ── Tenant list for dropdown ──────────────────────────────────
$tenantList = dbAll($conn,
    "SELECT t.id, t.full_name, r.price, r.room_number
     FROM tenants t LEFT JOIN rooms r ON t.room_id = r.id
     ORDER BY t.full_name"
);

$pageTitle = "Bills";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>📄 Bills &amp; Payments</h1>
        <p>Manage tenant billing</p>
      </div>
      <div class="topbar-right">
        <button class="btn btn-outline btn-sm" onclick="openModal('generateAllModal')">🔁 Generate Monthly</button>
        <button class="btn btn-primary" onclick="openModal('addBillModal')">➕ Add Bill</button>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <!-- SUMMARY -->
      <div class="bill-summary">
        <div class="bill-stat">
          <div class="amount" style="color:var(--success);">₱<?= number_format($totalPaid,2) ?></div>
          <div class="label">Total Collected</div>
        </div>
        <div class="bill-stat">
          <div class="amount" style="color:var(--danger);">₱<?= number_format($totalUnpaid,2) ?></div>
          <div class="label">Pending Balance</div>
        </div>
        <div class="bill-stat">
          <div class="amount" style="color:var(--warning);"><?= $countOverdue ?></div>
          <div class="label">Overdue Bills</div>
        </div>
      </div>

      <!-- FILTER -->
      <div class="search-bar" style="margin-bottom:16px;">
        <div style="display:flex;gap:8px;">
          <a href="bills.php"      class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
          <a href="?status=unpaid" class="btn btn-sm <?= $filter==='unpaid' ? 'btn-primary' : 'btn-outline' ?>">⏳ Unpaid</a>
          <a href="?status=paid"   class="btn btn-sm <?= $filter==='paid'   ? 'btn-primary' : 'btn-outline' ?>">✅ Paid</a>
        </div>
      </div>

      <!-- BILLS TABLE -->
      <div class="card">
        <div class="card-body" style="padding:0;">
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>#</th><th>Tenant</th><th>Room</th><th>Description</th>
                  <th>Amount</th><th>Due Date</th><th>Status</th><th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($bills)): foreach ($bills as $b):
                  $today    = date('Y-m-d');
                  $isOverdue = ($b['status'] === 'unpaid' && $b['due_date'] < $today);
                ?>
                <tr>
                  <td style="color:var(--text-muted);font-size:12px;">#<?= $b['id'] ?></td>
                  <td style="font-weight:600;"><?= htmlspecialchars($b['full_name']) ?></td>
                  <td><?= $b['room_number'] ? 'Room '.htmlspecialchars($b['room_number']) : '—' ?></td>
                  <td style="color:var(--text-muted);font-size:13px;"><?= htmlspecialchars($b['description']) ?></td>
                  <td style="font-weight:700;">₱<?= number_format($b['amount'],2) ?></td>
                  <td style="font-size:13px;"><?= date('M d, Y', strtotime($b['due_date'])) ?></td>
                  <td>
                    <?php if ($b['status'] === 'paid'): ?>
                      <span class="badge badge-success">✓ Paid</span>
                      <?php if ($b['paid_date']): ?><div style="font-size:11px;color:var(--text-muted);"><?= date('M d, Y', strtotime($b['paid_date'])) ?></div><?php endif; ?>
                    <?php elseif ($isOverdue): ?>
                      <span class="badge badge-danger">Overdue</span>
                    <?php else: ?>
                      <span class="badge badge-warning">Pending</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="flex gap-2">
                      <?php if ($b['status'] !== 'paid'): ?>
                      <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="mark_paid">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-sm">✓ Paid</button>
                      </form>
                      <?php endif; ?>
                      <form method="POST" onsubmit="return confirm('Delete this bill?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $b['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="8" class="text-center text-muted" style="padding:40px;">No bills found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD BILL MODAL -->
<div class="modal-overlay" id="addBillModal">
  <div class="modal">
    <div class="modal-header">
      <h3>➕ Generate Bill</h3>
      <button class="btn btn-icon btn-outline" onclick="closeModal('addBillModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="generate">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Tenant *</label>
          <select name="tenant_id" id="billTenantSelect" class="form-control" required onchange="setRentAmount(this)">
            <option value="">Select tenant</option>
            <?php foreach ($tenantList as $t): ?>
            <option value="<?= $t['id'] ?>" data-price="<?= $t['price'] ?>">
              <?= htmlspecialchars($t['full_name']) ?><?= $t['room_number'] ? ' — Room '.htmlspecialchars($t['room_number']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Description *</label>
          <input type="text" name="description" class="form-control" placeholder="e.g. Monthly Rent – January 2025" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Amount (₱) *</label>
            <input type="number" name="amount" id="billAmount" class="form-control" placeholder="0.00" step="0.01" min="0.01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Due Date *</label>
            <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addBillModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Generate Bill</button>
      </div>
    </form>
  </div>
</div>

<!-- GENERATE ALL MODAL -->
<div class="modal-overlay" id="generateAllModal">
  <div class="modal">
    <div class="modal-header">
      <h3>🔁 Generate Monthly Bills for All</h3>
      <button class="btn btn-icon btn-outline" onclick="closeModal('generateAllModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="generate_all">
      <div class="modal-body">
        <div class="alert alert-warning">⚠️ This will create a bill for every active tenant based on their room price. Duplicates for the same period are automatically skipped.</div>
        <div class="form-group">
          <label class="form-label">Description *</label>
          <input type="text" name="description" class="form-control"
                 placeholder="Monthly Rent – <?= date('F Y') ?>"
                 value="Monthly Rent – <?= date('F Y') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Due Date *</label>
          <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('generateAllModal')">Cancel</button>
        <button type="submit" class="btn btn-accent">Generate All Bills</button>
      </div>
    </form>
  </div>
</div>

<script>
function setRentAmount(select) {
  const price = select.options[select.selectedIndex].dataset.price;
  if (price) document.getElementById('billAmount').value = price;
}
</script>

<?php include '../includes/footer.php'; ?>
