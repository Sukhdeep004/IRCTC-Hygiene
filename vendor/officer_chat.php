<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Message Officers';
if (!isLoggedIn() || !isVendor()) redirect('../login.php');

$uid = (int)$_SESSION['user_id'];

$officers = $conn->query("
    SELECT u.id, u.name,
           COUNT(m.id) as unread_count
    FROM users u
    LEFT JOIN messages m ON m.sender_id=u.id AND m.receiver_id=$uid AND m.is_read=0
    WHERE u.role='officer' AND u.is_active=1
    GROUP BY u.id ORDER BY unread_count DESC, u.name ASC
");

include '../includes/header.php';
?>
<div class="page-hero py-4">
<div class="container">
    <a href="dashboard.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h1 style="font-size:1.8rem;"><i class="fas fa-user-shield me-2"></i>Message Officers</h1>
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
            $adminRes2 = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
            $admin_id2 = (int)$adminRes2->fetch_assoc()['id'];
            $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id2 AND is_read=0")->fetch_assoc()['c'];
            if ($ucAdmin > 0): ?><span class="badge bg-danger ms-auto"><?= $ucAdmin ?></span><?php endif; ?>
        </a>
        <a href="officer_chat.php" class="active"><i class="fas fa-user-shield fa-fw me-2"></i>Message Officers
            <?php $ucOfficers = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='officer' AND m.is_read=0")->fetch_assoc()['c'];
            if ($ucOfficers > 0): ?><span class="badge bg-danger ms-auto"><?= $ucOfficers ?></span><?php endif; ?>
        </a>
    </div>
</div>
<div class="col-lg-9">
<div class="card">
<div class="card-header-blue"><i class="fas fa-user-shield me-2"></i>IRCTC Officers</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Officer</th><th>Unread</th><th>Action</th></tr></thead>
<tbody>
<?php if (!$officers || $officers->num_rows === 0): ?>
<tr><td colspan="3" class="text-center py-4 text-muted">No officers available.</td></tr>
<?php else: while ($o = $officers->fetch_assoc()): ?>
<tr>
    <td><strong><?= htmlspecialchars($o['name']) ?></strong></td>
    <td><?= $o['unread_count'] > 0 ? '<span class="badge bg-danger">'.$o['unread_count'].' new</span>' : '<span class="text-muted">—</span>' ?></td>
    <td><a href="officer_thread.php?with=<?= $o['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-comment me-1"></i>Open Chat</a></td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
