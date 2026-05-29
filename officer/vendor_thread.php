<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Chat with Vendor';
if (!isLoggedIn() || !isOfficer()) redirect('../login.php');

$uid = (int)$_SESSION['user_id'];
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;
if ($with <= 0) { $_SESSION['msg_error'] = 'Invalid vendor.'; redirect('vendor_chat.php'); }

// Verify target is a vendor user
$targetRes = $conn->query("SELECT u.id, u.name, v.vendor_name FROM users u JOIN vendors v ON v.user_id=u.id WHERE u.id=$with AND u.role='vendor'");
if (!$targetRes || $targetRes->num_rows === 0) { $_SESSION['msg_error'] = 'Vendor not found.'; redirect('vendor_chat.php'); }
$target = $targetRes->fetch_assoc();

// POST — send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['message_body'] ?? '');
    if ($body === '') { $_SESSION['msg_error'] = 'Message cannot be empty.'; redirect("vendor_thread.php?with=$with"); }
    $body = $conn->real_escape_string($body);
    $ctx_type = 'general'; $ctx_id_sql = 'NULL';
    if (!empty($_POST['context_id'])) {
        $ctx_id = (int)$_POST['context_id'];
        if ($ctx_id > 0 && $conn->query("SELECT id FROM complaints WHERE id=$ctx_id")->num_rows > 0) {
            $ctx_type = 'complaint'; $ctx_id_sql = $ctx_id;
        }
    }
    $conn->query("INSERT INTO messages (sender_id, receiver_id, message_body, context_type, context_id) VALUES ($uid, $with, '$body', '$ctx_type', $ctx_id_sql)");
    redirect("vendor_thread.php?with=$with");
}

// GET — fetch thread + mark read
$messages = $conn->query("
    SELECT m.*, u.name as sender_name FROM messages m
    JOIN users u ON m.sender_id=u.id
    WHERE (m.sender_id=$uid AND m.receiver_id=$with) OR (m.sender_id=$with AND m.receiver_id=$uid)
    ORDER BY m.created_at ASC
");
$conn->query("UPDATE messages SET is_read=1 WHERE receiver_id=$uid AND sender_id=$with AND is_read=0");
$complaintsForSelect = $conn->query("SELECT id, complaint_code, subject FROM complaints WHERE vendor_id=(SELECT id FROM vendors WHERE user_id=$with) ORDER BY created_at DESC LIMIT 30");

include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">🛡️ Officer Panel</div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="complaints.php"><i class="fas fa-file-alt fa-fw me-2"></i>Complaint Verification</a>
    <a href="messages.php"><i class="fas fa-comments fa-fw me-2"></i>Messages (Admin)
        <?php
        $adminRes5 = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
        $admin_id5 = (int)$adminRes5->fetch_assoc()['id'];
        $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id5 AND is_read=0")->fetch_assoc()['c'];
        if ($ucAdmin > 0): ?>
        <span class="badge bg-danger ms-auto"><?= $ucAdmin ?></span>
        <?php endif; ?>
    </a>
    <a href="vendor_chat.php" class="active"><i class="fas fa-store fa-fw me-2"></i>Message Vendors
        <?php $ucVendors = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='vendor' AND m.is_read=0")->fetch_assoc()['c'];
        if ($ucVendors > 0): ?><span class="badge bg-danger ms-auto"><?= $ucVendors ?></span><?php endif; ?>
    </a>
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="vendor_chat.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Vendors</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-comment-dots me-2"></i>Chat with <?= htmlspecialchars($target['vendor_name']) ?> <span class="badge bg-success ms-2">Vendor</span></h3>
</div>

<?php if (isset($_SESSION['msg_error'])): ?><div class="alert alert-danger"><?= $_SESSION['msg_error'] ?><?php unset($_SESSION['msg_error']); ?></div><?php endif; ?>

<div class="card mb-3">
<div class="card-body" style="max-height:500px;overflow-y:auto;background:#f8f9fa;" id="thread-container">
<?php if (!$messages || $messages->num_rows === 0): ?>
<p class="text-center text-muted py-4">No messages yet. Start the conversation below.</p>
<?php else: while ($msg = $messages->fetch_assoc()):
    $isOwn = ((int)$msg['sender_id'] === $uid);
?>
<div class="d-flex <?= $isOwn ? 'justify-content-end' : 'justify-content-start' ?> mb-3">
    <div style="max-width:65%;">
        <div class="<?= $isOwn ? 'bg-primary text-white' : 'bg-white border' ?> rounded-3 px-3 py-2 shadow-sm">
            <div class="fw-semibold small mb-1 <?= $isOwn ? 'text-white-50' : 'text-muted' ?>"><?= htmlspecialchars($msg['sender_name']) ?></div>
            <div><?= nl2br(htmlspecialchars($msg['message_body'])) ?></div>
            <?php if (!empty($msg['context_id'])): ?>
            <?php $cr=$conn->query("SELECT complaint_code,subject FROM complaints WHERE id=".(int)$msg['context_id']); $crow=$cr?$cr->fetch_assoc():null; ?>
            <div class="mt-2 pt-2" style="border-top:1px solid <?= $isOwn?'rgba(255,255,255,0.3)':'#eee' ?>;">
                <span class="badge <?= $isOwn?'bg-light text-primary':'bg-primary' ?>" style="font-size:0.72rem;">
                    <i class="fas fa-link me-1"></i><?= $crow ? htmlspecialchars($crow['complaint_code']).' — '.htmlspecialchars(substr($crow['subject'],0,35)) : 'Complaint #'.(int)$msg['context_id'] ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <div class="text-muted small mt-1 <?= $isOwn ? 'text-end' : '' ?>"><?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></div>
    </div>
</div>
<?php endwhile; endif; ?>
</div>
</div>

<div class="card">
<div class="card-body">
<form method="POST" action="vendor_thread.php?with=<?= $with ?>">
    <div class="mb-3">
        <label class="form-label fw-semibold">Message</label>
        <textarea name="message_body" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
    </div>
    <?php if ($complaintsForSelect && $complaintsForSelect->num_rows > 0): ?>
    <div class="mb-3">
        <label class="form-label fw-semibold">Link to Complaint <span class="text-muted fw-normal">(optional)</span></label>
        <select name="context_id" class="form-select">
            <option value="">— No complaint context —</option>
            <?php while ($copt = $complaintsForSelect->fetch_assoc()): ?>
            <option value="<?= $copt['id'] ?>"><?= htmlspecialchars($copt['complaint_code']) ?> — <?= htmlspecialchars($copt['subject']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send</button>
</form>
</div>
</div>
</div>
</div>
</div>
</div>
<script>const tc=document.getElementById('thread-container');if(tc)tc.scrollTop=tc.scrollHeight;</script>
<?php include '../includes/footer.php'; ?>
