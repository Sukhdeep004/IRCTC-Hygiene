<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Vendor Dashboard';
if (!isLoggedIn() || !isVendor()) { $_SESSION['msg_error'] = 'Vendor access required.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];
$vendor = $conn->query("SELECT * FROM vendors WHERE user_id=$uid")->fetch_assoc();
if (!$vendor) { $_SESSION['msg_error'] = 'Vendor profile not found.'; redirect('../logout.php'); }
$vid = $vendor['id'];
$unreadMsgs = getUnreadMessageCount($uid);

$cls = classifyVendor($vendor['current_score']);

$paramAvgs = $conn->query("
    SELECT AVG(cleanliness) as ac, AVG(food_quality) as af, AVG(packaging) as ap,
           AVG(staff_hygiene) as as2, AVG(timeliness) as at2, COUNT(*) as total
    FROM ratings WHERE vendor_id=$vid
")->fetch_assoc();

$recentRatings = $conn->query("
    SELECT r.*, u.name as passenger_name FROM ratings r
    JOIN users u ON r.passenger_id=u.id
    WHERE r.vendor_id=$vid ORDER BY r.created_at DESC LIMIT 10
");

$complaints = $conn->query("
    SELECT c.*, u.name as passenger_name FROM complaints c
    JOIN users u ON c.passenger_id=u.id
    WHERE c.vendor_id=$vid ORDER BY c.created_at DESC
");

$pendingComplaints = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE vendor_id=$vid AND status IN ('submitted','under_verification','forwarded_to_admin')")->fetch_assoc()['c'];

// Vendor comment on complaint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $cid     = (int)$_POST['complaint_id'];
    $comment = sanitize($_POST['vendor_comment']);
    $now     = date('Y-m-d H:i:s');
    $conn->query("UPDATE complaints SET vendor_comment='$comment', vendor_commented_at='$now' WHERE id=$cid AND vendor_id=$vid");
    logComplaintHistory($cid, $vid, 'vendor', 'commented', $comment);
    $_SESSION['msg_success'] = 'Your comment has been submitted.';
    redirect('dashboard.php');
}


include '../includes/header.php';
?>

<div class="page-hero py-4">
<div class="container">
    <a href="../index.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
    <div class="d-flex align-items-center gap-3">
        <div style="width:55px;height:55px;background:var(--orange);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:1.8rem;">🏪</div>
        <div>
            <h1 style="font-size:1.8rem;"><?= htmlspecialchars($vendor['vendor_name']) ?></h1>
            <p class="mb-0" style="opacity:0.8;"><i class="fas fa-map-marker-alt me-1"></i><?= htmlspecialchars($vendor['station']) ?> | License: <?= htmlspecialchars($vendor['license_number']) ?></p>
        </div>
    </div>
</div>
</div>

<div class="container py-4">

<!-- STATUS BANNER -->
<?php if ($vendor['status'] === 'under_review'): ?>
<div class="alert alert-warning mb-4"><i class="fas fa-exclamation-triangle me-2"></i><strong>Under Review:</strong> Your hygiene score has dropped below 2.5. Immediate corrective action is required. An inspection will be scheduled.</div>
<?php elseif ($vendor['status'] === 'suspended'): ?>
<div class="alert alert-danger mb-4"><i class="fas fa-ban me-2"></i><strong>Suspended:</strong> Your vendor account has been suspended. Please contact IRCTC administration.</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card" style="border-top:4px solid #003580;">
            <div style="font-size:2.5rem;font-weight:700;font-family:'Rajdhani',sans-serif;color:#003580;"><?= number_format($vendor['current_score'],2) ?></div>
            <div><?= renderStars($vendor['current_score']) ?></div>
            <div class="stat-label mt-1">Current Hygiene Score</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num" style="color:var(--orange);"><?= $paramAvgs['total'] ?></div>
            <div class="stat-label">Total Ratings Received</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num text-danger"><?= $pendingComplaints ?></div>
            <div class="stat-label">Pending Complaints</div>
        </div>
    </div>
    <div class="col-md-3">
        <a href="messages.php" style="text-decoration:none;">
            <div class="stat-card" style="border-top:4px solid #0066cc;">
                <div style="font-size:2rem;">💬</div>
                <div class="stat-num text-primary"><?= $unreadMsgs ?></div>
                <div class="stat-label">Unread Messages</div>
                <?php if ($unreadMsgs > 0): ?>
                <span class="badge bg-danger mt-1">New</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <a href="officer_chat.php" style="text-decoration:none;">
            <div class="stat-card" style="border-top:4px solid #1a8a45;">
                <div style="font-size:2rem;">🛡️</div>
                <?php
                $ucOfficersDash = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='officer' AND m.is_read=0")->fetch_assoc()['c'];
                ?>
                <div class="stat-num" style="color:#1a8a45;"><?= $ucOfficersDash ?></div>
                <div class="stat-label">Unread from Officers</div>
                <?php if ($ucOfficersDash > 0): ?>
                <span class="badge bg-danger mt-1">New</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <div class="col-md-3">
        <div class="stat-card" style="border-top:4px solid <?= $cls['class']==='success'?'#1a8a45':($cls['class']==='primary'?'#0066cc':($cls['class']==='warning'?'#e6a817':($cls['class']==='danger'?'#c0392b':'#222'))) ?>">
            <div style="font-size:2rem;"><?= $cls['icon'] ?></div>
            <div class="fw-700" style="font-size:1.1rem;"><?= $cls['label'] ?></div>
            <div class="stat-label">Classification</div>
        </div>
    </div>
</div>

<div class="row g-4">
<!-- PARAMETER BREAKDOWN -->
<div class="col-lg-4">
    <div class="card p-4 mb-4">
        <h6 class="fw-700 mb-3"><i class="fas fa-chart-bar me-2 text-primary"></i>Parameter Performance</h6>
        <?php
        $params = [
            ['Cleanliness','fas fa-broom',$paramAvgs['ac'],30,'#003580'],
            ['Food Quality','fas fa-utensils',$paramAvgs['af'],25,'#1a8a45'],
            ['Staff Hygiene','fas fa-user-shield',$paramAvgs['as2'],20,'#8b2fc9'],
            ['Packaging','fas fa-box',$paramAvgs['ap'],15,'#c96010'],
            ['Timeliness','fas fa-clock',$paramAvgs['at2'],10,'#c0392b'],
        ];
        foreach ($params as $p):
            $val = round($p[2] ?? 0, 2);
            $pct = ($val / 5) * 100;
            $color = $pct >= 70 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
        ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1" style="font-size:0.82rem;">
                <span><i class="<?= $p[1] ?> me-1" style="color:<?= $p[4] ?>;"></i><?= $p[0] ?> <span class="text-muted">(<?= $p[3] ?>%)</span></span>
                <strong class="<?= $val < 2.5 ? 'text-danger' : 'text-success' ?>"><?= $val ?>/5</strong>
            </div>
            <div class="progress" style="height:8px;">
                <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
            </div>
            <?php if ($val < 2.5): ?>
            <div class="text-danger" style="font-size:0.72rem;"><i class="fas fa-exclamation-triangle me-1"></i>Needs immediate improvement</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- IMPROVEMENT SUGGESTIONS -->
    <div class="card p-4">
        <h6 class="fw-700 mb-3"><i class="fas fa-lightbulb me-2 text-warning"></i>Improvement Suggestions</h6>
        <ul class="list-unstyled mb-0" style="font-size:0.82rem;">
            <?php if (($paramAvgs['ac'] ?? 0) < 3): ?><li class="mb-2">🧹 <strong>Cleanliness:</strong> Deep clean kitchen and serving areas daily. Sanitize equipment after each use.</li><?php endif; ?>
            <?php if (($paramAvgs['af'] ?? 0) < 3): ?><li class="mb-2">🍽️ <strong>Food Quality:</strong> Use fresh ingredients. Check food storage temperatures regularly.</li><?php endif; ?>
            <?php if (($paramAvgs['as2'] ?? 0) < 3): ?><li class="mb-2">👤 <strong>Staff Hygiene:</strong> Ensure all staff wear gloves, hair nets, and clean uniforms.</li><?php endif; ?>
            <?php if (($paramAvgs['ap'] ?? 0) < 3): ?><li class="mb-2">📦 <strong>Packaging:</strong> Use sealed, tamper-proof packaging. Check for damage before serving.</li><?php endif; ?>
            <?php if (($paramAvgs['at2'] ?? 0) < 3): ?><li class="mb-2">⏱️ <strong>Timeliness:</strong> Plan delivery schedules in advance. Communicate delays proactively.</li><?php endif; ?>
            <?php if (($paramAvgs['total'] ?? 0) == 0): ?><li class="text-muted">No ratings yet. Keep maintaining high standards!</li><?php endif; ?>
            <?php if ($vendor['current_score'] >= 4.0): ?><li class="text-success">✅ Excellent performance! Keep it up!</li><?php endif; ?>
        </ul>
    </div>
</div>

<!-- RECENT RATINGS -->
<div class="col-lg-8">
    <div class="card mb-4">
        <div class="card-header-blue"><i class="fas fa-star me-2"></i>Recent Passenger Ratings</div>
        <div class="table-responsive">
        <table class="table mb-0">
        <thead><tr><th>Passenger</th><th>C</th><th>F</th><th>P</th><th>S</th><th>T</th><th>Score</th><th>Date</th><th>Comment</th></tr></thead>
        <tbody>
        <?php if ($recentRatings->num_rows === 0): ?>
        <tr><td colspan="9" class="text-center py-3 text-muted">No ratings received yet.</td></tr>
        <?php else: while ($r = $recentRatings->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($r['passenger_name']) ?></td>
            <td><?= $r['cleanliness'] ?></td>
            <td><?= $r['food_quality'] ?></td>
            <td><?= $r['packaging'] ?></td>
            <td><?= $r['staff_hygiene'] ?></td>
            <td><?= $r['timeliness'] ?></td>
            <td><?= renderStars($r['final_score']) ?> <strong><?= $r['final_score'] ?></strong></td>
            <td><small><?= date('d M Y', strtotime($r['travel_date'])) ?></small></td>
            <td style="max-width:150px;font-size:0.78rem;"><?= $r['comments'] ? htmlspecialchars(substr($r['comments'],0,40)).'...' : '—' ?></td>
        </tr>
        <?php endwhile; endif; ?>
        </tbody>
        </table>
        </div>
    </div>

    <!-- COMPLAINTS -->
    <div class="card">
        <div class="card-header-orange"><i class="fas fa-file-alt me-2"></i>Complaints & Responses</div>
        <div class="p-3">
        <?php if ($complaints->num_rows === 0): ?>
        <p class="text-center text-muted py-3">No complaints received. Keep up the good work!</p>
        <?php else: $complaints->data_seek(0); while ($c = $complaints->fetch_assoc()): ?>
        <div class="border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between align-items-start mb-1">
                <strong style="font-size:0.88rem;"><?= htmlspecialchars($c['subject']) ?></strong>
                <span class="badge status-<?= $c['status'] ?>"><?= ucfirst(str_replace('_',' ',$c['status'])) ?></span>
            </div>
            <div class="text-muted" style="font-size:0.75rem;">By <?= htmlspecialchars($c['passenger_name']) ?> | <code><?= $c['complaint_code'] ?></code> | <?= timeAgo($c['created_at']) ?></div>
            <p class="mb-2 mt-1" style="font-size:0.82rem;"><?= htmlspecialchars($c['description']) ?></p>

            <?php if (!empty($c['image_path'])): ?>
            <div class="mt-2 mb-2">
                <a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank">
                    <img src="../<?= htmlspecialchars($c['image_path']) ?>" alt="Complaint image"
                         style="max-height:120px;border-radius:6px;border:1px solid #dee2e6;">
                </a>
            </div>
            <?php endif; ?>

            <?php if (!empty($c['vendor_comment'])): ?>
            <div class="p-2 rounded mt-1" style="background:#d1e7dd;font-size:0.8rem;">
                <i class="fas fa-reply me-1 text-success"></i><strong>Your Comment:</strong> <?= htmlspecialchars($c['vendor_comment']) ?>
                <span class="text-muted ms-1">(<?= timeAgo($c['vendor_commented_at']) ?>)</span>
            </div>
            <?php endif; ?>
            <?php if (!in_array($c['status'], ['withdrawn','closed','rejected'])): ?>
            <div class="d-flex gap-2 mt-2 flex-wrap">
                <form method="POST" class="flex-grow-1">
                    <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                    <div class="input-group">
                        <input type="text" name="vendor_comment" class="form-control form-control-sm"
                               placeholder="<?= empty($c['vendor_comment']) ? 'Add your comment...' : 'Update your comment...' ?>" required>
                        <button class="btn btn-sm btn-primary"><?= empty($c['vendor_comment']) ? 'Comment' : 'Update' ?></button>
                    </div>
                </form>
                <a href="messages.php" class="btn btn-sm btn-outline-secondary" title="Message Admin about this complaint">
                    <i class="fas fa-comments me-1"></i>Message Admin
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; endif; ?>
        </div>
    </div>
</div>
</div>

</div>
<?php include '../includes/footer.php'; ?>
