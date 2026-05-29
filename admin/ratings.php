<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'All Ratings';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

$ratings = $conn->query("
    SELECT r.*, v.vendor_name, u.name as passenger_name
    FROM ratings r
    JOIN vendors v ON r.vendor_id=v.id
    JOIN users u ON r.passenger_id=u.id
    ORDER BY r.created_at DESC
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
    <a href="ratings.php" class="active"><i class="fas fa-star fa-fw me-2"></i>Ratings</a>
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
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;"><a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-4">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-star me-2"></i>All Hygiene Ratings</h3>
</div>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>#</th><th>Vendor</th><th>Passenger</th><th>C</th><th>F</th><th>P</th><th>S</th><th>T</th><th>Final Score</th><th>Date</th><th>Train</th></tr></thead>
<tbody>
<?php while ($r = $ratings->fetch_assoc()): ?>
<tr>
    <td><?= $r['id'] ?></td>
    <td><?= htmlspecialchars($r['vendor_name']) ?></td>
    <td><?= htmlspecialchars($r['passenger_name']) ?></td>
    <td><?= $r['cleanliness'] ?></td>
    <td><?= $r['food_quality'] ?></td>
    <td><?= $r['packaging'] ?></td>
    <td><?= $r['staff_hygiene'] ?></td>
    <td><?= $r['timeliness'] ?></td>
    <td><?= renderStars($r['final_score']) ?> <strong><?= $r['final_score'] ?></strong></td>
    <td><?= date('d M Y', strtotime($r['travel_date'])) ?></td>
    <td><?= htmlspecialchars($r['train_number'] ?: '—') ?></td>
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
