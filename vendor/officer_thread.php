<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Chat with Officer';
if (!isLoggedIn() || !isVendor()) redirect('../login.php');

$uid = (int)$_SESSION['user_id'];
$with = isset($_GET['with']) ? (int)$_GET['with'] : 0;
if ($with <= 0) { $_SESSION['msg_error'] = 'Invalid officer.'; redirect('officer_chat.php'); }

$targetRes = $conn->query("SELECT id, name FROM users WHERE id=$with AND role='officer'");
if (!$targetRes || $targetRes->num_rows === 0) { $_SESSION['msg_error'] = 'Officer not found.'; redirect('officer_chat.php'); }
$target = $targetRes->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = trim($_POST['message_body'] ?? '');
    if ($body === '') { $_SESSION['msg_error'] = 'Message cannot be empty.'; redirect("officer_thread.php?with=$with"); }
    $body = $conn->real_escape_string($body);
    $conn->query("INSERT INTO messages (sender_id, receiver_id, message_body, context_type) VALUES ($uid, $with, '$body', 'general')");
    redirect("officer_thread.php?with=$with");
}

$messages = $conn->query("
    SELECT m.*, u.name as sender_name FROM messages m
    JOIN users u ON m.sender_id=u.id
    WHERE (m.sender_id=$uid AND m.receiver_id=$with) OR (m.sender_id=$with AND m.receiver_id=$uid)
    ORDER BY m.created_at ASC
");
$conn->query("UPDATE messages SET is_read=1 WHERE receiver_id=$uid AND sender_id=$with AND is_read=0");

include '../includes/header.php';
?>
<div class="page-hero py-4">
<div class="container">
    <a href="officer_chat.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Officers</a>
    <h1 style="font-size:1.8rem;"><i class="fas fa-comment-dots me-2"></i>Chat with <?= htmlspecialchars($target['name']) ?> <span class="badge bg-primary ms-2">Officer</span></h1>
</div>
</div>
<div class="container py-4" style="min-height:60vh;">
<div class="row g-4">
<div class="col-lg-3">
    <div class="sidebar-nav">
        <div class="sidebar-header"><i class="fas fa-store me-2"></i>Vendor Panel</div>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
        <a href="messages.php"><i class="fas fa-comments fa-fw me-2"></i>Messages (Admin)
            <?php
            $adminRes3 = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
            $admin_id3 = (int)$adminRes3->fetch_assoc()['id'];
            $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id3 AND is_read=0")->fetch_assoc()['c'];
            if ($ucAdmin > 0): ?><span class="badge bg-danger ms-auto"><?= $ucAdmin ?></span><?php endif; ?>
        </a>
        <a href="officer_chat.php" class="active"><i class="fas fa-user-shield fa-fw me-2"></i>Message Officers
            <?php $ucOfficers = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='officer' AND m.is_read=0")->fetch_assoc()['c'];
            if ($ucOfficers > 0): ?><span class="badge bg-danger ms-auto"><?= $ucOfficers ?></span><?php endif; ?>
        </a>
    </div>
</div>
<div class="col-lg-9">
<div class="card mb-3">
<div class="card-body" style="min-height:400px;max-height:500px;overflow-y:auto;background:#f8faff;" id="chatThread">
<?php if (!$messages || $messages->num_rows === 0): ?>
<p class="text-center text-muted py-4">No messages yet.</p>
<?php else: while ($msg = $messages->fetch_assoc()):
    $isOwn = ((int)$msg['sender_id'] === $uid);
?>
<div class="d-flex mb-3 <?= $isOwn ? 'justify-content-end' : 'justify-content-start' ?>">
    <div style="max-width:70%;">
        <div class="d-flex align-items-center gap-2 mb-1 <?= $isOwn ? 'justify-content-end' : '' ?>">
            <small class="text-muted fw-600" style="font-size:0.75rem;"><?= htmlspecialchars($msg['sender_name']) ?></small>
            <small class="text-muted" style="font-size:0.72rem;"><?= date('d M Y, H:i', strtotime($msg['created_at'])) ?></small>
        </div>
        <div style="padding:10px 16px;border-radius:<?= $isOwn?'14px 14px 4px 14px':'14px 14px 14px 4px' ?>;background:<?= $isOwn?'linear-gradient(135deg,#003580,#0066cc)':'#fff' ?>;color:<?= $isOwn?'#fff':'#222' ?>;box-shadow:0 2px 8px rgba(0,0,0,0.10);font-size:0.88rem;word-break:break-word;">
            <?= nl2br(htmlspecialchars($msg['message_body'])) ?>
            <?php if (!empty($msg['context_id'])): ?>
            <?php $cr=$conn->query("SELECT complaint_code,subject FROM complaints WHERE id=".(int)$msg['context_id']); $crow=$cr?$cr->fetch_assoc():null; ?>
            <div class="mt-2 pt-2" style="border-top:1px solid <?= $isOwn?'rgba(255,255,255,0.3)':'#eee' ?>;">
                <span class="badge <?= $isOwn?'bg-light text-primary':'bg-primary' ?>" style="font-size:0.72rem;">
                    <i class="fas fa-link me-1"></i><?= $crow ? htmlspecialchars($crow['complaint_code']).' — '.htmlspecialchars(substr($crow['subject'],0,40)) : 'Complaint #'.(int)$msg['context_id'] ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endwhile; endif; ?>
</div>
</div>
<div class="card">
<div class="card-body">
<form method="POST" action="officer_thread.php?with=<?= $with ?>">
    <div class="mb-3">
        <label class="form-label fw-semibold">Reply to Officer</label>
        <textarea name="message_body" class="form-control" rows="3" placeholder="Type your message..." required></textarea>
    </div>
    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane me-2"></i>Send</button>
</form>
</div>
</div>
</div>
</div>
</div>
<script>const t=document.getElementById('chatThread');if(t)t.scrollTop=t.scrollHeight;</script>
<?php include '../includes/footer.php'; ?>
