<?php
require_once '../config/database.php';
requireAdmin();

// ── Upload helper ─────────────────────────────────────────────
function handlePhotoUpload($fileKey, $existingUrl = '') {
    if (empty($_FILES[$fileKey]['name'])) return $existingUrl; // no new file chosen

    $file     = $_FILES[$fileKey];
    $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
    $maxSize  = 5 * 1024 * 1024; // 5 MB

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException("File upload failed (code {$file['error']}).");
    }
    if (!in_array(mime_content_type($file['tmp_name']), $allowed)) {
        throw new InvalidArgumentException("Only JPG, PNG, WEBP, or GIF images are allowed.");
    }
    if ($file['size'] > $maxSize) {
        throw new InvalidArgumentException("Image must be under 5 MB.");
    }

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/boarding_system/uploads/rooms/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'room_' . uniqid() . '.' . $ext;
    $dest     = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new InvalidArgumentException("Could not save the uploaded file.");
    }

    // Delete old file if it was a local upload
    if ($existingUrl && strpos($existingUrl, '/boarding_system/uploads/rooms/') !== false) {
        $oldPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($existingUrl, PHP_URL_PATH);
        if (file_exists($oldPath)) unlink($oldPath);
    }

    return '/boarding_system/uploads/rooms/' . $filename;
}

$msg = ''; $msgType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add') {
            $room_number = cleanInput($_POST['address'] ?? '');
            $type        = cleanInput($_POST['type']);
            $price       = floatval($_POST['price']);
            $capacity    = intval($_POST['capacity']);
            $description = cleanInput($_POST['description'] ?? '');

            if (!$room_number || !$type || $price <= 0 || $capacity < 1) {
                throw new InvalidArgumentException("Please fill in all required fields with valid values.");
            }
            $exists = dbScalar($conn, "SELECT COUNT(*) FROM rooms WHERE room_number = ?", [$room_number]);
            if ($exists) throw new InvalidArgumentException("Room '$room_number' already exists.");

            $photo_url = handlePhotoUpload('photo_file');

            dbExec($conn,
                "INSERT INTO rooms (room_number, type, price, capacity, description, status, photo_url)
                 VALUES (?, ?, ?, ?, ?, 'available', ?)",
                [$room_number, $type, $price, $capacity, $description, $photo_url]
            );
            $msg = "Room added successfully!"; $msgType = 'success';
        }

        if ($action === 'edit') {
            $id          = intval($_POST['id']);
            $room_number = cleanInput($_POST['address'] ?? '');
            $type        = cleanInput($_POST['type']);
            $price       = floatval($_POST['price']);
            $capacity    = intval($_POST['capacity']);
            $status      = cleanInput($_POST['status']);
            $description = cleanInput($_POST['description'] ?? '');

            if (!$room_number || !$type || $price <= 0 || $capacity < 1) {
                throw new InvalidArgumentException("Please fill in all required fields with valid values.");
            }

            $existing    = dbOne($conn, "SELECT photo_url FROM rooms WHERE id = ?", [$id]);
            $photo_url   = handlePhotoUpload('photo_file', $existing['photo_url'] ?? '');

            dbExec($conn,
                "UPDATE rooms SET room_number=?, type=?, price=?, capacity=?, status=?, description=?, photo_url=? WHERE id=?",
                [$room_number, $type, $price, $capacity, $status, $description, $photo_url, $id]
            );
            $msg = "Room updated successfully!"; $msgType = 'success';
        }

        if ($action === 'delete') {
            $id   = intval($_POST['id']);
            $room = dbOne($conn, "SELECT status, photo_url FROM rooms WHERE id = ?", [$id]);
            if ($room && $room['status'] === 'occupied') {
                throw new InvalidArgumentException("Cannot delete an occupied room. Remove the tenant first.");
            }
            // Delete uploaded photo if local
            if (!empty($room['photo_url']) && strpos($room['photo_url'], '/boarding_system/uploads/rooms/') !== false) {
                $oldPath = $_SERVER['DOCUMENT_ROOT'] . parse_url($room['photo_url'], PHP_URL_PATH);
                if (file_exists($oldPath)) unlink($oldPath);
            }
            dbExec($conn, "DELETE FROM rooms WHERE id = ?", [$id]);
            $msg = "Room deleted."; $msgType = 'warning';
        }

    } catch (InvalidArgumentException $e) {
        $msg = $e->getMessage(); $msgType = 'warning';
    } catch (PDOException $e) {
        $msg = "Database error: " . $e->getMessage(); $msgType = 'danger';
    }
}

$filter = cleanInput($_GET['status'] ?? '');
$search = cleanInput($_GET['q'] ?? '');
$sql    = "SELECT * FROM rooms WHERE 1=1";
$params = [];
if ($filter) { $sql .= " AND status = ?";          $params[] = $filter; }
if ($search) { $sql .= " AND room_number ILIKE ?";  $params[] = '%'.$search.'%'; }
$sql .= " ORDER BY room_number";
$rooms = dbAll($conn, $sql, $params);

$totalRooms     = count($rooms);
$availableCount = count(array_filter($rooms, fn($r) => $r['status'] === 'available'));
$occupiedCount  = count(array_filter($rooms, fn($r) => $r['status'] === 'occupied'));

$pageTitle = "Rooms";
include '../includes/header.php';
?>
<style>
.rooms-hero{background:linear-gradient(135deg,var(--primary) 0%,#0f2442 100%);border-radius:var(--radius);padding:28px 32px;margin-bottom:24px;display:flex;align-items:center;justify-content:space-between;position:relative;overflow:hidden;}
.rooms-hero::before{content:'🏠';position:absolute;right:32px;top:50%;transform:translateY(-50%);font-size:80px;opacity:0.08;pointer-events:none;}
.rooms-hero h2{color:#fff;font-size:22px;font-weight:800;margin-bottom:4px;}
.rooms-hero p{color:rgba(255,255,255,0.6);font-size:13px;}
.rooms-hero-stats{display:flex;gap:28px;}
.hero-stat{text-align:center;}
.hero-stat .n{font-size:26px;font-weight:800;color:#fff;line-height:1;}
.hero-stat .l{font-size:11px;color:rgba(255,255,255,0.55);margin-top:2px;}
.room-grid-new{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;}
.room-card-new{background:#fff;border-radius:16px;border:1.5px solid var(--border);overflow:hidden;transition:all 0.25s;box-shadow:var(--shadow-sm);display:flex;flex-direction:column;}
.room-card-new:hover{transform:translateY(-4px);box-shadow:var(--shadow-lg);border-color:var(--primary);}
.photo-wrap{position:relative;}
.room-photo{width:100%;height:180px;object-fit:cover;display:block;background:#f0f4f8;}
.room-photo-placeholder{width:100%;height:180px;background:linear-gradient(135deg,#e8f0f9 0%,#dde8f5 100%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;color:var(--text-muted);}
.room-photo-placeholder .ph-icon{font-size:40px;opacity:0.4;}
.room-photo-placeholder .ph-text{font-size:12px;font-weight:600;opacity:0.5;}
.room-card-body{padding:18px 20px;flex:1;}
.room-type-tag{display:inline-flex;align-items:center;gap:4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:var(--text-muted);background:var(--bg);padding:3px 8px;border-radius:6px;border:1px solid var(--border);margin-bottom:10px;}
.room-address{font-size:15px;font-weight:800;color:var(--primary);margin-bottom:4px;}
.room-price-new{font-size:22px;font-weight:800;color:var(--accent);margin-bottom:2px;}
.room-price-new span{font-size:12px;color:var(--text-muted);font-weight:400;}
.room-desc{font-size:12px;color:var(--text-muted);line-height:1.6;margin:8px 0;min-height:36px;}
.room-meta{display:flex;align-items:center;gap:12px;font-size:12px;color:var(--text-muted);padding-top:10px;border-top:1px solid var(--border);margin-top:10px;}
.room-actions{display:flex;gap:8px;padding:0 20px 18px;}
.room-actions .btn{flex:1;justify-content:center;}
.status-ribbon{position:absolute;top:12px;left:12px;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;backdrop-filter:blur(4px);}
.ribbon-available{background:rgba(34,160,107,0.9);color:#fff;}
.ribbon-occupied{background:rgba(229,62,62,0.9);color:#fff;}
.ribbon-maintenance{background:rgba(232,160,32,0.9);color:#fff;}
.empty-rooms{grid-column:1/-1;text-align:center;padding:80px 20px;background:#fff;border-radius:16px;border:2px dashed var(--border);}

/* ── FILE UPLOAD WIDGET ── */
.upload-zone {
  border: 2px dashed var(--border);
  border-radius: 10px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.2s;
  background: var(--bg);
  position: relative;
}
.upload-zone:hover, .upload-zone.dragover {
  border-color: var(--primary);
  background: #f0f5fb;
}
.upload-zone input[type=file] {
  position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
}
.upload-zone .uz-icon { font-size: 32px; margin-bottom: 6px; }
.upload-zone .uz-text { font-size: 13px; font-weight: 600; color: var(--text-muted); }
.upload-zone .uz-sub  { font-size: 11px; color: var(--text-muted); margin-top: 2px; }
.upload-preview {
  margin-top: 10px; border-radius: 8px; overflow: hidden;
  display: none; position: relative;
}
.upload-preview img { width: 100%; height: 160px; object-fit: cover; display: block; }
.upload-preview .remove-preview {
  position: absolute; top: 6px; right: 6px;
  background: rgba(0,0,0,0.6); color: #fff; border: none; border-radius: 6px;
  padding: 3px 8px; font-size: 11px; cursor: pointer; font-weight: 600;
}
.current-photo {
  margin-bottom: 10px; border-radius: 8px; overflow: hidden;
}
.current-photo img { width: 100%; height: 120px; object-fit: cover; display: block; border-radius: 8px; }
.current-photo .cp-label {
  font-size: 11px; color: var(--text-muted); margin-bottom: 4px; font-weight: 600;
}
</style>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>
  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title"><h1>🏠 Room Management</h1><p>Manage all boarding house rooms</p></div>
      <div class="topbar-right">
        <button class="btn btn-primary" onclick="openModal('addRoomModal')">➕ Add Room</button>
      </div>
    </div>
    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>

      <div class="rooms-hero">
        <div><h2>Room Overview</h2><p>All boarding house rooms at a glance</p></div>
        <div class="rooms-hero-stats">
          <div class="hero-stat"><div class="n"><?= $totalRooms ?></div><div class="l">Total</div></div>
          <div class="hero-stat"><div class="n"><?= $availableCount ?></div><div class="l">Available</div></div>
          <div class="hero-stat"><div class="n"><?= $occupiedCount ?></div><div class="l">Occupied</div></div>
        </div>
      </div>

      <div class="search-bar" style="margin-bottom:20px;">
        <div class="search-input-wrap">
          <span class="icon">🔍</span>
          <form method="GET" style="display:contents;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search room number…">
          </form>
        </div>
        <div style="display:flex;gap:8px;">
          <a href="rooms.php"           class="btn btn-sm <?= !$filter?'btn-primary':'btn-outline' ?>">All</a>
          <a href="?status=available"   class="btn btn-sm <?= $filter==='available'?'btn-primary':'btn-outline' ?>">✅ Available</a>
          <a href="?status=occupied"    class="btn btn-sm <?= $filter==='occupied'?'btn-primary':'btn-outline' ?>">🔴 Occupied</a>
          <a href="?status=maintenance" class="btn btn-sm <?= $filter==='maintenance'?'btn-primary':'btn-outline' ?>">🔧 Maintenance</a>
        </div>
      </div>

      <div class="room-grid-new">
        <?php if (!empty($rooms)): foreach ($rooms as $r):
          $ribbonClass = ['available'=>'ribbon-available','occupied'=>'ribbon-occupied','maintenance'=>'ribbon-maintenance'][$r['status']] ?? '';
          $ribbonLabel = ['available'=>'✅ Available','occupied'=>'🔴 Occupied','maintenance'=>'🔧 Maintenance'][$r['status']] ?? htmlspecialchars($r['status']);
        ?>
        <div class="room-card-new">
          <div class="photo-wrap">
            <span class="status-ribbon <?= $ribbonClass ?>"><?= $ribbonLabel ?></span>
            <?php if (!empty($r['photo_url'])): ?>
              <img src="<?= htmlspecialchars($r['photo_url']) ?>" alt="Room photo" class="room-photo"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">
              <div class="room-photo-placeholder" style="display:none;"><div class="ph-icon">🏠</div><div class="ph-text">No Photo</div></div>
            <?php else: ?>
              <div class="room-photo-placeholder"><div class="ph-icon">🏠</div><div class="ph-text">No Photo Added</div></div>
            <?php endif; ?>
          </div>
          <div class="room-card-body">
            <div class="room-type-tag"><?= ['Single'=>'🛏️','Double'=>'🛏️🛏️','Studio'=>'🏡','Shared'=>'👥'][$r['type']] ?? '🏠' ?> <?= htmlspecialchars($r['type']) ?></div>
            <div class="room-address">Room <?= htmlspecialchars($r['room_number']) ?></div>
            <div class="room-price-new">₱<?= number_format($r['price'],2) ?> <span>/ month</span></div>
            <div class="room-desc"><?= $r['description'] ? htmlspecialchars($r['description']) : '<em style="opacity:.4;">No description.</em>' ?></div>
            <div class="room-meta"><span>👤 <?= $r['capacity'] ?> person<?= $r['capacity']>1?'s':'' ?></span></div>
          </div>
          <div class="room-actions">
            <button class="btn btn-outline btn-sm" onclick='editRoom(<?= htmlspecialchars(json_encode($r),ENT_QUOTES) ?>)'>✏️ Edit</button>
            <form method="POST" onsubmit="return confirm('Delete this room?')" style="flex:1;">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= $r['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" style="width:100%;justify-content:center;">🗑️ Delete</button>
            </form>
          </div>
        </div>
        <?php endforeach; else: ?>
        <div class="empty-rooms">
          <div style="font-size:56px;margin-bottom:16px;">🏠</div>
          <h3 style="margin-bottom:8px;">No rooms found</h3>
          <p class="text-muted" style="margin-bottom:20px;">Start by adding your first boarding room.</p>
          <button class="btn btn-primary" onclick="openModal('addRoomModal')">➕ Add Room</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ADD ROOM MODAL -->
<div class="modal-overlay" id="addRoomModal">
  <div class="modal">
    <div class="modal-header"><h3>🏠 Add New Room</h3><button class="btn btn-icon btn-outline" onclick="closeModal('addRoomModal')">✕</button></div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="add">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">📍 Room Number *</label>
          <input type="text" name="address" class="form-control" placeholder="e.g. 101" required>
        </div>

        <!-- PHOTO UPLOAD -->
        <div class="form-group">
          <label class="form-label">🖼️ Room Photo</label>
          <div class="upload-zone" id="addUploadZone" onclick="document.getElementById('addPhotoFile').click()">
            <input type="file" name="photo_file" id="addPhotoFile" accept="image/*"
                   onchange="previewPhoto(this,'addPreview','addUploadZone')">
            <div class="uz-icon">📷</div>
            <div class="uz-text">Click to browse or drag & drop</div>
            <div class="uz-sub">JPG, PNG, WEBP up to 5 MB</div>
          </div>
          <div class="upload-preview" id="addPreview">
            <img id="addPreviewImg" src="" alt="Preview">
            <button type="button" class="remove-preview" onclick="clearPhoto('addPhotoFile','addPreview','addUploadZone')">✕ Remove</button>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Room Type *</label>
            <select name="type" class="form-control" required>
              <option value="">Select type</option>
              <option value="Single">🛏️ Single</option>
              <option value="Double">🛏️ Double</option>
              <option value="Studio">🏡 Studio</option>
              <option value="Shared">👥 Shared</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Capacity *</label>
            <input type="number" name="capacity" class="form-control" placeholder="1" min="1" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Monthly Price (₱) *</label>
          <input type="number" name="price" class="form-control" placeholder="3500" step="0.01" min="0.01" required>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Amenities, features…"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('addRoomModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Room</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT ROOM MODAL -->
<div class="modal-overlay" id="editRoomModal">
  <div class="modal">
    <div class="modal-header"><h3>✏️ Edit Room</h3><button class="btn btn-icon btn-outline" onclick="closeModal('editRoomModal')">✕</button></div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="id" id="edit_id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">📍 Room Number *</label>
          <input type="text" name="address" id="edit_room_number" class="form-control" required>
        </div>

        <!-- PHOTO UPLOAD -->
        <div class="form-group">
          <label class="form-label">🖼️ Room Photo</label>
          <div id="editCurrentPhoto" class="current-photo" style="display:none;">
            <div class="cp-label">Current photo:</div>
            <img id="editCurrentImg" src="" alt="Current photo">
          </div>
          <div class="upload-zone" id="editUploadZone" onclick="document.getElementById('editPhotoFile').click()">
            <input type="file" name="photo_file" id="editPhotoFile" accept="image/*"
                   onchange="previewPhoto(this,'editPreview','editUploadZone')">
            <div class="uz-icon">📷</div>
            <div class="uz-text" id="editUploadText">Click to replace photo or drag & drop</div>
            <div class="uz-sub">JPG, PNG, WEBP up to 5 MB</div>
          </div>
          <div class="upload-preview" id="editPreview">
            <img id="editPreviewImg" src="" alt="Preview">
            <button type="button" class="remove-preview" onclick="clearPhoto('editPhotoFile','editPreview','editUploadZone')">✕ Remove</button>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Room Type</label>
            <select name="type" id="edit_type" class="form-control">
              <option value="Single">🛏️ Single</option>
              <option value="Double">🛏️ Double</option>
              <option value="Studio">🏡 Studio</option>
              <option value="Shared">👥 Shared</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Capacity</label>
            <input type="number" name="capacity" id="edit_capacity" class="form-control" min="1">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Monthly Price (₱)</label>
            <input type="number" name="price" id="edit_price" class="form-control" step="0.01" min="0.01">
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="status" id="edit_status" class="form-control">
              <option value="available">✅ Available</option>
              <option value="occupied">🔴 Occupied</option>
              <option value="maintenance">🔧 Maintenance</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('editRoomModal')">Cancel</button>
        <button type="submit" class="btn btn-primary">Update Room</button>
      </div>
    </form>
  </div>
</div>

<script>
function editRoom(room) {
  document.getElementById('edit_id').value          = room.id;
  document.getElementById('edit_room_number').value = room.room_number;
  document.getElementById('edit_type').value        = room.type;
  document.getElementById('edit_price').value       = room.price;
  document.getElementById('edit_capacity').value    = room.capacity;
  document.getElementById('edit_status').value      = room.status;
  document.getElementById('edit_description').value = room.description || '';

  // Show current photo if exists
  const curPhotoWrap = document.getElementById('editCurrentPhoto');
  const curImg       = document.getElementById('editCurrentImg');
  if (room.photo_url) {
    curImg.src = room.photo_url;
    curPhotoWrap.style.display = 'block';
    document.getElementById('editUploadText').textContent = 'Click to replace photo or drag & drop';
  } else {
    curPhotoWrap.style.display = 'none';
    document.getElementById('editUploadText').textContent = 'Click to add a photo or drag & drop';
  }

  // Reset new upload preview
  clearPhoto('editPhotoFile', 'editPreview', 'editUploadZone');
  openModal('editRoomModal');
}

function previewPhoto(input, previewId, zoneId) {
  const file = input.files[0];
  if (!file) return;

  const preview    = document.getElementById(previewId);
  const previewImg = preview.querySelector('img');
  const zone       = document.getElementById(zoneId);

  const reader = new FileReader();
  reader.onload = e => {
    previewImg.src = e.target.result;
    preview.style.display = 'block';
    zone.style.borderStyle = 'solid';
    zone.style.borderColor = 'var(--primary)';
  };
  reader.readAsDataURL(file);
}

function clearPhoto(inputId, previewId, zoneId) {
  document.getElementById(inputId).value = '';
  const preview = document.getElementById(previewId);
  preview.style.display = 'none';
  preview.querySelector('img').src = '';
  const zone = document.getElementById(zoneId);
  zone.style.borderStyle = 'dashed';
  zone.style.borderColor = 'var(--border)';
}

// Drag and drop support
['addUploadZone','editUploadZone'].forEach(id => {
  const zone = document.getElementById(id);
  if (!zone) return;
  zone.addEventListener('dragover',  e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', e => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault();
    zone.classList.remove('dragover');
    const inputId  = id === 'addUploadZone' ? 'addPhotoFile' : 'editPhotoFile';
    const prevId   = id === 'addUploadZone' ? 'addPreview'   : 'editPreview';
    const input    = document.getElementById(inputId);
    const dt       = new DataTransfer();
    dt.items.add(e.dataTransfer.files[0]);
    input.files    = dt.files;
    previewPhoto(input, prevId, id);
  });
});
</script>

<?php include '../includes/footer.php'; ?>
