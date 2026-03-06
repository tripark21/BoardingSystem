<?php
require_once '../config/database.php';
requireAdmin();

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $full_name = cleanInput($_POST['full_name']);
            $email     = cleanInput($_POST['email']);
            $phone     = cleanInput($_POST['phone']);
            $room_id   = intval($_POST['room_id']) ?: null;
            $move_in   = cleanInput($_POST['move_in_date']);
            $username  = cleanInput($_POST['username']);
            $password  = $_POST['password'] ?? '';

            if (!$full_name || !$username || !$password)
                throw new InvalidArgumentException("Full name, username, and password are required.");
            if (strlen($password) < 8)
                throw new InvalidArgumentException("Password must be at least 8 characters.");

            $exists = dbScalar($conn, "SELECT COUNT(*) FROM users WHERE username = ?", [$username]);
            if ($exists) throw new InvalidArgumentException("Username '$username' is already taken.");

            $conn->beginTransaction();
            dbExec($conn, "INSERT INTO users (username, password, role) VALUES (?, ?, 'tenant')",
                [$username, password_hash($password, PASSWORD_DEFAULT)]);
            $user_id = $conn->lastInsertId('users_id_seq');

            dbExec($conn,
                "INSERT INTO tenants (user_id, full_name, email, phone, room_id, move_in_date) VALUES (?, ?, ?, ?, ?, ?)",
                [$user_id, $full_name, $email, $phone, $room_id, $move_in ?: null]);

            if ($room_id)
                dbExec($conn, "UPDATE rooms SET status = 'occupied' WHERE id = ?", [$room_id]);

            $conn->commit();
            $msg = "Tenant added! Their login username is: <strong>" . htmlspecialchars($username) . "</strong>. Please share their credentials so they can log in.";
            $msgType = 'success';
        }

        if ($action === 'delete') {
            $id     = intval($_POST['id']);
            $tenant = dbOne($conn, "SELECT * FROM tenants WHERE id = ?", [$id]);
            if ($tenant) {
                $conn->beginTransaction();
                if ($tenant['room_id'])
                    dbExec($conn, "UPDATE rooms SET status = 'available' WHERE id = ?", [$tenant['room_id']]);
                dbExec($conn, "DELETE FROM tenants WHERE id = ?", [$id]);
                if (!empty($tenant['user_id']))
                    dbExec($conn, "DELETE FROM users WHERE id = ?", [$tenant['user_id']]);
                $conn->commit();
            }
            $msg = "Tenant removed and room freed."; $msgType = 'warning';
        }

    } catch (InvalidArgumentException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $msg = $e->getMessage(); $msgType = 'warning';
    } catch (PDOException $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $msg = "Database error: " . $e->getMessage(); $msgType = 'danger';
    }
}

// Fetch data
$search  = cleanInput($_GET['q'] ?? '');
$tSql    = "SELECT t.*, r.room_number, r.type AS room_type, r.price, u.username,
                   COUNT(CASE WHEN b.status = 'unpaid' THEN 1 END) as unpaid_count,
                   COALESCE(SUM(CASE WHEN b.status = 'unpaid' THEN b.amount ELSE 0 END), 0) as unpaid_amount
            FROM tenants t
            LEFT JOIN rooms r ON t.room_id = r.id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN bills b ON t.id = b.tenant_id
            WHERE 1=1";
$tParams = [];
if ($search) {
    $tSql   .= " AND (t.full_name ILIKE ? OR t.email ILIKE ?)";
    $tParams = ['%'.$search.'%', '%'.$search.'%'];
}
$tSql .= " GROUP BY t.id, r.id, u.id ORDER BY t.id DESC";
$tenants = dbAll($conn, $tSql, $tParams);

$availableRooms = dbAll($conn, "SELECT * FROM rooms WHERE status = 'available' ORDER BY room_number");

$pageTitle = "Tenants";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>👥 Tenant Management</h1>
        <p>All active tenants and their details</p>
      </div>
      <div class="topbar-right">
        <a href="bookings.php" class="btn btn-outline btn-sm">📋 View Bookings</a>
        <button class="btn btn-primary" onclick="openModal('addTenantModal')">➕ Add Tenant</button>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= $msg ?></div>
      <?php endif; ?>

      <!-- SEARCH -->
      <div class="search-bar">
        <div class="search-input-wrap">
          <span class="icon">🔍</span>
          <form method="GET">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name or email…">
          </form>
        </div>
      </div>

      <!-- TENANTS TABLE -->
      <div class="card">
        <div class="card-header">
          <h3>All Tenants</h3>
          <span style="font-size:12px;color:var(--text-muted);"><?= count($tenants) ?> total</span>
        </div>
        <div class="card-body" style="padding-top:12px;">
          <?php if (!empty($tenants)): ?>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Tenant</th>
                  <th>Login</th>
                  <th>Contact</th>
                  <th>Room</th>
                  <th>Rent</th>
                  <th>Move-in</th>
                  <th>Bills</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($tenants as $t): ?>
                <tr>
                  <td>
                    <div style="display:flex;align-items:center;gap:10px;">
                      <div class="avatar"><?= strtoupper(substr($t['full_name'],0,1)) ?></div>
                      <div>
                        <div style="font-weight:600;"><?= htmlspecialchars($t['full_name']) ?></div>
                        <div style="font-size:11px;color:var(--text-muted);">ID #<?= $t['id'] ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span style="font-family:monospace;font-size:12px;background:var(--bg);padding:2px 6px;border-radius:4px;border:1px solid var(--border);">
                      <?= htmlspecialchars($t['username'] ?? '—') ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!empty($t['email'])): ?>
                    <a href="mailto:<?= htmlspecialchars($t['email']) ?>" style="font-size:12px;color:var(--primary);display:block;">
                      📧 <?= htmlspecialchars($t['email']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($t['phone'])): ?>
                    <a href="tel:<?= htmlspecialchars($t['phone']) ?>" style="font-size:12px;color:var(--text-muted);display:block;">
                      📞 <?= htmlspecialchars($t['phone']) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (empty($t['email']) && empty($t['phone'])): ?>
                    <span style="color:var(--text-muted);font-size:12px;">—</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($t['room_number']): ?>
                      <span class="badge badge-primary">Room <?= htmlspecialchars($t['room_number']) ?></span>
                      <div style="font-size:11px;color:var(--text-muted);"><?= htmlspecialchars($t['room_type']) ?></div>
                    <?php else: ?>
                      <span class="badge badge-gray">Unassigned</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-weight:700;">
                    <?= $t['price'] ? '₱'.number_format($t['price'],2).'/mo' : '—' ?>
                  </td>
                  <td style="font-size:12px;color:var(--text-muted);">
                    <?= $t['move_in_date'] ? date('M d, Y', strtotime($t['move_in_date'])) : '—' ?>
                  </td>
                  <td>
                    <?php if ($t['unpaid_count'] > 0): ?>
                      <span class="badge badge-danger" style="cursor:pointer;" onclick="this.nextElementSibling.style.display=this.nextElementSibling.style.display==='none'?'block':'none'">
                        ⚠️ <?= $t['unpaid_count'] ?> unpaid
                      </span>
                      <div style="display:none;font-size:11px;margin-top:4px;background:var(--bg);padding:6px;border-radius:4px;border-left:3px solid var(--danger);">
                        <strong>₱<?= number_format($t['unpaid_amount'], 2) ?></strong> due
                      </div>
                    <?php else: ?>
                      <span class="badge badge-success">✓ All paid</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:6px;">
                      <a href="bills.php" class="btn btn-outline btn-sm" title="Generate bill for this tenant">📄 Bill</a>
                      <form method="POST" onsubmit="return confirm('Remove this tenant and free up their room?')">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                      </form>
                    </div>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php else: ?>
          <div class="text-center text-muted" style="padding:50px 0;">
            <div style="font-size:40px;margin-bottom:10px;">👥</div>
            <p>No tenants yet. Approve a booking or add one manually.</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ADD TENANT MODAL -->
<div class="modal-overlay" id="addTenantModal">
  <div class="modal">
    <div class="modal-header">
      <h3>➕ Add New Tenant</h3>
      <button class="btn btn-icon btn-outline" onclick="closeModal('addTenantModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" placeholder="Juan dela Cruz" required>
          </div>
          <div class="form-group">
            <label class="form-label">Assign Room</label>
            <select name="room_id" class="form-control">
              <option value="">— No room yet —</option>
              <?php foreach ($availableRooms as $r): ?>
              <option value="<?= $r['id'] ?>">
                Room <?= htmlspecialchars($r['room_number']) ?> — <?= htmlspecialchars($r['type']) ?> (₱<?= number_format($r['price'],2) ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="09xx-xxx-xxxx">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Move-in Date</label>
          <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d') ?>">
        </div>
        <hr style="border:none;border-top:1px solid var(--border);margin:8px 0 16px;">
        <p style="font-size:12px;font-weight:700;color:var(--text-muted);margin-bottom:10px;">LOGIN CREDENTIALS — Share these with the tenant</p>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Username *</label>
            <input type="text" name="username" class="form-control" placeholder="e.g. juan.delacruz" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password * <span style="font-size:11px;font-weight:400;color:var(--text-muted);">(min. 8 chars)</span></label>
            <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" minlength="8" required>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addTenantModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Add Tenant</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
