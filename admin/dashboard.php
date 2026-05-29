<?php
require_once '../includes/config.php';
require_once '../includes/ai_module.php';
define('BASEPATH', '../');
$pageTitle = 'Admin Dashboard';
if (!isLoggedIn() || !isAdmin()) { $_SESSION['msg_error'] = 'Admin access required.'; redirect('../login.php'); }

// Get AI Insights
$aiInsights = generateAIInsights();
$inspectionPriorities = calculateInspectionPriority();

$stats = [
    'vendors'    => $conn->query("SELECT COUNT(*) as c FROM vendors")->fetch_assoc()['c'],
    'active'     => $conn->query("SELECT COUNT(*) as c FROM vendors WHERE status='active'")->fetch_assoc()['c'],
    'review'     => $conn->query("SELECT COUNT(*) as c FROM vendors WHERE status='under_review'")->fetch_assoc()['c'],
    'suspended'  => $conn->query("SELECT COUNT(*) as c FROM vendors WHERE status='suspended'")->fetch_assoc()['c'],
    'ratings'    => $conn->query("SELECT COUNT(*) as c FROM ratings")->fetch_assoc()['c'],
    'complaints' => $conn->query("SELECT COUNT(*) as c FROM complaints")->fetch_assoc()['c'],
    'pending_c'  => $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='pending'")->fetch_assoc()['c'],
    'passengers' => $conn->query("SELECT COUNT(*) as c FROM users WHERE role='passenger'")->fetch_assoc()['c'],
    'avg_score'  => $conn->query("SELECT AVG(final_score) as a FROM ratings")->fetch_assoc()['a'],
    'alerts'     => getAlertCount(),
];

$vendors = $conn->query("
    SELECT v.*, COUNT(DISTINCT r.id) as rating_count, COUNT(DISTINCT c.id) as complaint_count
    FROM vendors v
    LEFT JOIN ratings r ON v.id = r.vendor_id
    LEFT JOIN complaints c ON v.id = c.vendor_id
    GROUP BY v.id ORDER BY v.current_score DESC
");

// Get vendor trends for AI display
$vendorTrends = [];
$vendors->data_seek(0);
while ($v = $vendors->fetch_assoc()) {
    $trend = predictVendorTrend($v['id']);
    $vendorTrends[$v['id']] = $trend;
}
$vendors->data_seek(0);

$recentRatings = $conn->query("
    SELECT r.final_score, r.created_at, v.vendor_name, u.name as passenger_name
    FROM ratings r JOIN vendors v ON r.vendor_id=v.id JOIN users u ON r.passenger_id=u.id
    ORDER BY r.created_at DESC LIMIT 8
");

$recentComplaints = $conn->query("
    SELECT c.*, v.vendor_name, u.name as passenger_name
    FROM complaints c JOIN vendors v ON c.vendor_id=v.id JOIN users u ON c.passenger_id=u.id
    ORDER BY c.created_at DESC LIMIT 8
");

$alerts = $conn->query("
    SELECT a.*, v.vendor_name FROM alerts a JOIN vendors v ON a.vendor_id=v.id
    WHERE a.is_read=0 ORDER BY a.created_at DESC
");

include '../includes/header.php';

function sidebarItem($href, $icon, $label, $active=false) {
    $a = $active ? 'active' : '';
    return "<a href='$href' class='$a'><i class='$icon fa-fw me-2'></i>$label</a>";
}
?>

<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">

<!-- SIDEBAR -->
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">⚙️ Admin Panel</div>
    <a href="dashboard.php" class="active"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="vendors.php"><i class="fas fa-store fa-fw me-2"></i>Vendors</a>
    <a href="ratings.php"><i class="fas fa-star fa-fw me-2"></i>Ratings</a>
    <a href="complaints.php"><i class="fas fa-file-alt fa-fw me-2"></i>Complaints
      <?php $nc=((int)$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='forwarded_to_admin'")->fetch_assoc()['c']); if($nc>0): ?><span class="badge bg-danger ms-auto"><?= $nc ?></span><?php endif; ?>
    </a>
    <a href="inspections.php"><i class="fas fa-clipboard-check fa-fw me-2"></i>Inspections</a>
    <a href="officers.php"><i class="fas fa-user-shield fa-fw me-2"></i>Officers</a>
    <a href="alerts.php"><i class="fas fa-bell fa-fw me-2"></i>Alerts
        <?php $ac = getAlertCount(); if ($ac > 0): ?><span class="badge bg-danger ms-auto"><?= $ac ?></span><?php endif; ?>
    </a>
    <a href="analytics.php"><i class="fas fa-chart-bar fa-fw me-2"></i>Analytics</a>
    <a href="chat.php"><i class="fas fa-comments fa-fw me-2"></i>Messages
        <?php $uc = getUnreadMessageCount($_SESSION['user_id']); if ($uc > 0): ?><span class="badge bg-danger ms-auto"><?= $uc ?></span><?php endif; ?>
    </a>
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>

<!-- MAIN -->
<div class="col-xl-10 col-lg-9">

<!-- ALERTS BANNER -->
<?php if ($stats['alerts'] > 0): ?>
<div class="alert alert-danger d-flex align-items-center gap-3 mb-3">
    <i class="fas fa-exclamation-triangle fa-lg"></i>
    <div><strong><?= $stats['alerts'] ?> Active Alert(s):</strong> Vendors below 2.5 threshold need attention.
    <a href="alerts.php" class="text-danger fw-600 ms-2">View Alerts →</a></div>
</div>
<?php endif; ?>

<div class="d-flex align-items-center gap-3 mb-4">
    <a href="../index.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
    <h3 class="fw-700 mb-0">📊 Admin Dashboard</h3>
</div>

<!-- AI INSIGHTS BANNER -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card" style="border-left:4px solid #003580;">
            <div class="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-robot fa-lg" style="color:#003580;"></i>
                    <strong>System Health</strong>
                </div>
                <div style="font-size:2rem;font-weight:700;color:#003580;">
                    <?= number_format($aiInsights['system_health']['score'], 2) ?>/5.00
                </div>
                <div class="text-muted" style="font-size:0.82rem;">
                    Status: <span class="badge bg-<?= $aiInsights['system_health']['status']==='excellent'?'success':($aiInsights['system_health']['status']==='good'?'primary':'warning') ?>">
                        <?= ucfirst($aiInsights['system_health']['status']) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left:4px solid #1a8a45;">
            <div class="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-trophy fa-lg" style="color:#1a8a45;"></i>
                    <strong>Top Performers</strong>
                </div>
                <?php foreach (array_slice($aiInsights['top_performers'], 0, 2) as $tp): ?>
                <div style="font-size:0.82rem;" class="mb-1">
                    🏆 <?= htmlspecialchars(substr($tp['vendor_name'], 0, 20)) ?> 
                    <span class="badge bg-success"><?= $tp['current_score'] ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card" style="border-left:4px solid #c0392b;">
            <div class="p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-exclamation-triangle fa-lg" style="color:#c0392b;"></i>
                    <strong>At Risk Vendors</strong>
                </div>
                <?php if (empty($aiInsights['at_risk'])): ?>
                <div class="text-muted" style="font-size:0.82rem;">No vendors at risk</div>
                <?php else: foreach (array_slice($aiInsights['at_risk'], 0, 2) as $ar): ?>
                <div style="font-size:0.82rem;" class="mb-1">
                    ⚠️ <?= htmlspecialchars(substr($ar['vendor_name'], 0, 20)) ?> 
                    <span class="badge bg-danger"><?= $ar['current_score'] ?></span>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- INSPECTION PRIORITIES -->
<?php if (!empty($inspectionPriorities) && $inspectionPriorities[0]['priority_level'] === 'urgent'): ?>
<div class="alert alert-warning mb-4">
    <div class="d-flex align-items-center gap-3">
        <i class="fas fa-clipboard-check fa-2x"></i>
        <div>
            <strong>🤖 AI Recommendation:</strong> 
            <?= count(array_filter($inspectionPriorities, fn($p) => $p['priority_level'] === 'urgent')) ?> vendor(s) require urgent inspection.
            <a href="inspections.php" class="ms-2 fw-600">View Priorities →</a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <?php $statCards = [
        ['Total Vendors',$stats['vendors'],'fas fa-store','#003580'],
        ['Active',$stats['active'],'fas fa-check-circle','#1a8a45'],
        ['Under Review',$stats['review'],'fas fa-exclamation-triangle','#e6a817'],
        ['Suspended',$stats['suspended'],'fas fa-ban','#c0392b'],
        ['Total Ratings',$stats['ratings'],'fas fa-star','var(--orange)'],
        ['Complaints',$stats['complaints'],'fas fa-file-alt','#8b2fc9'],
        ['Pending Complaints',$stats['pending_c'],'fas fa-clock','#c0392b'],
        ['Passengers',$stats['passengers'],'fas fa-users','#003580'],
    ]; foreach ($statCards as $sc): ?>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-top:4px solid <?= $sc[3] ?>;">
            <div class="stat-num" style="color:<?= $sc[3] ?>;font-size:2rem;"><?= $sc[1] ?></div>
            <div class="stat-label"><?= $sc[0] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- VENDOR TABLE -->
<div class="card mb-4">
<div class="card-header-blue d-flex justify-content-between align-items-center">
    <span><i class="fas fa-store me-2"></i>All Vendors – Hygiene Rankings</span>
    <a href="vendors.php" class="btn btn-sm" style="background:rgba(255,255,255,0.2);color:#fff;">Manage →</a>
</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr>
    <th>Vendor</th><th>Zone/Station</th><th>Score</th><th>AI Trend</th><th>Classification</th>
    <th>Ratings</th><th>Complaints</th><th>Status</th><th>Action</th>
</tr></thead>
<tbody>
<?php while ($v = $vendors->fetch_assoc()):
    $cls = classifyVendor($v['current_score']);
    $trend = $vendorTrends[$v['id']];
    $trendIcon = $trend['trend'] === 'improving' ? '📈' : ($trend['trend'] === 'declining' ? '📉' : '➡️');
    $trendColor = $trend['trend'] === 'improving' ? '#1a8a45' : ($trend['trend'] === 'declining' ? '#c0392b' : '#6c757d');
?>
<tr>
    <td><strong><?= htmlspecialchars($v['vendor_name']) ?></strong><br><small class="text-muted"><?= $v['license_number'] ?></small></td>
    <td><?= htmlspecialchars($v['zone']) ?><br><small class="text-muted"><?= htmlspecialchars($v['station']) ?></small></td>
    <td>
        <?= renderStars($v['current_score']) ?><br>
        <strong class="text-primary"><?= number_format($v['current_score'],2) ?></strong>
    </td>
    <td>
        <?php if ($trend['trend'] !== 'insufficient_data'): ?>
        <span style="color:<?= $trendColor ?>;font-size:0.85rem;">
            <?= $trendIcon ?> <?= ucfirst($trend['trend']) ?>
        </span>
        <?php if ($trend['prediction']): ?>
        <br><small class="text-muted">Next: <?= $trend['prediction'] ?></small>
        <?php endif; ?>
        <?php else: ?>
        <span class="text-muted" style="font-size:0.82rem;">Not enough data</span>
        <?php endif; ?>
    </td>
    <td><span class="score-badge bg-<?= $cls['class']==='warning'?'warning text-dark':$cls['class'].' text-white' ?>"><?= $cls['icon'] ?> <?= $cls['label'] ?></span></td>
    <td><?= $v['rating_count'] ?></td>
    <td><?= $v['complaint_count'] ?></td>
    <td>
        <form method="POST" action="update_vendor_status.php" class="d-flex gap-1">
            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
            <select name="status" class="form-select form-select-sm" style="width:120px;" onchange="this.form.submit()">
                <option value="active"       <?= $v['status']==='active'       ?'selected':'' ?>>Active</option>
                <option value="under_review" <?= $v['status']==='under_review'  ?'selected':'' ?>>Under Review</option>
                <option value="suspended"   <?= $v['status']==='suspended'     ?'selected':'' ?>>Suspended</option>
            </select>
        </form>
    </td>
    <td><a href="../vendor_profile.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>

<div class="row g-4">
<!-- RECENT RATINGS -->
<div class="col-md-6">
<div class="card">
<div class="card-header-blue"><i class="fas fa-star me-2"></i>Recent Ratings</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Vendor</th><th>Passenger</th><th>Score</th><th>Time</th></tr></thead>
<tbody>
<?php while ($r = $recentRatings->fetch_assoc()): ?>
<tr>
    <td><?= htmlspecialchars(substr($r['vendor_name'],0,18)) ?>...</td>
    <td><?= htmlspecialchars($r['passenger_name']) ?></td>
    <td><?= renderStars($r['final_score']) ?> <strong><?= $r['final_score'] ?></strong></td>
    <td><small class="text-muted"><?= timeAgo($r['created_at']) ?></small></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>
</div>
</div>

<!-- RECENT COMPLAINTS -->
<div class="col-md-6">
<div class="card">
<div class="card-header-orange"><i class="fas fa-file-alt me-2"></i>Recent Complaints</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Code</th><th>Subject</th><th>Vendor</th><th>Status</th></tr></thead>
<tbody>
<?php while ($c = $recentComplaints->fetch_assoc()): ?>
<tr>
    <td><code style="font-size:0.72rem;"><?= $c['complaint_code'] ?></code></td>
    <td style="font-size:0.82rem;"><?= htmlspecialchars(substr($c['subject'],0,22)) ?>...</td>
    <td style="font-size:0.82rem;"><?= htmlspecialchars(substr($c['vendor_name'],0,16)) ?>...</td>
    <td><span class="badge status-<?= $c['status'] ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span></td>
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
</div>
</div>

<?php include '../includes/footer.php'; ?>
