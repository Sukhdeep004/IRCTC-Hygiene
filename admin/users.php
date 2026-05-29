<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Users';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

$currentAdminId = $_SESSION['user_id'];

// Edit user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $uid   = (int)$_POST['user_id'];
    $name  = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $pass  = trim($_POST['password']);
    $target = $conn->query("SELECT role FROM users WHERE id=$uid")->fetch_assoc();
    if (!$target) { $_SESSION['msg_error'] = 'User not found.'; redirect('users.php'); }
    if ($target['role'] === 'admin' && $uid !== $currentAdminId) {
        $_SESSION['msg_error'] = 'Cannot edit another admin account.'; redirect('users.php');
    }
    if (!$name || !$email) {
        $_SESSION['msg_error'] = 'Name and email are required.'; redirect('users.php');
    }
    if ($conn->query("SELECT id FROM users WHERE email='$email' AND id!=$uid")->num_rows > 0) {
        $_SESSION['msg_error'] = 'Email already in use by another account.'; redirect('users.php');
    }
    if ($pass) {
        if (strlen($pass) < 6) { $_SESSION['msg_error'] = 'Password must be at least 6 characters.'; redirect('users.php'); }
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET name='$name',email='$email',phone='$phone',password='$hash' WHERE id=$uid");
    } else {
        $conn->query("UPDATE users SET name='$name',email='$email',phone='$phone' WHERE id=$uid");
    }
    $_SESSION['msg_success'] = 'User updated successfully.';
    redirect('users.php');
}

// Toggle active/inactive
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $uid = (int)$_GET['toggle'];
    $target = $conn->query("SELECT role, is_active FROM users WHERE id=$uid")->fetch_assoc();
    if ($target) {
        if ($target['role'] === 'admin') {
            $_SESSION['msg_error'] = 'Cannot deactivate an admin account.';
        } else {
            $newVal = $target['is_active'] ? 0 : 1;
            $conn->query("UPDATE users SET is_active=$newVal WHERE id=$uid");
            $_SESSION['msg_success'] = 'User status updated.';
        }
    }
    redirect('users.php');
}

// Delete user
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    $target = $conn->query("SELECT role FROM users WHERE id=$uid")->fetch_assoc();
    if ($target) {
        if ($target['role'] === 'admin') {
            $_SESSION['msg_error'] = 'Cannot delete an admin account.';
        } elseif ($uid === $currentAdminId) {
            $_SESSION['msg_error'] = 'Cannot delete your own account.';
        } else {
            $conn->query("DELETE FROM users WHERE id=$uid");
            $_SESSION['msg_success'] = 'User deleted successfully.';
        }
    }
    redirect('users.php');
}

$users = $conn->query("
    SELECT u.*, COUNT(DISTINCT r.id) as rating_count, COUNT(DISTINCT c.id) as complaint_count
    FROM users u
    LEFT JOIN ratings r ON u.id=r.passenger_id AND u.role='passenger'
    LEFT JOIN complaints c ON u.id=c.passenger_id
    GROUP BY u.id ORDER BY u.created_at DESC
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
    <a href="chat.php"><i class="fas fa-comments fa-fw me-2"></i>Messages
      <?php $uc = getUnreadMessageCount($_SESSION['user_id']); if ($uc > 0): ?>
      <span class="badge bg-danger ms-auto"><?= $uc ?></span>
      <?php endif; ?>
    </a>
    <a href="users.php" class="active"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;"><a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-users me-2"></i>All Users</h3>
</div>

<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead>
<tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Ratings</th><th>Complaints</th><th>Joined</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php
$roleColors = ['admin'=>'danger','officer'=>'primary','vendor'=>'success','passenger'=>'secondary'];
while ($u = $users->fetch_assoc()):
    $isAdminUser = $u['role'] === 'admin';
    $isSelf      = (int)$u['id'] === $currentAdminId;
    $canEdit     = !$isAdminUser || $isSelf;
    $canToggle   = !$isAdminUser;
    $canDelete   = !$isAdminUser && !$isSelf;
?>
<tr>
    <td style="font-size:0.82rem;"><?= $u['id'] ?></td>
    <td>
        <div class="d-flex align-items-center gap-2">
            <div style="width:34px;height:34px;background:linear-gradient(135deg,#003580,#0066cc);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.82rem;flex-shrink:0;">
                <?= strtoupper(substr($u['name'],0,1)) ?>
            </div>
            <strong style="font-size:0.88rem;"><?= htmlspecialchars($u['name']) ?></strong>
        </div>
    </td>
    <td style="font-size:0.82rem;"><?= htmlspecialchars($u['email']) ?></td>
    <td style="font-size:0.82rem;"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
    <td><span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span></td>
    <td><span class="badge bg-light text-dark border"><?= $u['rating_count'] ?></span></td>
    <td><span class="badge bg-light text-dark border"><?= $u['complaint_count'] ?></span></td>
    <td style="font-size:0.8rem;"><?= date('d M Y', strtotime($u['created_at'])) ?></td>
    <td><span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
    <td>
        <div class="d-flex gap-1">
            <!-- View -->
            <button class="btn btn-sm btn-outline-secondary" title="View Details"
                data-bs-toggle="modal" data-bs-target="#viewModal<?= $u['id'] ?>">
                <i class="fas fa-eye"></i>
            </button>
            <!-- Edit -->
            <?php if ($canEdit): ?>
            <button class="btn btn-sm btn-outline-primary" title="Edit"
                data-bs-toggle="modal" data-bs-target="#editModal<?= $u['id'] ?>">
                <i class="fas fa-edit"></i>
            </button>
            <?php endif; ?>
            <!-- Toggle -->
            <?php if ($canToggle): ?>
            <a href="users.php?toggle=<?= $u['id'] ?>"
               class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
               title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"
               onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> <?= htmlspecialchars(addslashes($u['name'])) ?>?')">
                <i class="fas fa-<?= $u['is_active'] ? 'ban' : 'check' ?>"></i>
            </a>
            <?php endif; ?>
            <!-- Delete -->
            <?php if ($canDelete): ?>
            <a href="users.php?delete=<?= $u['id'] ?>"
               class="btn btn-sm btn-outline-danger" title="Delete"
               onclick="return confirm('Permanently delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This cannot be undone.')">
                <i class="fas fa-trash"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- VIEW MODAL -->
        <div class="modal fade" id="viewModal<?= $u['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-user me-2"></i><?= htmlspecialchars($u['name']) ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-3">
                <div style="width:60px;height:60px;background:linear-gradient(135deg,#003580,#0066cc);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.5rem;margin:0 auto;">
                    <?= strtoupper(substr($u['name'],0,1)) ?>
                </div>
                <h5 class="mt-2 mb-1"><?= htmlspecialchars($u['name']) ?></h5>
                <span class="badge bg-<?= $roleColors[$u['role']] ?? 'secondary' ?>"><?= ucfirst($u['role']) ?></span>
            </div>
            <div class="p-3 rounded mb-3" style="background:#f0f5ff;font-size:0.88rem;">
                <div class="row g-2">
                    <div class="col-6"><strong>Email:</strong><br><span style="font-size:0.82rem;"><?= htmlspecialchars($u['email']) ?></span></div>
                    <div class="col-6"><strong>Phone:</strong><br><?= htmlspecialchars($u['phone'] ?? '—') ?></div>
                    <div class="col-6"><strong>Joined:</strong><br><?= date('d M Y', strtotime($u['created_at'])) ?></div>
                    <div class="col-6"><strong>Status:</strong><br>
                        <span class="badge <?= $u['is_active'] ? 'bg-success' : 'bg-secondary' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span>
                    </div>
                </div>
            </div>
            <div class="row g-3 text-center">
                <div class="col-6"><div class="stat-card p-3"><div class="stat-num text-primary" style="font-size:1.8rem;"><?= $u['rating_count'] ?></div><div class="stat-label">Ratings Given</div></div></div>
                <div class="col-6"><div class="stat-card p-3"><div class="stat-num text-danger" style="font-size:1.8rem;"><?= $u['complaint_count'] ?></div><div class="stat-label">Complaints Filed</div></div></div>
            </div>
        </div>
        </div></div></div>

        <!-- EDIT MODAL -->
        <?php if ($canEdit): ?>
        <div class="modal fade" id="editModal<?= $u['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-edit me-2"></i>Edit User</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($u['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($u['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($u['phone'] ?? '') ?>" maxlength="15">
            </div>
            <div class="mb-3">
                <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                <input type="password" name="password" class="form-control" placeholder="Min 6 characters" minlength="6">
            </div>
            <div class="mb-3 p-2 rounded" style="background:#f0f5ff;font-size:0.82rem;">
                <i class="fas fa-info-circle me-1 text-primary"></i>
                Role: <strong><?= ucfirst($u['role']) ?></strong>
                <?php if ($isAdminUser): ?> — <span class="text-danger">Admin role cannot be changed here</span><?php endif; ?>
            </div>
            <button type="submit" name="edit_user" class="btn btn-primary w-100">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </form>
        </div>
        </div></div></div>
        <?php endif; ?>

    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
