<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Message Vendors';
if (!isLoggedIn() || !isOfficer()) redirect('../login.php');

$uid = (int)$_SESSION['user_id'];

$vendors = $conn->query("
    SELECT u.id, u.name, v.vendor_name, v.station,
           COUNT(m.id) as unread_count
    FROM vendors v
    JOIN users u ON v.user_id = u.id
    LEFT JOIN messages m ON m.sender_id=u.id AND m.receiver_id=$uid AND m.is_read=0
    WHERE v.status != 'removed'
    GROUP BY u.id ORDER BY unread_count DESC, u.name ASC
");

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
        $adminRes4 = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
        $admin_id4 = (int)$adminRes4->fetch_assoc()['id'];
        $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id4 AND is_read=0")->fetch_assoc()['c'];
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
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-store me-2"></i>Message Vendors</h3>
</div>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Vendor</th><th>Station</th><th>Unread</th><th>Action</th></tr></thead>
<tbody>
<?php if (!$vendors || $vendors->num_rows === 0): ?>
<tr><td colspan="4" class="text-center py-4 text-muted">No vendors found.</td></tr>
<?php else: while ($v = $vendors->fetch_assoc()): ?>
<tr>
    <td><strong><?= htmlspecialchars($v['vendor_name']) ?></strong></td>
    <td><?= htmlspecialchars($v['station']) ?></td>
    <td>
        <?php if ($v['unread_count'] > 0): ?>
        <span class="badge bg-danger"><?= $v['unread_count'] ?> new</span>
        <?php else: ?>
        <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td><a href="vendor_thread.php?with=<?= $v['id'] ?>" class="btn btn-sm btn-primary"><i class="fas fa-comment me-1"></i>Open Chat</a></td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
