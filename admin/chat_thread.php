<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Chat Thread';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

$admin_id = (int)$_SESSION['user_id'];

// Validate ?with= param
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;
if ($with <= 0) {
    $_SESSION['msg_error'] = 'Invalid user specified.';
    redirect('chat.php');
}

// Verify target user exists and is vendor/officer
$targetRes = $conn->query("SELECT id, name, role FROM users WHERE id=$with AND role IN ('vendor','officer')");
if (!$targetRes || $targetRes->num_rows === 0) {
    $_SESSION['msg_error'] = 'User not found or not a vendor/officer.';
    redirect('chat.php');
}
$target = $targetRes->fetch_assoc();

// Query vendor status if target is a vendor
$vendorStatus = null;
if ($target['role'] === 'vendor') {
    $vsRes = $conn->query("SELECT status FROM vendors WHERE user_id=$with");
    if ($vsRes && $vsRes->num_rows > 0) {
        $vendorStatus = $vsRes->fetch_assoc()['status'];
    }
}

// POST handler — send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['message_body'] ?? '');
    if ($body === '') {
        $_SESSION['msg_error'] = 'Message cannot be empty.';
        redirect("chat_thread.php?with=$with");
    }

    $body = $conn->real_escape_string($body);

    // Optional complaint context
    $ctx_type = 'general';
    $ctx_id_sql = 'NULL';
    if (!empty($_POST['context_id'])) {
        $ctx_id = (int)$_POST['context_id'];
        if ($ctx_id > 0) {
            $ctxRes = $conn->query("SELECT id FROM complaints WHERE id=$ctx_id");
            if ($ctxRes && $ctxRes->num_rows > 0) {
                $ctx_type = 'complaint';
                $ctx_id_sql = $ctx_id;
            }
        }
    }

    $conn->query("INSERT INTO messages (sender_id, receiver_id, message_body, context_type, context_id)
        VALUES ($admin_id, $with, '$body', '$ctx_type', $ctx_id_sql)");

    redirect("chat_thread.php?with=$with");
}

// GET handler — fetch thread and mark read
$messages = $conn->query("
    SELECT m.*, u.name as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id=$admin_id AND m.receiver_id=$with)
       OR (m.sender_id=$with AND m.receiver_id=$admin_id)
    ORDER BY m.created_at ASC
");

$conn->query("UPDATE messages SET is_read=1 WHERE receiver_id=$admin_id AND sender_id=$with AND is_read=0");

// Fetch complaints for context select (last 50)
$complaintsForSelect = $conn->query("SELECT id, complaint_code, subject FROM complaints ORDER BY created_at DESC LIMIT 50");

include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">

<!-- Sidebar -->
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">⚙️ Admin Panel</div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="vendors.php"><i class="fas fa-store fa-fw me-2"></i>Vendors</a>
    <a href="ratings.php"><i class="fas fa-star fa-fw me-2"></i>Ratings</a>
    <a href="complaints.php"><i class="fas fa-file-alt fa-fw me-2"></i>Complaints
      <?php $nc=((int)$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='forwarded_to_admin'")->fetch_assoc()['c']); if($nc>0): ?><span class="badge bg-danger ms-auto"><?= $nc ?></span><?php endif; ?>
    </a>
    <a href="inspections.php"><i class="fas fa-clipboard-check fa-fw me-2"></i>Inspections</a>
    <a href="officers.php"><i class="fas fa-user-shield fa-fw me-2"></i>Officers</a>
    <a href="alerts.php"><i class="fas fa-bell fa-fw me-2"></i>Alerts</a>
    <a href="analytics.php"><i class="fas fa-chart-bar fa-fw me-2"></i>Analytics</a>
    <a href="chat.php" class="active"><i class="fas fa-comments fa-fw me-2"></i>Messages
        <?php $uc = getUnreadMessageCount($admin_id); if ($uc > 0): ?>
        <span class="badge bg-danger ms-auto"><?= $uc ?></span>
        <?php endif; ?>
    </a>
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>

<!-- Main content -->
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="chat.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Messages</a>
    <h3 class="fw-700 mb-0">
        <i class="fas fa-comment-dots me-2"></i>
        Chat with <?= htmlspecialchars($target['name']) ?>
        <?php if ($target['role'] === 'vendor'): ?>
        <span class="badge bg-primary ms-2">Vendor</span>
        <?php else: ?>
        <span class="badge bg-success ms-2">Officer</span>
        <?php endif; ?>
    </h3>
</div>

<?php if (isset($_SESSION['msg_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $_SESSION['msg_success'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg_success']); endif; ?>

<?php if (isset($_SESSION['msg_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $_SESSION['msg_error'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg_error']); endif; ?>

<?php if ($vendorStatus === 'suspended' || $vendorStatus === 'removed'): ?>
<div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
    <i class="fas fa-exclamation-triangle"></i>
    <span>
        This vendor's account is currently <strong><?= htmlspecialchars($vendorStatus) ?></strong>.
        They cannot log in or access the platform.
    </span>
</div>
<?php endif; ?>

<!-- Thread -->
<div class="card mb-3">
<div class="card-header bg-white py-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-comments me-2 text-primary"></i>Conversation</h5>
</div>
<div class="card-body" style="max-height:500px;overflow-y:auto;background:#f8f9fa;" id="thread-container">
<?php if (!$messages || $messages->num_rows === 0): ?>
    <p class="text-center text-muted py-4">No messages yet. Start the conversation below.</p>
<?php else: while ($msg = $messages->fetch_assoc()):
    $isAdmin = ((int)$msg['sender_id'] === $admin_id);
?>
    <div class="d-flex <?= $isAdmin ? 'justify-content-end' : 'justify-content-start' ?> mb-3">
        <div style="max-width:65%;">
            <div class="<?= $isAdmin ? 'bg-primary text-white' : 'bg-white border' ?> rounded-3 px-3 py-2 shadow-sm">
                <div class="fw-semibold small mb-1 <?= $isAdmin ? 'text-white-50' : 'text-muted' ?>">
                    <?= htmlspecialchars($msg['sender_name']) ?>
                </div>
                <div><?= nl2br(htmlspecialchars($msg['message_body'])) ?></div>
                <?php if (!empty($msg['context_id'])): ?>
                <div class="mt-1">
                    <?php
                    $codeRes = $conn->query("SELECT complaint_code FROM complaints WHERE id=" . (int)$msg['context_id']);
                    $codeRow = $codeRes ? $codeRes->fetch_assoc() : null;
                    $codeLabel = $codeRow ? htmlspecialchars($codeRow['complaint_code']) : '#' . (int)$msg['context_id'];
                    ?>
                    <span class="badge <?= $isAdmin ? 'bg-light text-primary' : 'bg-primary' ?> small">
                        <i class="fas fa-link me-1"></i><?= $codeLabel ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            <div class="text-muted small mt-1 <?= $isAdmin ? 'text-end' : '' ?>">
                <?= htmlspecialchars(date('d M Y, H:i', strtotime($msg['created_at']))) ?>
            </div>
        </div>
    </div>
<?php endwhile; endif; ?>
</div>
</div>

<!-- Send form -->
<div class="card">
<div class="card-header bg-white py-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-paper-plane me-2 text-primary"></i>Send Message</h5>
</div>
<div class="card-body">
    <form method="POST" action="chat_thread.php?with=<?= $with ?>">
        <div class="mb-3">
            <label for="message_body" class="form-label fw-semibold">Message</label>
            <textarea name="message_body" id="message_body" class="form-control" rows="4"
                placeholder="Type your message here..." required></textarea>
        </div>
        <div class="mb-3">
            <label for="context_id" class="form-label fw-semibold">
                Link to Complaint <span class="text-muted fw-normal">(optional)</span>
            </label>
            <select name="context_id" id="context_id" class="form-select">
                <option value="">— No complaint context —</option>
                <?php if ($complaintsForSelect): while ($c = $complaintsForSelect->fetch_assoc()): ?>
                <option value="<?= (int)$c['id'] ?>">
                    <?= htmlspecialchars($c['complaint_code']) ?> — <?= htmlspecialchars($c['subject']) ?>
                </option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-2"></i>Send
        </button>
    </form>
</div>
</div>

</div><!-- /col -->
</div><!-- /row -->
</div><!-- /container -->
</div><!-- /bg -->

<script>
// Auto-scroll thread to bottom on load
const tc = document.getElementById('thread-container');
if (tc) tc.scrollTop = tc.scrollHeight;
</script>

<?php include '../includes/footer.php'; ?>
