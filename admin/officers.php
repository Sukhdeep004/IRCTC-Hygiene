<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Officers Management';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

$error = '';

// Add new officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_officer'])) {
    $name  = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $pass  = $_POST['password'];

    if (!$name || !$email || !$pass) {
        $error = 'Name, email and password are required.';
    } elseif (strlen($pass) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($conn->query("SELECT id FROM users WHERE email='$email'")->num_rows > 0) {
        $error = 'Email already exists.';
    } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (name, email, phone, password, role) VALUES ('$name','$email','$phone','$hash','officer')");
        $_SESSION['msg_success'] = "Officer '$name' added successfully.";
        redirect('officers.php');
    }
}

// Edit officer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_officer'])) {
    $oid   = (int)$_POST['officer_id'];
    $name  = sanitize($_POST['name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $pass  = trim($_POST['password']);

    if (!$name || !$email) {
        $error = 'Name and email are required.';
    } elseif ($conn->query("SELECT id FROM users WHERE email='$email' AND id!=$oid")->num_rows > 0) {
        $error = 'Email already used by another account.';
    } else {
        if ($pass) {
            if (strlen($pass) < 6) { $error = 'Password must be at least 6 characters.'; }
            else {
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $conn->query("UPDATE users SET name='$name',email='$email',phone='$phone',password='$hash' WHERE id=$oid AND role='officer'");
            }
        } else {
            $conn->query("UPDATE users SET name='$name',email='$email',phone='$phone' WHERE id=$oid AND role='officer'");
        }
        if (!$error) { $_SESSION['msg_success'] = "Officer updated successfully."; redirect('officers.php'); }
    }
}

// Delete officer
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $oid = (int)$_GET['delete'];
    $conn->query("DELETE FROM users WHERE id=$oid AND role='officer'");
    $_SESSION['msg_success'] = 'Officer deleted.';
    redirect('officers.php');
}

// Toggle active status
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $oid = (int)$_GET['toggle'];
    $cur = $conn->query("SELECT is_active FROM users WHERE id=$oid AND role='officer'")->fetch_assoc();
    if ($cur) {
        $newVal = $cur['is_active'] ? 0 : 1;
        $conn->query("UPDATE users SET is_active=$newVal WHERE id=$oid");
        $_SESSION['msg_success'] = 'Officer status updated.';
    }
    redirect('officers.php');
}

$officers = $conn->query("
    SELECT u.*,
        (SELECT COUNT(*) FROM inspection_reports WHERE officer_id=u.id) as total_inspections,
        (SELECT COUNT(*) FROM complaints WHERE officer_id=u.id) as complaints_handled,
        (SELECT MAX(created_at) FROM inspection_reports WHERE officer_id=u.id) as last_inspection
    FROM users u WHERE u.role='officer' ORDER BY u.created_at DESC
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
    <a href="officers.php" class="active"><i class="fas fa-user-shield fa-fw me-2"></i>Officers</a>
    <a href="alerts.php"><i class="fas fa-bell fa-fw me-2"></i>Alerts</a>
    <a href="analytics.php"><i class="fas fa-chart-bar fa-fw me-2"></i>Analytics</a>
    <a href="chat.php"><i class="fas fa-comments fa-fw me-2"></i>Messages
      <?php $uc = getUnreadMessageCount($_SESSION['user_id']); if ($uc > 0): ?>
      <span class="badge bg-danger ms-auto"><?= $uc ?></span>
      <?php endif; ?>
    </a>
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-user-shield me-2"></i>Officers Management</h3>
    <button class="btn btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#addOfficerModal">
        <i class="fas fa-plus me-1"></i>Add New Officer
    </button>
</div>

<?php if ($error): ?><div class="alert alert-danger mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<!-- Officers Table -->
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead>
<tr><th>Name</th><th>Email</th><th>Phone</th><th>Inspections</th><th>Complaints Handled</th><th>Last Inspection</th><th>Status</th><th>Actions</th></tr>
</thead>
<tbody>
<?php if ($officers->num_rows === 0): ?>
<tr><td colspan="8" class="text-center py-4 text-muted">No officers found. Add one above.</td></tr>
<?php else: while ($o = $officers->fetch_assoc()): ?>
<tr>
    <td>
        <div class="d-flex align-items-center gap-2">
            <div style="width:36px;height:36px;background:linear-gradient(135deg,#003580,#0066cc);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:0.85rem;flex-shrink:0;">
                <?= strtoupper(substr($o['name'],0,1)) ?>
            </div>
            <strong><?= htmlspecialchars($o['name']) ?></strong>
        </div>
    </td>
    <td style="font-size:0.85rem;"><?= htmlspecialchars($o['email']) ?></td>
    <td style="font-size:0.85rem;"><?= $o['phone'] ?: '—' ?></td>
    <td><span class="badge bg-primary"><?= $o['total_inspections'] ?></span></td>
    <td><span class="badge bg-info text-dark"><?= $o['complaints_handled'] ?></span></td>
    <td style="font-size:0.82rem;"><?= $o['last_inspection'] ? date('d M Y', strtotime($o['last_inspection'])) : '<span class="text-muted">Never</span>' ?></td>
    <td>
        <?php if ($o['is_active']): ?>
        <span class="badge bg-success">Active</span>
        <?php else: ?>
        <span class="badge bg-danger">Inactive</span>
        <?php endif; ?>
    </td>
    <td>
        <div class="d-flex gap-1">
            <a href="officers.php?toggle=<?= $o['id'] ?>"
               class="btn btn-sm <?= $o['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
               onclick="return confirm('<?= $o['is_active'] ? 'Deactivate' : 'Activate' ?> this officer?')"
               title="<?= $o['is_active'] ? 'Deactivate' : 'Activate' ?>">
                <?= $o['is_active'] ? '<i class="fas fa-ban"></i>' : '<i class="fas fa-check"></i>' ?>
            </a>
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal<?= $o['id'] ?>" title="Edit">
                <i class="fas fa-edit"></i>
            </button>
            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $o['id'] ?>" title="View">
                <i class="fas fa-eye"></i>
            </button>
            <a href="officers.php?delete=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger"
               onclick="return confirm('Delete officer <?= htmlspecialchars(addslashes($o['name'])) ?>? This cannot be undone.')" title="Delete">
                <i class="fas fa-trash"></i>
            </a>
        </div>

        <!-- Edit Officer Modal -->
        <div class="modal fade" id="editModal<?= $o['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-edit me-2"></i>Edit Officer</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
        <form method="POST">
            <input type="hidden" name="officer_id" value="<?= $o['id'] ?>">
            <div class="mb-3">
                <label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($o['name']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($o['email']) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($o['phone'] ?? '') ?>" maxlength="15">
            </div>
            <div class="mb-3">
                <label class="form-label">New Password <small class="text-muted">(leave blank to keep current)</small></label>
                <input type="password" name="password" class="form-control" placeholder="Enter new password (min 6 chars)" minlength="6">
            </div>
            <button type="submit" name="edit_officer" class="btn btn-primary w-100">
                <i class="fas fa-save me-2"></i>Save Changes
            </button>
        </form>
        </div>
        </div>
        </div>
        </div>

        <!-- Officer Detail Modal -->
        <div class="modal fade" id="detailModal<?= $o['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
        <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-user-shield me-2"></i><?= htmlspecialchars($o['name']) ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <div class="p-3 rounded mb-3" style="background:#f0f5ff;">
                <div class="row g-2" style="font-size:0.88rem;">
                    <div class="col-6"><strong>Name:</strong> <?= htmlspecialchars($o['name']) ?></div>
                    <div class="col-6"><strong>Email:</strong> <?= htmlspecialchars($o['email']) ?></div>
                    <div class="col-6"><strong>Phone:</strong> <?= $o['phone'] ?: '—' ?></div>
                    <div class="col-6"><strong>Joined:</strong> <?= date('d M Y', strtotime($o['created_at'])) ?></div>
                    <div class="col-6"><strong>Status:</strong> <?= $o['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Inactive</span>' ?></div>
                </div>
            </div>
            <div class="row g-3 text-center">
                <div class="col-4"><div class="stat-card p-3"><div class="stat-num text-primary" style="font-size:1.8rem;"><?= $o['total_inspections'] ?></div><div class="stat-label">Inspections</div></div></div>
                <div class="col-4"><div class="stat-card p-3"><div class="stat-num text-info" style="font-size:1.8rem;"><?= $o['complaints_handled'] ?></div><div class="stat-label">Complaints</div></div></div>
                <div class="col-4"><div class="stat-card p-3"><div class="stat-num text-success" style="font-size:1.8rem;"><?= $o['last_inspection'] ? date('M Y', strtotime($o['last_inspection'])) : '—' ?></div><div class="stat-label">Last Active</div></div></div>
            </div>
        </div>
        </div>
        </div>
        </div>
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

<!-- Add Officer Modal -->
<div class="modal fade" id="addOfficerModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
    <h5 class="modal-title fw-700"><i class="fas fa-user-plus me-2"></i>Add New Officer</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
<form method="POST">
    <div class="mb-3">
        <label class="form-label">Full Name *</label>
        <input type="text" name="name" class="form-control" placeholder="Officer full name" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Email *</label>
        <input type="email" name="email" class="form-control" placeholder="officer@irctc.com" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Phone</label>
        <input type="text" name="phone" class="form-control" placeholder="10-digit phone number" maxlength="15">
    </div>
    <div class="mb-3">
        <label class="form-label">Password * <small class="text-muted">(min 6 chars)</small></label>
        <input type="password" name="password" class="form-control" placeholder="Set a password" required minlength="6">
    </div>
    <button type="submit" name="add_officer" class="btn btn-primary w-100">
        <i class="fas fa-user-plus me-2"></i>Create Officer Account
    </button>
</form>
</div>
</div>
</div>
</div>

<?php include '../includes/footer.php'; ?>
