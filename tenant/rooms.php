<?php
require_once '../config/database.php';
requireTenant();

$user_id = $_SESSION['user_id'];
$msg = ''; $msgType = '';

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $room_id = intval($_POST['room_id'] ?? 0);
    $name    = cleanInput($_POST['name'] ?? '');
    $email   = cleanInput($_POST['email'] ?? '');
    $phone   = cleanInput($_POST['phone'] ?? '');
    $message = cleanInput($_POST['message'] ?? '');

    if (!$room_id || !$name) {
        $msg = "Please fill in all required fields."; $msgType = 'warning';
    } else {
        try {
            // Check room is still available
            $room = dbOne($conn, "SELECT * FROM rooms WHERE id = ? AND status = 'available'", [$room_id]);
            if (!$room) {
                $msg = "Sorry, this room is no longer available."; $msgType = 'danger';
            } else {
                // Check if user already has a pending/approved booking
                $existing = dbScalar($conn,
                    "SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status IN ('pending','approved')",
                    [$user_id]
                );
                if ($existing > 0) {
                    $msg = "You already have an active booking request. Please check My Booking."; $msgType = 'warning';
                } else {
                    dbExec($conn,
                        "INSERT INTO bookings (user_id, room_id, name, email, phone, message, status)
                         VALUES (?, ?, ?, ?, ?, ?, 'pending')",
                        [$user_id, $room_id, $name, $email, $phone, $message]
                    );
                    $msg = "✅ Booking request submitted! The admin will review it shortly."; $msgType = 'success';
                }
            }
        } catch (PDOException $e) {
            $msg = "An error occurred. Please try again."; $msgType = 'danger';
        }
    }
}

// Fetch available rooms
$filter = cleanInput($_GET['type'] ?? '');
$search = cleanInput($_GET['q'] ?? '');

$sql    = "SELECT * FROM rooms WHERE status = 'available'";
$params = [];
if ($filter) { $sql .= " AND type = ?";            $params[] = $filter; }
if ($search) { $sql .= " AND room_number ILIKE ?"; $params[] = '%'.$search.'%'; }
$sql .= " ORDER BY price ASC";

$rooms = dbAll($conn, $sql, $params);

// Get tenant info
$tenant = dbOne($conn,
    "SELECT t.*, r.room_number FROM tenants t LEFT JOIN rooms r ON t.room_id = r.id WHERE t.user_id = ?",
    [$user_id]
);

$pageTitle = "Available Rooms";
include '../includes/header.php';
?>

<style>
.rooms-hero {
  background: linear-gradient(135deg, var(--primary) 0%, #0f2442 100%);
  border-radius: var(--radius); padding: 28px 32px; margin-bottom: 24px;
  display: flex; align-items: center; justify-content: space-between;
  position: relative; overflow: hidden;
}
.rooms-hero::before {
  content: '🏠'; position: absolute; right: 32px; top: 50%;
  transform: translateY(-50%); font-size: 80px; opacity: 0.08; pointer-events: none;
}
.rooms-hero h2 { color: #fff; font-size: 22px; font-weight: 800; margin-bottom: 4px; }
.rooms-hero p  { color: rgba(255,255,255,0.6); font-size: 13px; }

.room-grid-new {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 20px;
}
.room-card-new {
  background: #fff; border-radius: 16px;
  border: 1.5px solid var(--border);
  overflow: hidden; transition: all 0.25s;
  box-shadow: var(--shadow-sm);
  display: flex; flex-direction: column;
}
.room-card-new:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-lg);
  border-color: var(--primary);
}
.photo-wrap { position: relative; }
.room-photo {
  width: 100%; height: 180px; object-fit: cover;
  display: block; background: #f0f4f8;
}
.room-photo-placeholder {
  width: 100%; height: 180px;
  background: linear-gradient(135deg, #e8f0f9 0%, #dde8f5 100%);
  display: flex; flex-direction: column; align-items: center; justify-content: center;
  gap: 8px; color: var(--text-muted);
}
.room-photo-placeholder .ph-icon { font-size: 40px; opacity: 0.4; }
.room-photo-placeholder .ph-text { font-size: 12px; font-weight: 600; opacity: 0.5; }
.room-card-body { padding: 18px 20px; flex: 1; }
.room-type-tag {
  display: inline-flex; align-items: center; gap: 4px;
  font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: 0.8px; color: var(--text-muted);
  background: var(--bg); padding: 3px 8px; border-radius: 6px;
  border: 1px solid var(--border); margin-bottom: 10px;
}
.room-address { font-size: 15px; font-weight: 800; color: var(--primary); margin-bottom: 4px; }
.room-price-new { font-size: 22px; font-weight: 800; color: var(--accent); margin-bottom: 2px; }
.room-price-new span { font-size: 12px; color: var(--text-muted); font-weight: 400; }
.room-desc { font-size: 12px; color: var(--text-muted); line-height: 1.6; margin: 8px 0; min-height: 36px; }
.room-meta {
  display: flex; align-items: center; gap: 12px;
  font-size: 12px; color: var(--text-muted);
  padding-top: 10px; border-top: 1px solid var(--border); margin-top: 10px;
}
.room-actions { display: flex; gap: 8px; padding: 0 20px 18px; }
.room-actions .btn { flex: 1; justify-content: center; }
.status-ribbon {
  position: absolute; top: 12px; left: 12px;
  padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700;
  background: rgba(34,160,107,0.9); color: #fff;
}
.empty-rooms {
  grid-column: 1/-1; text-align: center; padding: 80px 20px;
  background: #fff; border-radius: 16px; border: 2px dashed var(--border);
}
</style>

<div class="app-wrapper">
  <?php include '../includes/tenant_navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>🛏️ Available Rooms</h1>
        <p>Browse and book your perfect room</p>
      </div>
      <?php if ($tenant && $tenant['room_number']): ?>
      <div class="topbar-right">
        <span class="badge badge-success" style="font-size:13px;padding:7px 14px;">
          🏡 You are in Room <?= htmlspecialchars($tenant['room_number']) ?>
        </span>
      </div>
      <?php endif; ?>
    </div>

    <div class="page-body">

      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <!-- HERO -->
      <div class="rooms-hero">
        <div>
          <h2>Find Your Room</h2>
          <p><?= count($rooms) ?> room<?= count($rooms) !== 1 ? 's' : '' ?> available right now</p>
        </div>
        <a href="booking.php" class="btn btn-outline" style="color:#fff;border-color:rgba(255,255,255,0.4);">📋 My Bookings</a>
      </div>

      <!-- FILTER BAR -->
      <div class="search-bar" style="margin-bottom:20px;">
        <div class="search-input-wrap">
          <span class="icon">🔍</span>
          <form method="GET" style="display:contents;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search rooms…">
            <?php if ($filter): ?><input type="hidden" name="type" value="<?= htmlspecialchars($filter) ?>"><?php endif; ?>
          </form>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <a href="rooms.php"              class="btn btn-sm <?= !$filter ? 'btn-primary' : 'btn-outline' ?>">All</a>
          <a href="?type=Single"   class="btn btn-sm <?= $filter==='Single'  ? 'btn-primary' : 'btn-outline' ?>">🛏️ Single</a>
          <a href="?type=Double"   class="btn btn-sm <?= $filter==='Double'  ? 'btn-primary' : 'btn-outline' ?>">🛏️ Double</a>
          <a href="?type=Studio"   class="btn btn-sm <?= $filter==='Studio'  ? 'btn-primary' : 'btn-outline' ?>">🏡 Studio</a>
          <a href="?type=Shared"   class="btn btn-sm <?= $filter==='Shared'  ? 'btn-primary' : 'btn-outline' ?>">👥 Shared</a>
        </div>
      </div>

      <!-- ROOM CARDS -->
      <div class="room-grid-new">
        <?php if (!empty($rooms)): foreach ($rooms as $r): ?>
        <div class="room-card-new">
          <div class="photo-wrap">
            <span class="status-ribbon">✅ Available</span>
            <?php if (!empty($r['photo_url'])): ?>
              <img src="<?= htmlspecialchars($r['photo_url']) ?>" alt="Room photo" class="room-photo"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="room-photo-placeholder" style="display:none;">
                <div class="ph-icon">🏠</div><div class="ph-text">No Photo</div>
              </div>
            <?php else: ?>
              <div class="room-photo-placeholder">
                <div class="ph-icon">🏠</div><div class="ph-text">No Photo Added</div>
              </div>
            <?php endif; ?>
          </div>
          <div class="room-card-body">
            <div class="room-type-tag">
              <?= ['Single'=>'🛏️','Double'=>'🛏️🛏️','Studio'=>'🏡','Shared'=>'👥'][$r['type']] ?? '🏠' ?>
              <?= htmlspecialchars($r['type']) ?>
            </div>
            <div class="room-address">Room <?= htmlspecialchars($r['room_number']) ?></div>
            <div class="room-price-new">₱<?= number_format($r['price'],2) ?> <span>/ month</span></div>
            <div class="room-desc"><?= $r['description'] ? htmlspecialchars($r['description']) : '<em style="opacity:.4;">No description.</em>' ?></div>
            <div class="room-meta">
              <span>👤 <?= $r['capacity'] ?> person<?= $r['capacity']>1?'s':'' ?></span>
            </div>
          </div>
          <div class="room-actions">
            <button class="btn btn-primary btn-sm"
              onclick="openBookingModal(<?= $r['id'] ?>, '<?= htmlspecialchars(addslashes($r['room_number'])) ?>', '<?= number_format($r['price'],2) ?>')">
              📋 Book This Room
            </button>
          </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-rooms">
          <div style="font-size:56px;margin-bottom:16px;">🏠</div>
          <h3 style="margin-bottom:8px;">No rooms available</h3>
          <p class="text-muted">Check back later or try a different filter.</p>
        </div>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>

<!-- BOOKING MODAL -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal">
    <div class="modal-header">
      <h3>📋 Book a Room</h3>
      <button class="btn btn-icon btn-outline" onclick="closeModal('bookingModal')">✕</button>
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="room_id" id="bookRoomId">
      <div class="modal-body">
        <div class="alert alert-warning" id="bookRoomInfo" style="margin-bottom:16px;">
          You are booking <strong id="bookRoomLabel"></strong> at <strong id="bookRoomPrice"></strong>/month.
        </div>
        <div class="form-group">
          <label class="form-label">Full Name *</label>
          <input type="text" name="name" class="form-control" placeholder="Your full name"
                 value="<?= htmlspecialchars($_SESSION['username'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com">
          </div>
          <div class="form-group">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control" placeholder="09xx-xxx-xxxx">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Message <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
          <textarea name="message" class="form-control" rows="3" placeholder="Any questions or requests for the admin…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('bookingModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Submit Booking Request</button>
      </div>
    </form>
  </div>
</div>

<script>
function openBookingModal(roomId, roomNumber, price) {
  document.getElementById('bookRoomId').value    = roomId;
  document.getElementById('bookRoomLabel').textContent = 'Room ' + roomNumber;
  document.getElementById('bookRoomPrice').textContent = '₱' + price;
  openModal('bookingModal');
}
</script>

<?php include '../includes/footer.php'; ?>
