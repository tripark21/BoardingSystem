<?php
require_once '../config/database.php';
requireAdmin();

$msg = ''; $msgType = '';
$selected_tenant_id = null;
$conversation = [];

// Handle sending message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'send_message') {
        $receiver_id = intval($_POST['receiver_id'] ?? 0);
        $subject = cleanInput($_POST['subject'] ?? '');
        $message = cleanInput($_POST['message'] ?? '');

        if ($receiver_id && $subject && $message) {
            try {
                dbExec($conn,
                    "INSERT INTO messages (sender_id, receiver_id, subject, message) 
                     VALUES (?, ?, ?, ?)",
                    [$_SESSION['user_id'], $receiver_id, $subject, $message]
                );
                $msg = "✅ Message sent successfully!";
                $msgType = 'success';
                // Redirect to same conversation after sending
                header("Location: ?tenant_id=" . $receiver_id);
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
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        try {
            dbExec($conn,
                "UPDATE messages SET is_archived = TRUE 
                 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
                [$_SESSION['user_id'], $tenant_id, $tenant_id, $_SESSION['user_id']]
            );
            $msg = "✅ Conversation archived successfully!";
            $msgType = 'success';
            $selected_tenant_id = null;
            $conversation = [];
            header("Location: messages.php");
            exit;
        } catch (Exception $e) {
            $msg = "Error archiving conversation: " . $e->getMessage();
            $msgType = 'danger';
        }
    }

    if ($action === 'delete_conversation') {
        $tenant_id = intval($_POST['tenant_id'] ?? 0);
        try {
            dbExec($conn,
                "DELETE FROM messages 
                 WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)",
                [$_SESSION['user_id'], $tenant_id, $tenant_id, $_SESSION['user_id']]
            );
            $msg = "✅ Conversation deleted successfully!";
            $msgType = 'success';
            $selected_tenant_id = null;
            $conversation = [];
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
            header("Location: ?tenant_id=" . $_POST['tenant_id']);
            exit;
        } catch (Exception $e) {
            $msg = "Error deleting message: " . $e->getMessage();
            $msgType = 'danger';
        }
    }
}

// Get selected tenant from GET/POST
if (isset($_GET['tenant_id'])) {
    $selected_tenant_id = intval($_GET['tenant_id']);
}

// Get all tenant users
$tenants = dbAll($conn,
    "SELECT u.id, u.username, t.full_name, t.email, COUNT(m.id) as unread_count
     FROM users u
     JOIN tenants t ON u.id = t.user_id
     LEFT JOIN messages m ON (m.receiver_id = u.id AND m.is_read = FALSE AND m.sender_id = ? )
     GROUP BY u.id, u.username, t.full_name, t.email
     ORDER BY unread_count DESC, t.full_name ASC",
    [$_SESSION['user_id']]
);

// Get conversation with selected tenant
if ($selected_tenant_id) {
    $conversation = dbAll($conn,
        "SELECT m.*, u_sender.username as sender_name, u_receiver.username as receiver_name
         FROM messages m
         JOIN users u_sender ON m.sender_id = u_sender.id
         JOIN users u_receiver ON m.receiver_id = u_receiver.id
         WHERE (
            (m.sender_id = ? AND m.receiver_id = ?) OR
            (m.sender_id = ? AND m.receiver_id = ?)
         )
         AND m.is_archived = FALSE
         ORDER BY m.created_at DESC
         LIMIT 50",
        [$_SESSION['user_id'], $selected_tenant_id, $selected_tenant_id, $_SESSION['user_id']]
    );
    $conversation = array_reverse($conversation); // Show oldest first
    
    // Mark unread messages as read
    dbExec($conn,
        "UPDATE messages SET is_read = TRUE 
         WHERE receiver_id = ? AND sender_id = ? AND is_read = FALSE",
        [$_SESSION['user_id'], $selected_tenant_id]
    );
}

// Get selected tenant info
$selected_tenant = null;
if ($selected_tenant_id) {
    $selected_tenant = dbOne($conn,
        "SELECT u.id, u.username, t.full_name, t.email, t.phone, r.room_number 
         FROM users u
         JOIN tenants t ON u.id = t.user_id
         LEFT JOIN rooms r ON t.room_id = r.id
         WHERE u.id = ?",
        [$selected_tenant_id]
    );
}

$pageTitle = "Messages";
include '../includes/header.php';
?>

<div class="app-wrapper">
  <?php include '../includes/navbar.php'; ?>

  <div class="main-content">
    <div class="topbar">
      <div class="topbar-title">
        <h1>💬 Messages</h1>
        <p>Communicate with tenants</p>
      </div>
    </div>

    <div class="page-body">
      <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
      <?php endif; ?>

      <div style="display: grid; grid-template-columns: 280px 1fr; gap: 20px; min-height: calc(100vh - 200px);">
        <!-- LEFT: TENANT LIST -->
        <div class="card" style="overflow-y: auto; display: flex; flex-direction: column;">
          <div class="card-header" style="flex-shrink: 0;">
            <h3>💬 Tenants (<?= count($tenants) ?>)</h3>
          </div>
          <div class="card-body" style="padding: 0; flex: 1; overflow-y: auto;">
            <?php if (!empty($tenants)): ?>
              <?php foreach ($tenants as $tenant): ?>
              <a href="?tenant_id=<?= $tenant['id'] ?>" 
                 style="display: flex; align-items: center; gap: 10px; padding: 12px 14px; border-bottom: 1px solid var(--border-light); text-decoration: none; transition: all 0.2s; background: <?= $selected_tenant_id == $tenant['id'] ? 'var(--bg-dark)' : 'transparent' ?>; border-left: 4px solid <?= $selected_tenant_id == $tenant['id'] ? 'var(--primary)' : 'transparent' ?>"
                 onmouseover="this.style.background = 'var(--bg-dark)'; this.style.borderLeftColor = 'var(--primary)'" 
                 onmouseout="this.style.background = '<?= $selected_tenant_id == $tenant['id'] ? 'var(--bg-dark)' : 'transparent' ?>'; this.style.borderLeftColor = '<?= $selected_tenant_id == $tenant['id'] ? 'var(--primary)' : 'transparent' ?>'">
                <div style="flex: 1; min-width: 0;">
                  <div style="font-weight: 600; font-size: 13px; color: var(--text); margin-bottom: 2px;">
                    <?= htmlspecialchars($tenant['full_name']) ?>
                  </div>
                  <div style="font-size: 11px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                    @<?= htmlspecialchars($tenant['username']) ?>
                  </div>
                </div>
                <?php if ($tenant['unread_count'] > 0): ?>
                <span style="background: var(--danger); color: #fff; padding: 4px 8px; border-radius: 20px; font-size: 10px; font-weight: 700; flex-shrink: 0;">
                  <?= $tenant['unread_count'] ?>
                </span>
                <?php endif; ?>
              </a>
              <?php endforeach; ?>
            <?php else: ?>
            <div style="padding: 20px; text-align: center; color: var(--text-muted);">
              No tenants available.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- RIGHT: CONVERSATION -->
        <div class="card" style="display: flex; flex-direction: column; overflow: hidden; min-height: 600px;">
          <?php if ($selected_tenant): ?>
            <!-- Tenant Info -->
            <div class="card-header" style="flex-shrink: 0; border-bottom: 2px solid var(--border-light); display: flex; align-items: center; justify-content: space-between;">
              <div style="flex: 1;">
                <h3 style="margin-bottom: 4px; font-size: 16px;">📧 <?= htmlspecialchars($selected_tenant['full_name']) ?></h3>
                <p style="font-size: 12px; color: var(--text-muted); margin: 0;">
                  <?= htmlspecialchars($selected_tenant['email']) ?> 
                  <?php if ($selected_tenant['phone']): ?>
                    • <?= htmlspecialchars($selected_tenant['phone']) ?>
                  <?php endif; ?>
                  <?php if ($selected_tenant['room_number']): ?>
                    • Room <?= htmlspecialchars($selected_tenant['room_number']) ?>
                  <?php endif; ?>
                </p>
              </div>
              <div style="display: flex; gap: 8px; flex-shrink: 0;">
                <form method="POST" style="display: inline;" onsubmit="return confirm('Archive this conversation?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="archive_conversation">
                  <input type="hidden" name="tenant_id" value="<?= $selected_tenant['id'] ?>">
                  <button type="submit" class="btn btn-secondary" style="font-size: 12px; padding: 6px 10px;">
                    📦 Archive
                  </button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this conversation? This cannot be undone.');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete_conversation">
                  <input type="hidden" name="tenant_id" value="<?= $selected_tenant['id'] ?>">
                  <button type="submit" class="btn btn-danger" style="font-size: 12px; padding: 6px 10px;">
                    🗑️ Delete
                  </button>
                </form>
              </div>
            </div>

            <!-- Messages Display -->
            <div style="flex: 1; overflow-y: auto; padding: 16px; background: var(--bg); scroll-behavior: smooth;" data-conversation>
              <?php if (!empty($conversation)): ?>
                <?php foreach ($conversation as $m): 
                  $isSent = $m['sender_id'] == $_SESSION['user_id'];
                ?>
                <div style="margin-bottom: 16px; display: flex; align-items: flex-end; <?= $isSent ? 'justify-content: flex-end;' : 'justify-content: flex-start;' ?>" class="message-item">
                  <div style="max-width: 65%; background: <?= $isSent ? 'var(--primary)' : '#f5f5f5' ?>; color: <?= $isSent ? 'white' : 'var(--text)' ?>; padding: 12px 14px; border-radius: 14px; border: 1px solid <?= $isSent ? 'var(--primary)' : 'var(--border-light)' ?>; box-shadow: <?= $isSent ? '0 2px 8px rgba(26,60,94,0.2)' : 'none' ?>">
                    <?php if ($m['subject']): ?>
                    <div style="font-size: 12px; font-weight: 700; margin-bottom: 6px; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.3px;">
                      <?= htmlspecialchars($m['subject']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="font-size: 14px; line-height: 1.6; word-wrap: break-word;">
                      <?= nl2br(htmlspecialchars($m['message'])) ?>
                    </div>
                    <div style="font-size: 11px; margin-top: 6px; opacity: 0.7; text-align: right;">
                      <?= date('M d H:i', strtotime($m['created_at'])) ?>
                    </div>
                  </div>
                  <?php if ($isSent): ?>
                  <div style="margin-left: 8px; display: none;" class="message-actions">
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this message?');">
                      <?= csrfField() ?>
                      <input type="hidden" name="action" value="delete_message">
                      <input type="hidden" name="msg_id" value="<?= $m['id'] ?>">
                      <input type="hidden" name="tenant_id" value="<?= $selected_tenant['id'] ?>">
                      <button type="submit" style="background: none; border: none; cursor: pointer; font-size: 14px; padding: 4px 8px;" title="Delete message">
                        🗑️
                      </button>
                    </form>
                  </div>
                  <?php endif; ?>
                </div>
                <?php endforeach; ?>
              <?php else: ?>
              <div style="text-align: center; color: var(--text-muted); margin-top: 80px;">
                <div style="font-size: 48px; margin-bottom: 12px;">💬</div>
                <p style="font-size: 14px;">No messages yet. Start the conversation!</p>
              </div>
              <?php endif; ?>
            </div>

            <!-- Send Message Form -->
            <div style="padding: 16px; background: white; border-top: 1px solid var(--border-light);">
              <form method="POST" data-message-form>
                <?= csrfField() ?>
                <input type="hidden" name="action" value="send_message">
                <input type="hidden" name="receiver_id" value="<?= $selected_tenant['id'] ?>">
                
                <div style="margin-bottom: 10px;">
                  <input type="text" name="subject" placeholder="Subject..." 
                         style="width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; outline: none;"
                         required>
                </div>
                
                <div style="margin-bottom: 10px; display: flex; gap: 8px;">
                  <textarea name="message" placeholder="Type your message..." rows="2"
                            style="flex: 1; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 13px; font-family: inherit; resize: none; outline: none;"
                            required></textarea>
                  <button type="submit" class="btn btn-primary" style="align-self: flex-end; white-space: nowrap;">
                    Send ➤
                  </button>
                </div>
              </form>
            </div>
          <?php else: ?>
          <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); flex-direction: column;">
            <div style="font-size: 64px; margin-bottom: 16px; opacity: 0.3;">💬</div>
            <p style="font-size: 16px; font-weight: 600;">Select a Tenant</p>
            <p style="font-size: 13px; opacity: 0.7;">Click a tenant on the left to view conversation</p>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

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
</script>

<?php include '../includes/footer.php'; ?>

<script>
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

// Auto-refresh conversation every 2 seconds
setInterval(function() {
  const currentTenantId = new URLSearchParams(window.location.search).get('tenant_id');
  if (!currentTenantId) return;
  
  fetch(window.location.pathname + '?tenant_id=' + currentTenantId)
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
