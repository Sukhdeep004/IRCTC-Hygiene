<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Alerts';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

// Mark all as read
if (isset($_GET['mark_read'])) {
    $conn->query("UPDATE alerts SET is_read=1");
    $_SESSION['msg_success'] = 'All alerts marked as read.';
    redirect('alerts.php');
}

$alerts = $conn->query("
    SELECT a.*, v.vendor_name, v.current_score, v.status as vendor_status
    FROM alerts a JOIN vendors v ON a.vendor_id=v.id
    ORDER BY a.is_read ASC, a.created_at DESC
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
    <a href="alerts.php" class="active"><i class="fas fa-bell fa-fw me-2"></i>Alerts</a>
    <a href="analytics.php"><i class="fas fa-chart-bar fa-fw me-2"></i>Analytics</a>
    <a href="chat.php"><i class="fas fa-comments fa-fw me-2"></i>Messages
      <?php $uc = getUnreadMessageCount($_SESSION['user_id']); if ($uc > 0): ?>
      <span class="badge bg-danger ms-auto"><?= $uc ?></span>
      <?php endif; ?>
    </a>
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;"><a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <h3 class="fw-700 mb-0"><i class="fas fa-bell me-2"></i>System Alerts</h3>
    </div>
    <a href="alerts.php?mark_read=1" class="btn btn-outline-secondary btn-sm"><i class="fas fa-check me-1"></i>Mark All Read</a>
</div>
<?php if ($alerts->num_rows === 0): ?>
<div class="card p-5 text-center text-muted">
    <i class="fas fa-bell-slash fa-3x mb-3"></i>
    <h5>No alerts at this time</h5>
</div>
<?php else: while ($a = $alerts->fetch_assoc()):
    $typeColors = ['low_score'=>'danger','complaint_spike'=>'warning','critical'=>'dark','suspended'=>'secondary'];
    $typeIcons  = ['low_score'=>'fas fa-chart-line-down','complaint_spike'=>'fas fa-exclamation-triangle','critical'=>'fas fa-skull-crossbones','suspended'=>'fas fa-ban'];
    $color = $typeColors[$a['alert_type']] ?? 'secondary';
    $cls   = classifyVendor($a['current_score']);
?>
<div class="card mb-3 border-<?= $color ?>" style="border-left:5px solid !important;<?= !$a['is_read'] ? 'background:#fffbf0;' : '' ?>">
<div class="p-4">
    <div class="d-flex align-items-start gap-3">
        <div style="width:45px;height:45px;background:var(--<?= $color==='warning'?'orange':'navy' ?>);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.2rem;flex-shrink:0;">
            <i class="<?= $typeIcons[$a['alert_type']] ?? 'fas fa-bell' ?> text-white"></i>
        </div>
        <div class="flex-grow-1">
            <div class="d-flex justify-content-between">
                <div>
                    <span class="badge bg-<?= $color ?> mb-1"><?= ucwords(str_replace('_',' ',$a['alert_type'])) ?></span>
                    <?= !$a['is_read'] ? '<span class="badge bg-primary ms-1">NEW</span>' : '' ?>
                    <h6 class="fw-700 mb-1"><?= htmlspecialchars($a['vendor_name']) ?></h6>
                </div>
                <small class="text-muted"><?= timeAgo($a['created_at']) ?></small>
            </div>
            <p class="mb-2"><?= htmlspecialchars($a['message']) ?></p>
            <div class="d-flex align-items-center gap-3" style="font-size:0.82rem;">
                <span>Score: <strong class="text-danger"><?= $a['current_score'] ?></strong></span>
                <span class="score-badge bg-<?= $cls['class']==='warning'?'warning text-dark':$cls['class'].' text-white' ?>"><?= $cls['icon'] ?> <?= $cls['label'] ?></span>
            </div>
        </div>
        <div class="d-flex flex-column gap-2">
            <a href="../vendor_profile.php?id=<?= $a['vendor_id'] ?>" class="btn btn-sm btn-outline-primary">View Vendor</a>
            <form method="POST" action="update_vendor_status.php">
                <input type="hidden" name="vendor_id" value="<?= $a['vendor_id'] ?>">
                <input type="hidden" name="status" value="under_review">
                <button type="submit" class="btn btn-sm btn-warning w-100">Mark Under Review</button>
            </form>
        </div>
    </div>
</div>
</div>
<?php endwhile; endif; ?>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
