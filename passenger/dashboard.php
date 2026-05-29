<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'My Dashboard';
if (!isLoggedIn() || !isPassenger()) { $_SESSION['msg_error'] = 'Please login as passenger.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];
$myRatings     = $conn->query("SELECT COUNT(*) as c FROM ratings WHERE passenger_id=$uid")->fetch_assoc()['c'];
$myComplaints  = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE passenger_id=$uid")->fetch_assoc()['c'];
$resolvedCompl = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE passenger_id=$uid AND status='resolved'")->fetch_assoc()['c'];

$recentRatings = $conn->query("
    SELECT r.*, v.vendor_name FROM ratings r
    JOIN vendors v ON r.vendor_id = v.id
    WHERE r.passenger_id=$uid ORDER BY r.created_at DESC LIMIT 5
");
$recentComplaints = $conn->query("
    SELECT c.*, v.vendor_name FROM complaints c
    JOIN vendors v ON c.vendor_id = v.id
    WHERE c.passenger_id=$uid ORDER BY c.created_at DESC LIMIT 5
");
include '../includes/header.php';
?>
<div class="container py-4">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="../index.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
</div>
<h3 class="fw-700 mb-1">Welcome, <?= htmlspecialchars($_SESSION['name']) ?>! 👋</h3>
<p class="text-muted mb-4">Passenger Dashboard</p>

<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-num text-primary"><?= $myRatings ?></div>
            <div class="stat-label">Ratings Given</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-num text-danger"><?= $myComplaints ?></div>
            <div class="stat-label">Complaints Filed</div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="stat-card">
            <div class="stat-num text-success"><?= $resolvedCompl ?></div>
            <div class="stat-label">Complaints Resolved</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <a href="rate.php" class="btn btn-orange w-100 py-3 fw-600"><i class="fas fa-star me-2"></i>Rate a Vendor</a>
    </div>
    <div class="col-md-4">
        <a href="complaint.php" class="btn btn-outline-danger w-100 py-3 fw-600"><i class="fas fa-file-alt me-2"></i>File Complaint</a>
    </div>
    <div class="col-md-4">
        <a href="../vendors_list.php" class="btn btn-outline-primary w-100 py-3 fw-600"><i class="fas fa-store me-2"></i>Browse Vendors</a>
    </div>
</div>

<div class="row g-4">
<div class="col-md-6">
    <div class="card">
        <div class="card-header-blue"><i class="fas fa-star me-2"></i>My Recent Ratings</div>
        <div class="p-3">
        <?php if ($recentRatings->num_rows === 0): ?>
        <p class="text-muted text-center py-3">No ratings submitted yet.</p>
        <?php else: while ($r = $recentRatings->fetch_assoc()): ?>
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
            <div>
                <strong style="font-size:0.88rem;"><?= htmlspecialchars($r['vendor_name']) ?></strong>
                <div><?= renderStars($r['final_score']) ?> <span class="fw-600 text-primary"><?= $r['final_score'] ?></span></div>
                <small class="text-muted"><?= date('d M Y', strtotime($r['travel_date'])) ?></small>
            </div>
            <a href="../vendor_profile.php?id=<?= $r['vendor_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
        </div>
        <?php endwhile; endif; ?>
        </div>
    </div>
</div>
<div class="col-md-6">
    <div class="card">
        <div class="card-header-orange"><i class="fas fa-file-alt me-2"></i>My Complaints</div>
        <div class="p-3">
        <?php if ($recentComplaints->num_rows === 0): ?>
        <p class="text-muted text-center py-3">No complaints filed yet.</p>
        <?php else: while ($c = $recentComplaints->fetch_assoc()): ?>
        <div class="border-bottom pb-2 mb-2" style="font-size:0.88rem;">
            <div class="d-flex justify-content-between">
                <strong><?= htmlspecialchars(substr($c['subject'],0,35)) ?>...</strong>
                <span class="badge status-<?= $c['status'] ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
            </div>
            <div class="text-muted"><?= htmlspecialchars($c['vendor_name']) ?> | <code><?= $c['complaint_code'] ?></code></div>
        </div>
        <?php endwhile; endif; ?>
        </div>
    </div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
