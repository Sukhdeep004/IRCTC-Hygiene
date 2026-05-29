<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Messages';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

$admin_id = (int)$_SESSION['user_id'];

$users = $conn->query("
    SELECT u.id, u.name, u.role,
           COUNT(m.id) as unread_count
    FROM users u
    LEFT JOIN messages m ON m.sender_id=u.id AND m.receiver_id=$admin_id AND m.is_read=0
    WHERE u.role IN ('vendor','officer')
    GROUP BY u.id ORDER BY unread_count DESC, u.name ASC
");

include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">
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
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-comments me-2"></i>Messages</h3>
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

<div class="card">
<div class="card-header bg-white py-3">
    <h5 class="mb-0 fw-semibold"><i class="fas fa-inbox me-2 text-primary"></i>Conversations</h5>
</div>
<div class="table-responsive">
<table class="table table-hover mb-0">
<thead class="table-light">
    <tr>
        <th>Name</th>
        <th>Role</th>
        <th>Unread</th>
        <th>Action</th>
    </tr>
</thead>
<tbody>
<?php if ($users->num_rows === 0): ?>
<tr><td colspan="4" class="text-center py-4 text-muted">No vendors or officers found.</td></tr>
<?php else: while ($u = $users->fetch_assoc()): ?>
<tr>
    <td class="fw-semibold"><?= htmlspecialchars($u['name']) ?></td>
    <td>
        <?php if ($u['role'] === 'vendor'): ?>
        <span class="badge bg-primary">Vendor</span>
        <?php else: ?>
        <span class="badge bg-success">Officer</span>
        <?php endif; ?>
    </td>
    <td>
        <?php if ($u['unread_count'] > 0): ?>
        <span class="badge bg-danger"><?= (int)$u['unread_count'] ?></span>
        <?php else: ?>
        <span class="text-muted">—</span>
        <?php endif; ?>
    </td>
    <td>
        <a href="chat_thread.php?with=<?= (int)$u['id'] ?>" class="btn btn-sm btn-outline-primary">
            <i class="fas fa-comment-dots me-1"></i>Open Chat
        </a>
    </td>
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
