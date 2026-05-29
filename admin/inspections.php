<?php
require_once '../includes/config.php';
require_once '../includes/ai_module.php';
define('BASEPATH', '../');
$pageTitle = 'Inspections';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

// Get AI-powered inspection priorities
$inspectionPriorities = calculateInspectionPriority();

$reports = $conn->query("
    SELECT ir.*, v.vendor_name, v.current_score as vendor_current, u.name as officer_name
    FROM inspection_reports ir
    JOIN vendors v ON ir.vendor_id=v.id
    JOIN users u ON ir.officer_id=u.id
    ORDER BY ir.inspection_date DESC
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
    <a href="inspections.php" class="active"><i class="fas fa-clipboard-check fa-fw me-2"></i>Inspections</a>
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
    <h3 class="fw-700 mb-0"><i class="fas fa-clipboard-check me-2"></i>Inspection Management</h3>
</div>

<!-- AI INSPECTION PRIORITIES -->
<div class="card mb-4">
<div class="card-header-orange d-flex justify-content-between align-items-center">
    <span><i class="fas fa-robot me-2"></i>🤖 AI-Powered Inspection Priorities</span>
    <span class="badge bg-light text-dark">Smart Recommendations</span>
</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr>
    <th>Priority</th><th>Vendor</th><th>Current Score</th><th>Complaints (30d)</th><th>Trend</th><th>Last Inspection</th><th>Priority Score</th><th>Action</th>
</tr></thead>
<tbody>
<?php 
$urgentCount = 0;
foreach (array_slice($inspectionPriorities, 0, 10) as $ip): 
    $priorityBadge = $ip['priority_level'] === 'urgent' ? 'danger' : ($ip['priority_level'] === 'high' ? 'warning' : 'info');
    $priorityIcon = $ip['priority_level'] === 'urgent' ? '🚨' : ($ip['priority_level'] === 'high' ? '⚠️' : 'ℹ️');
    if ($ip['priority_level'] === 'urgent') $urgentCount++;
?>
<tr>
    <td><span class="badge bg-<?= $priorityBadge ?>"><?= $priorityIcon ?> <?= ucfirst($ip['priority_level']) ?></span></td>
    <td><strong><?= htmlspecialchars($ip['vendor_name']) ?></strong></td>
    <td>
        <?= renderStars($ip['reasons']['current_score']) ?>
        <strong><?= number_format($ip['reasons']['current_score'], 2) ?></strong>
    </td>
    <td><?= $ip['reasons']['complaints'] ?></td>
    <td>
        <?php 
        $trendIcon = $ip['reasons']['trend'] === 'improving' ? '📈' : ($ip['reasons']['trend'] === 'declining' ? '📉' : '➡️');
        $trendColor = $ip['reasons']['trend'] === 'improving' ? '#1a8a45' : ($ip['reasons']['trend'] === 'declining' ? '#c0392b' : '#6c757d');
        ?>
        <span style="color:<?= $trendColor ?>;"><?= $trendIcon ?> <?= ucfirst($ip['reasons']['trend']) ?></span>
    </td>
    <td style="font-size:0.82rem;">
        <?= $ip['reasons']['last_inspection'] ? date('d M Y', strtotime($ip['reasons']['last_inspection'])) : '<span class="text-danger">Never</span>' ?>
    </td>
    <td><strong><?= $ip['priority_score'] ?></strong></td>
    <td><a href="../vendor_profile.php?id=<?= $ip['vendor_id'] ?>" class="btn btn-sm btn-primary">View</a></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<div class="p-3" style="background:#f8f9fa;border-top:1px solid #dee2e6;font-size:0.85rem;">
    <i class="fas fa-info-circle me-1"></i>
    <strong>AI Priority Algorithm:</strong> Considers hygiene score, recent complaints, trend analysis, and inspection history. 
    <?= $urgentCount ?> vendor(s) require urgent attention.
</div>
</div>

<h4 class="fw-700 mb-3">Past Inspection Reports</h4>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Date</th><th>Vendor</th><th>Officer</th><th>Inspect Score</th><th>Passenger Score</th><th>Deviation</th><th>Violations</th><th>Details</th></tr></thead>
<tbody>
<?php while ($r = $reports->fetch_assoc()):
    $dev = round($r['inspection_score'] - $r['vendor_current'], 2);
    $devColor = abs($dev) > 1 ? 'danger' : (abs($dev) > 0.5 ? 'warning' : 'success');
?>
<tr>
    <td><?= date('d M Y', strtotime($r['inspection_date'])) ?></td>
    <td><strong><?= htmlspecialchars($r['vendor_name']) ?></strong></td>
    <td><?= htmlspecialchars($r['officer_name']) ?></td>
    <td><?= renderStars($r['inspection_score']) ?> <strong><?= $r['inspection_score'] ?></strong></td>
    <td><strong><?= $r['vendor_current'] ?></strong></td>
    <td><span class="badge bg-<?= $devColor ?>"><?= ($dev >= 0 ? '+' : '') . $dev ?></span></td>
    <td style="font-size:0.78rem;"><?= $r['violations'] ? htmlspecialchars(substr($r['violations'],0,50)).'...' : '<span class="text-success">None</span>' ?></td>
    <td>
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#imodal<?= $r['id'] ?>">View</button>
        <div class="modal fade" id="imodal<?= $r['id'] ?>" tabindex="-1">
        <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700">Inspection Report</h5>
            <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <p><strong>Vendor:</strong> <?= htmlspecialchars($r['vendor_name']) ?></p>
            <p><strong>Officer:</strong> <?= htmlspecialchars($r['officer_name']) ?> | <strong>Date:</strong> <?= date('d M Y', strtotime($r['inspection_date'])) ?></p>
            <p><strong>Inspection Score:</strong> <?= $r['inspection_score'] ?>/5 | <strong>Passenger Score:</strong> <?= $r['vendor_current'] ?>/5</p>
            <div class="row g-2 mb-3">
                <?php foreach ([['Cleanliness',$r['cleanliness']],['Food Quality',$r['food_quality']],['Packaging',$r['packaging']],['Staff Hygiene',$r['staff_hygiene']],['Timeliness',$r['timeliness']]] as $p): ?>
                <div class="col-6"><div class="p-2 rounded text-center" style="background:#f0f5ff;font-size:0.82rem;"><strong><?= $p[0] ?></strong><br><?= $p[1] ?>/5</div></div>
                <?php endforeach; ?>
            </div>
            <?php if ($r['violations']): ?><div class="p-2 rounded mb-2" style="background:#fff3cd;font-size:0.85rem;"><strong>Violations:</strong><br><?= nl2br(htmlspecialchars($r['violations'])) ?></div><?php endif; ?>
            <?php if ($r['recommendations']): ?><div class="p-2 rounded" style="background:#d1e7dd;font-size:0.85rem;"><strong>Recommendations:</strong><br><?= nl2br(htmlspecialchars($r['recommendations'])) ?></div><?php endif; ?>
        </div>
        </div></div></div>
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
