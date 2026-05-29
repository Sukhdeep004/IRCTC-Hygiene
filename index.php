<?php
require_once 'includes/config.php';
$pageTitle = 'Home';

$totalVendors   = ($r = $conn->query("SELECT COUNT(*) as c FROM vendors WHERE status='active'")) ? ($r->fetch_assoc()['c'] ?? 0) : 0;
$totalRatings   = ($r = $conn->query("SELECT COUNT(*) as c FROM ratings")) ? ($r->fetch_assoc()['c'] ?? 0) : 0;
$totalComplaints= ($r = $conn->query("SELECT COUNT(*) as c FROM complaints")) ? ($r->fetch_assoc()['c'] ?? 0) : 0;
$avgScore       = ($r = $conn->query("SELECT AVG(final_score) as a FROM ratings")) ? ($r->fetch_assoc()['a'] ?? null) : null;

$topVendors = $conn->query("
    SELECT v.*, COUNT(r.id) as rating_count
    FROM vendors v
    LEFT JOIN ratings r ON v.id = r.vendor_id
    WHERE v.status='active'
    GROUP BY v.id
    ORDER BY v.current_score DESC
    LIMIT 6
");

include 'includes/header.php';
?>

<!-- HERO -->
<div class="page-hero">
<div class="container">
    <div class="row align-items-center">
        <div class="col-lg-7">
            <div class="mb-3">
                <span class="badge" style="background:var(--orange);font-size:0.8rem;padding:7px 14px;">🚂 Indian Railways Catering Oversight</span>
            </div>
            <h1>IRCTC Food Hygiene<br>Rating System</h1>
            <p class="mt-3 mb-4" style="opacity:0.85;font-size:1.05rem;">A structured digital platform for monitoring railway catering hygiene. Rate vendors, track complaints, and ensure food safety across the railway network.</p>
            <div class="d-flex gap-3 flex-wrap">
                <?php if (!isLoggedIn()): ?>
                <a href="register.php" class="btn btn-orange btn-lg px-4"><i class="fas fa-user-plus me-2"></i>Register as Passenger</a>
                <a href="login.php" class="btn btn-lg px-4" style="background:rgba(255,255,255,0.15);color:#fff;border:1.5px solid rgba(255,255,255,0.4);">Login</a>
                <?php else: ?>
                <a href="passenger/rate.php" class="btn btn-orange btn-lg px-4"><i class="fas fa-star me-2"></i>Rate a Vendor</a>
                <a href="vendors_list.php" class="btn btn-lg px-4" style="background:rgba(255,255,255,0.15);color:#fff;border:1.5px solid rgba(255,255,255,0.4);">View All Vendors</a>
                <?php endif; ?>
            </div>
            <div class="mt-3">
                <div class="d-inline-flex align-items-center gap-2 px-3 py-2 rounded" style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.3);">
                    <i class="fas fa-clock" style="color:#ffd700;"></i>
                    <span style="font-size:0.9rem;">Action is taken within <strong>8 hours</strong> of complaint submission</span>
                </div>
            </div>
        </div>
        <div class="col-lg-5 d-none d-lg-block text-center">
            <div style="font-size:7rem;filter:drop-shadow(0 10px 20px rgba(0,0,0,0.3));">🔍</div>
        </div>
    </div>
</div>
</div>

<!-- STATS -->
<div class="container" style="margin-top:-28px;position:relative;z-index:5;">
<div class="row g-3">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-num text-primary"><?= $totalVendors ?></div>
            <div class="stat-label">Active Vendors</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-num" style="color:var(--orange);"><?= $totalRatings ?>+</div>
            <div class="stat-label">Ratings Submitted</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-num text-danger"><?= $totalComplaints ?></div>
            <div class="stat-label">Complaints Tracked</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-num" style="color:#f5a623;">⭐<?= $avgScore ? number_format($avgScore,1) : '—' ?></div>
            <div class="stat-label">Avg Hygiene Score</div>
        </div>
    </div>
</div>
</div>

<!-- SCORING FORMULA INFO -->
<div class="container mt-5">
<div class="card p-4" style="background:linear-gradient(135deg,#003580,#0066cc);color:#fff;">
<div class="row align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold"><i class="fas fa-calculator me-2"></i>Weighted Hygiene Scoring Formula</h4>
        <p class="mb-2 mt-2" style="opacity:0.85;">Each vendor is evaluated using a scientific weighted average based on 5 key parameters:</p>
        <div class="row g-2 mt-1">
            <?php $params = [['Cleanliness','30%','fas fa-broom'],['Food Quality','25%','fas fa-utensils'],['Staff Hygiene','20%','fas fa-user-shield'],['Packaging','15%','fas fa-box'],['Timeliness','10%','fas fa-clock']]; ?>
            <?php foreach ($params as $p): ?>
            <div class="col-6">
                <div style="background:rgba(255,255,255,0.12);padding:8px 12px;border-radius:8px;display:flex;align-items:center;gap:8px;font-size:0.83rem;">
                    <i class="<?= $p[2] ?>" style="color:#ffd700;width:16px;"></i>
                    <span><?= $p[0] ?></span>
                    <span class="ms-auto fw-700" style="color:#ffd700;"><?= $p[1] ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="col-md-5 text-center mt-3 mt-md-0">
        <div style="background:rgba(0,0,0,0.2);padding:20px;border-radius:12px;font-family:monospace;font-size:0.9rem;line-height:1.8;">
            <div style="color:#ffd700;font-size:1rem;margin-bottom:8px;">Score Formula:</div>
            Score = <span style="color:#7ee8ff;">0.30</span>×C +<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7ee8ff;">0.25</span>×F +<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7ee8ff;">0.15</span>×P +<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7ee8ff;">0.20</span>×S +<br>
            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="color:#7ee8ff;">0.10</span>×T
        </div>
    </div>
</div>
</div>
</div>

<!-- VENDOR CLASSIFICATION -->
<div class="container mt-5">
<h4 class="fw-bold mb-3"><i class="fas fa-tags me-2 text-primary"></i>Vendor Classification Scale</h4>
<div class="row g-2">
    <?php
    $classes = [
        ['4.5–5.0','Excellent','#1a8a45','🏆'],
        ['3.5–4.49','Good','#0066cc','👍'],
        ['2.5–3.49','Average','#e6a817','⚠️'],
        ['1.5–2.49','Poor','#c0392b','👎'],
        ['Below 1.5','Critical','#222','🚨'],
    ];
    foreach ($classes as $c):
    ?>
    <div class="col-6 col-md" >
        <div class="text-center p-3" style="background:#fff;border-radius:10px;box-shadow:var(--shadow);border-top:4px solid <?= $c[2] ?>;">
            <div style="font-size:1.8rem;"><?= $c[3] ?></div>
            <div class="fw-700" style="color:<?= $c[2] ?>;font-family:'Rajdhani',sans-serif;"><?= $c[1] ?></div>
            <div class="text-muted" style="font-size:0.8rem;"><?= $c[0] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>

<!-- TOP VENDORS -->
<div class="container mt-5">
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="fas fa-trophy me-2 text-warning"></i>Top Rated Vendors</h4>
    <a href="vendors_list.php" class="btn btn-outline-primary btn-sm">View All</a>
</div>
<div class="row g-3">
<?php while ($v = $topVendors->fetch_assoc()):
    $cls = classifyVendor($v['current_score']);
?>
<div class="col-md-4">
    <div class="card p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div style="width:50px;height:50px;background:linear-gradient(135deg,#003580,#0066cc);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0;">🏪</div>
            <div>
                <div class="fw-700"><?= htmlspecialchars($v['vendor_name']) ?></div>
                <div class="text-muted" style="font-size:0.78rem;"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($v['station']) ?></div>
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between">
            <div>
                <?= renderStars($v['current_score']) ?>
                <span class="ms-2 fw-600 text-primary"><?= number_format($v['current_score'],2) ?></span>
                <div class="text-muted" style="font-size:0.75rem;"><?= $v['rating_count'] ?> ratings</div>
            </div>
            <span class="score-badge bg-<?= $cls['class'] == 'warning' ? 'warning text-dark' : ($cls['class'].' text-white') ?>">
                <?= $cls['icon'] ?> <?= $cls['label'] ?>
            </span>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>
</div>

<!-- HOW IT WORKS -->
<div class="container mt-5 mb-5">
<h4 class="fw-bold text-center mb-4">How It Works</h4>
<div class="row g-3">
    <?php $steps = [
        ['fas fa-user-plus','Register','Create your account as Passenger, Vendor, or Inspection Officer.','#003580'],
        ['fas fa-star','Rate Vendor','Submit structured ratings based on 5 hygiene parameters after your journey.','var(--orange)'],
        ['fas fa-calculator','Score Calculated','Weighted algorithm calculates vendor hygiene score automatically.','#1a8a45'],
        ['fas fa-bell','Admin Acts','Alerts trigger for underperforming vendors. Inspections are scheduled.','#c0392b'],
    ]; foreach ($steps as $i => $s): ?>
    <div class="col-6 col-md-3">
        <div class="card p-4 text-center h-100">
            <div style="width:55px;height:55px;background:<?= $s[3] ?>;border-radius:14px;margin:0 auto 12px;display:flex;align-items:center;justify-content:center;">
                <i class="<?= $s[0] ?> fa-lg text-white"></i>
            </div>
            <div class="fw-700 mb-2">Step <?= $i+1 ?>: <?= $s[1] ?></div>
            <p class="text-muted mb-0" style="font-size:0.82rem;"><?= $s[2] ?></p>
        </div>
    </div>
    <?php endforeach; ?>
</div>
</div>

<?php include 'includes/footer.php'; ?>
