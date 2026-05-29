<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Messages';

if (!isLoggedIn() || !isOfficer()) redirect('../login.php');

$uid = (int)$_SESSION['user_id'];

// Look up admin user id
$adminRes = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
$admin_id = (int)$adminRes->fetch_assoc()['id'];

// POST handler (PRG pattern — before GET/render)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['message_body'] ?? '');
    if ($body === '') {
        $_SESSION['msg_error'] = 'Message cannot be empty.';
        redirect('messages.php');
    }
    $body = $conn->real_escape_string($body);
    $conn->query("INSERT INTO messages (sender_id, receiver_id, message_body, context_type)
                  VALUES ($uid, $admin_id, '$body', 'general')");
    redirect('messages.php');
}

// GET handler — fetch thread and mark messages as read
$messages = $conn->query("
    SELECT m.*, u.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = $uid AND m.receiver_id = $admin_id)
       OR (m.sender_id = $admin_id AND m.receiver_id = $uid)
    ORDER BY m.created_at ASC
");

// Mark own unread messages (from admin) as read
$conn->query("UPDATE messages SET is_read=1
              WHERE receiver_id=$uid AND sender_id=$admin_id AND is_read=0");

include '../includes/header.php';
?>

<div class="page-hero py-4">
    <div class="container">
        <a href="dashboard.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        <div class="d-flex align-items-center gap-3">
            <div style="width:55px;height:55px;background:var(--orange);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">💬</div>
            <div>
                <h1 style="font-size:1.8rem;">Messages</h1>
                <p class="mb-0" style="opacity:0.8;">Your conversation with IRCTC Administration</p>
            </div>
        </div>
    </div>
</div>

<div class="container py-4">
    <div class="row g-4">

        <!-- SIDEBAR -->
        <div class="col-lg-3">
            <div class="sidebar-nav">
                <div class="sidebar-header"><i class="fas fa-clipboard-check me-2"></i>Officer Panel</div>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
                <a href="complaints.php"><i class="fas fa-file-alt fa-fw me-2"></i>Complaint Verification</a>
                <a href="messages.php" class="active"><i class="fas fa-comments fa-fw me-2"></i>Messages (Admin)
                    <?php $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id AND is_read=0")->fetch_assoc()['c'];
                    if ($ucAdmin > 0): ?>
                    <span class="badge bg-danger ms-auto"><?= $ucAdmin ?></span>
                    <?php endif; ?>
                </a>
                <a href="vendor_chat.php"><i class="fas fa-store fa-fw me-2"></i>Message Vendors
                    <?php $ucVendors = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='vendor' AND m.is_read=0")->fetch_assoc()['c'];
                    if ($ucVendors > 0): ?><span class="badge bg-danger ms-auto"><?= $ucVendors ?></span><?php endif; ?>
                </a>
                <hr style="margin:8px;">
                <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
            </div>
        </div>

        <!-- CHAT THREAD -->
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header-blue d-flex align-items-center gap-2">
                    <i class="fas fa-comments me-1"></i>
                    Conversation with Admin
                </div>

                <!-- MESSAGE BUBBLES -->
                <div class="p-4" style="min-height:350px;max-height:520px;overflow-y:auto;background:#f8faff;" id="chatThread">
                    <?php if ($messages->num_rows === 0): ?>
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-comments fa-3x mb-3" style="opacity:0.3;"></i>
                        <p>No messages yet. Start the conversation below.</p>
                    </div>
                    <?php else: while ($msg = $messages->fetch_assoc()):
                        $isOwn = ((int)$msg['sender_id'] === $uid);
                    ?>
                    <div class="d-flex mb-3 <?= $isOwn ? 'justify-content-end' : 'justify-content-start' ?>">
                        <div style="max-width:70%;">
                            <div class="d-flex align-items-center gap-2 mb-1 <?= $isOwn ? 'justify-content-end' : '' ?>">
                                <small class="text-muted fw-600" style="font-size:0.75rem;">
                                    <?= htmlspecialchars($msg['sender_name']) ?>
                                </small>
                                <small class="text-muted" style="font-size:0.72rem;">
                                    <?= date('d M Y, H:i', strtotime($msg['created_at'])) ?>
                                </small>
                            </div>
                            <div style="
                                padding: 10px 16px;
                                border-radius: <?= $isOwn ? '14px 14px 4px 14px' : '14px 14px 14px 4px' ?>;
                                background: <?= $isOwn ? 'linear-gradient(135deg,#003580,#0066cc)' : '#fff' ?>;
                                color: <?= $isOwn ? '#fff' : '#222' ?>;
                                box-shadow: 0 2px 8px rgba(0,0,0,0.10);
                                font-size: 0.88rem;
                                word-break: break-word;
                            ">
                                <?= nl2br(htmlspecialchars($msg['message_body'])) ?>
                                <?php if (!empty($msg['context_id'])): ?>
                                <?php
                                    $cr = $conn->query("SELECT complaint_code, subject FROM complaints WHERE id=" . (int)$msg['context_id']);
                                    $crow = $cr ? $cr->fetch_assoc() : null;
                                ?>
                                <div class="mt-2 pt-2" style="border-top:1px solid <?= $isOwn ? 'rgba(255,255,255,0.3)' : '#eee' ?>;">
                                    <span class="badge <?= $isOwn ? 'bg-light text-primary' : 'bg-primary' ?>" style="font-size:0.72rem;">
                                        <i class="fas fa-link me-1"></i>
                                        <?= $crow ? htmlspecialchars($crow['complaint_code']) . ' — ' . htmlspecialchars(substr($crow['subject'],0,40)) : 'Complaint #'.(int)$msg['context_id'] ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; endif; ?>
                </div>

                <!-- REPLY FORM -->
                <div class="p-4 border-top" style="background:#fff;">
                    <form method="POST" class="d-flex gap-2 align-items-end">
                        <div class="flex-grow-1">
                            <label class="form-label mb-1" for="message_body">Reply to Admin</label>
                            <textarea
                                name="message_body"
                                id="message_body"
                                class="form-control"
                                rows="3"
                                placeholder="Type your message here..."
                                required
                            ></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary px-4" style="height:fit-content;">
                            <i class="fas fa-paper-plane me-1"></i>Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Auto-scroll chat thread to bottom
const thread = document.getElementById('chatThread');
if (thread) thread.scrollTop = thread.scrollHeight;
</script>

<?php include '../includes/footer.php'; ?>
