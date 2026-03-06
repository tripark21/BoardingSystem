<?php
require_once '../config/database.php';
requireTenant();

$msg = ''; $msgType = '';
$user_id = $_SESSION['user_id'];

// Get admin user
$admin = dbOne($conn, "SELECT id FROM users WHERE role = 'admin' LIMIT 1");
$admin_id = $admin['id'] ?? null;

if (!$admin_id) {
    die("Error: Admin user not found");
}

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $subject = cleanInput($_POST['subject'] ?? '');
        $message = cleanInput($_POST['message'] ?? '');

        if ($subject && $message) {
            try {
                dbExec($conn,
                    "INSERT INTO messages (sender_id, receiver_id, subject, message) 
                     VALUES (?, ?, ?, ?)",
                    [$user_id, $admin_id, $subject, $message]
                );
                $msg = "✅ Message sent to admin!";
                $msgType = 'success';
                // Redirect to refresh and show the new message
                header("Location: ?");
                exit;
            } catch (Exception $e) {
                $msg = "Error sending message: " . $e->getMessage();
                $msgType = 'danger';
            }
        } else {
            $msg = "Please fill in all fields.";
            $msgType = 'warning';
        }
    }

    if ($action === 'mark_read') {
        $msg_id = intval($_POST['msg_id'] ?? 0);
        try {
            dbExec($conn,
                "UPDATE messages SET is_read = TRUE WHERE id = ?",
                [$msg_id]
            );
        } catch (Exception $e) {
            $msg = "Error: " . $e->getMessage();
            $msgType = 'danger';
        }
    }

    if ($action === 'archive_conversation') {
        try {
            dbExec($conn,
                "UPDATE messages SET is_archived = TRUE 
                 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
                [$user_id, $admin_id, $admin_id, $user_id]
            );
            $msg = "✅ Conversation archived successfully!";
            $msgType = 'success';
            header("Location: messages.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error archiving conversation: " . $e->getMessage();
            $msgType = 'danger';
        }
    }

    if ($action === 'delete_conversation') {
        try {
            dbExec($conn,
                "DELETE FROM messages 
                 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
                [$user_id, $admin_id, $admin_id, $user_id]
            );
            $msg = "✅ Conversation deleted successfully!";
            $msgType = 'success';
            header("Location: messages.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error deleting conversation: " . $e->getMessage();
            $msgType = 'danger';
        }
    }

    if ($action === 'delete_message') {
        $msg_id = intval($_POST['msg_id'] ?? 0);
        try {
            dbExec($conn,
                "DELETE FROM messages WHERE id = ?",
                [$msg_id]
            );
            $msg = "✅ Message deleted successfully!";
            $msgType = 'success';
            header("Location: messages.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error deleting message: " . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Get all messages with admin
$messages = dbAll($conn,
    "SELECT m.*, u_sender.username as sender_name
     FROM messages m
     JOIN users u_sender ON m.sender_id = u_sender.id
     WHERE 
        ((m.sender_id = ? AND m.receiver_id = ?) OR
         (m.sender_id = ? AND m.receiver_id = ?))
     AND m.is_archived = FALSE
     ORDER BY m.created_at ASC",
    [$user_id, $admin_id, $admin_id, $user_id]
);

// Mark unread admin messages as read
dbExec($conn,
    "UPDATE messages SET is_read = TRUE 
     WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE",
    [$user_id, $admin_id]
);

$pageTitle = "Messages";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/tenant_navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>💬 Messages with Admin</h1>
        <p>Get help and stay updated</p>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <div style="max-width: 800px; margin: 0 auto;">
        <!-- MESSAGES DISPLAY -->
        <div class="card" style="margin-bottom: 20px; max-height: 400px; overflow-y: auto;" data-conversation>
          <div class="card-header" style="display: flex; align-items: center; justify-content: space-between;">
            <h3 style="margin: 0;">💬 Conversation</h3>
            <div style="display: flex; gap: 8px;">
              <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this conversation?');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="archive_conversation">
                <button type="submit" class="btn btn-secondary" style="font-size: 12px; padding: 6px 10px;">
                  📦 Archive
                </button>
              </form>
              <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this conversation? This cannot be undone.');">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete_conversation">
                <button type="submit" class="btn btn-danger" style="font-size: 12px; padding: 6px 10px;">
                  🗑️ Delete
                </button>
              </form>
            </div>
          </div>
          <div class="card-body" style="padding: 20px;">
            <?php if (!empty($messages)): ?>
              <?php foreach ($messages as $m): 
                $isSent = $m['sender_id'] == $user_id;
              ?>
              <div style="margin-bottom: 16px; display: flex; <?= $isSent ? 'justify-content: flex-end;' : '' ?>" class="message-item">
                <div style="max-width: 70%; background: <?= $isSent ? 'linear-gradient(135deg, var(--primary), var(--primary-light))' : '#f0f3f8' ?>; color: <?= $isSent ? 'white' : 'var(--text)' ?>; padding: 12px 16px; border-radius: 12px; border: 1px solid <?= $isSent ? 'transparent' : 'var(--border-light)' ?>; box-shadow: <?= $isSent ? 'var(--shadow-sm)' : 'none' ?>;">
                  <div style="font-size: 12px; font-weight: 700; margin-bottom: 6px; opacity: 0.9; text-transform: uppercase; letter-spacing: 0.5px;">
                    <?= htmlspecialchars($m['subject']) ?>
                  </div>
                  <div style="font-size: 14px; line-height: 1.6; margin-bottom: 6px;">
                    <?= htmlspecialchars($m['message']) ?>
                  </div>
                  <div style="font-size: 11px; opacity: 0.7;">
                    <?= date('M d, Y H:i', strtotime($m['created_at'])) ?>
                    <?php if ($isSent): ?>
                      <span style="margin-left: 8px;">📤</span>
                    <?php endif; ?>
                  </div>
                </div>
                <?php if ($isSent): ?>
                <div style="display: none;" class="message-actions">
                  <form method="POST" style="display: inline; margin-left: 8px;" onsubmit="return confirm('Delete this message?');">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                    <button type="submit" style="background: none; border: none; cursor: pointer; font-size: 14px; padding: 4px 8px;" title="Delete message">
                      🗑️
                    </button>
                  </form>
                </div>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align: center; color: var(--text-muted); padding: 40px 20px;">
              <div style="font-size: 40px; margin-bottom: 12px;">💬</div>
              <p>No messages yet. Send your first message to the admin!</p>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- SEND MESSAGE FORM -->
        <div class="card" style="border-color: var(--primary);">
          <div class="card-header">
            <h3>Send Message to Admin</h3>
          </div>
          <div class="card-body">
            <form method="POST" data-message-form>
              <?= csrfField() ?>
              <input type="hidden" name="action" value="send_message">
              
              <div class="form-group">
                <label class="form-label">Subject *</label>
                <input type="text" name="subject" placeholder="e.g. Question about billing" 
                       class="form-control" required value="">
              </div>

              <div class="form-group">
                <label class="form-label">Message *</label>
                <textarea name="message" placeholder="Write your message here..."
                         class="form-control" rows="4" style="resize: vertical;" required></textarea>
              </div>

              <button type="submit" class="btn btn-primary" style="width: 100%;">
                📤 Send Message
              </button>
            </form>
          </div>
        </div>

        <!-- QUICK TIPS -->
        <div class="card" style="margin-top: 20px; background: linear-gradient(135deg, #e8f0f9, #f0f5fb); border-color: var(--primary);">
          <div class="card-body">
            <div style="font-size: 13px; color: var(--text-muted); line-height: 1.8;">
              <strong style="color: var(--primary);">💡 Tips:</strong><br>
              • Use clear subject lines so admin knows what your message is about<br>
              • For billing issues, include your room number<br>
              • Check back regularly for admin responses
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>

<script>
// Show delete button on message hover
document.querySelectorAll('.message-item').forEach(item => {
  item.addEventListener('mouseenter', function() {
    const actions = this.querySelector('.message-actions');
    if (actions) actions.style.display = 'block';
  });
  item.addEventListener('mouseleave', function() {
    const actions = this.querySelector('.message-actions');
    if (actions) actions.style.display = 'none';
  });
});

// Scroll messages to bottom on load
function scrollToBottom() {
  const conv = document.querySelector('[data-conversation]');
  if (conv) {
    setTimeout(() => {
      conv.scrollTop = conv.scrollHeight;
    }, 100);
  }
}

// Initial scroll on page load
document.addEventListener('DOMContentLoaded', scrollToBottom);

// Auto-refresh messages every 2 seconds
setInterval(function() {
  fetch(window.location.pathname)
    .then(response => response.text())
    .then(html => {
      const parser = new DOMParser();
      const newDoc = parser.parseFromString(html, 'text/html');
      const newConversation = newDoc.querySelector('[data-conversation]');
      const oldConversation = document.querySelector('[data-conversation]');
      
      if (newConversation && oldConversation) {
        const oldHTML = oldConversation.innerHTML;
        const newHTML = newConversation.innerHTML;
        
        if (oldHTML !== newHTML) {
          oldConversation.innerHTML = newHTML;
          scrollToBottom();
        }
      }
    })
    .catch(err => console.error('Auto-refresh error:', err));
}, 2000);

// Form submission handler
document.addEventListener('DOMContentLoaded', function() {
  const form = document.querySelector('[data-message-form]');
  if (form) {
    form.addEventListener('submit', function(e) {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = '⏳ Sending...';
        submitBtn.style.opacity = '0.6';
      }
    });
  }
});
</script>
