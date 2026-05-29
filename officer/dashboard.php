<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Inspection Officer Dashboard';
if (!isLoggedIn() || !isOfficer()) { $_SESSION['msg_error'] = 'Officer access required.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];
$unreadMsgs = getUnreadMessageCount($uid);

// Handle report submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendor_id   = (int)$_POST['vendor_id'];
    $insp_date   = sanitize($_POST['inspection_date']);
    $c  = (int)$_POST['cleanliness'];
    $f  = (int)$_POST['food_quality'];
    $p  = (int)$_POST['packaging'];
    $s  = (int)$_POST['staff_hygiene'];
    $t  = (int)$_POST['timeliness'];
    $violations     = sanitize($_POST['violations']);
    $recommendations= sanitize($_POST['recommendations']);

    if (!$vendor_id || !$insp_date || !$c || !$f || !$p || !$s || !$t) {
        $error = 'Please fill all required fields.';
    } else {
        $score = calcHygieneScore($c, $f, $p, $s, $t);
        $conn->query("INSERT INTO inspection_reports (officer_id, vendor_id, inspection_date, cleanliness, food_quality, packaging, staff_hygiene, timeliness, inspection_score, violations, recommendations)
            VALUES ($uid, $vendor_id, '$insp_date', $c, $f, $p, $s, $t, $score, '$violations', '$recommendations')");

        // Update vendor status if score very low
        if ($score < 2.0) {
            $conn->query("UPDATE vendors SET status='under_review' WHERE id=$vendor_id");
            $conn->query("INSERT INTO alerts (vendor_id, alert_type, message) VALUES ($vendor_id, 'critical', 'Inspection score critically low: $score/5. Vendor flagged for review.')");
        }
        $_SESSION['msg_success'] = "Inspection report submitted! Score: $score/5.00";
        redirect('dashboard.php');
    }
}

$myReports = $conn->query("
    SELECT ir.*, v.vendor_name
    FROM inspection_reports ir JOIN vendors v ON ir.vendor_id=v.id
    WHERE ir.officer_id=$uid ORDER BY ir.inspection_date DESC
");
$totalReports = $conn->query("SELECT COUNT(*) as c FROM inspection_reports WHERE officer_id=$uid")->fetch_assoc()['c'];
$thisMonth    = $conn->query("SELECT COUNT(*) as c FROM inspection_reports WHERE officer_id=$uid AND MONTH(inspection_date)=MONTH(NOW())")->fetch_assoc()['c'];
$vendors      = $conn->query("SELECT id, vendor_name, station FROM vendors WHERE status != 'suspended' ORDER BY vendor_name");

include '../includes/header.php';
?>

<div class="page-hero py-4">
<div class="container">
    <a href="../index.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
    <h1 style="font-size:2rem;"><i class="fas fa-clipboard-check me-2"></i>Inspection Officer Panel</h1>
    <p class="mb-0" style="opacity:0.8;">Welcome, <?= htmlspecialchars($_SESSION['name']) ?></p>
</div>
</div>

<div class="container py-4">
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="stat-num text-primary"><?= $totalReports ?></div><div class="stat-label">Total Inspections</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-num" style="color:var(--orange);"><?= $thisMonth ?></div><div class="stat-label">This Month</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="stat-num text-success"><?= $conn->query("SELECT COUNT(*) as c FROM vendors WHERE status='active'")->fetch_assoc()['c'] ?></div><div class="stat-label">Active Vendors</div></div></div>
    <div class="col-md-3"><a href="complaints.php" style="text-decoration:none;"><div class="stat-card"><div class="stat-num text-danger"><?= $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('submitted','under_verification')")->fetch_assoc()['c'] ?></div><div class="stat-label">Pending Complaints</div></div></a></div>
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
        <a href="vendor_chat.php" style="text-decoration:none;">
            <div class="stat-card" style="border-top:4px solid #1a8a45;">
                <div style="font-size:2rem;">🏪</div>
                <?php
                $ucVendorsDash = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='vendor' AND m.is_read=0")->fetch_assoc()['c'];
                ?>
                <div class="stat-num" style="color:#1a8a45;"><?= $ucVendorsDash ?></div>
                <div class="stat-label">Unread from Vendors</div>
                <?php if ($ucVendorsDash > 0): ?>
                <span class="badge bg-danger mt-1">New</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
</div>

<div class="row g-4">
<!-- SUBMIT REPORT -->
<div class="col-lg-5">
<?php if ($error): ?><div class="alert alert-danger mb-3"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>
<div class="card">
<div class="card-header-blue"><i class="fas fa-plus me-2"></i>Submit Inspection Report</div>
<div class="p-4">
<form method="POST" id="inspForm">
    <div class="mb-3">
        <label class="form-label">Select Vendor *</label>
        <select name="vendor_id" class="form-select" required>
            <option value="">-- Choose Vendor --</option>
            <?php $vendors->data_seek(0); while ($v = $vendors->fetch_assoc()): ?>
            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['vendor_name']) ?> (<?= htmlspecialchars($v['station']) ?>)</option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Inspection Date *</label>
        <input type="date" name="inspection_date" class="form-control" max="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d') ?>" required>
    </div>

    <div class="mb-2 fw-700" style="color:#003580;font-size:0.88rem;border-bottom:2px solid #003580;padding-bottom:5px;">Rate Each Parameter (1–5)</div>

    <?php $params = [
        ['cleanliness','Cleanliness','30%'],['food_quality','Food Quality','25%'],
        ['staff_hygiene','Staff Hygiene','20%'],['packaging','Packaging','15%'],['timeliness','Timeliness','10%']
    ]; foreach ($params as $idx => $rp): ?>
    <div class="mb-3">
        <label class="form-label"><?= $rp[1] ?> <span class="text-muted">(<?= $rp[2] ?>)</span></label>
        <div class="d-flex gap-2 align-items-center">
            <?php for ($s=1;$s<=5;$s++): ?>
            <span class="rating-input-star" data-param="<?= $rp[0] ?>" data-val="<?= $s ?>" data-idx="<?= $idx ?>"
                onclick="setInspRating('<?= $rp[0] ?>',<?= $s ?>,<?= $idx ?>)">★</span>
            <?php endfor; ?>
            <input type="hidden" name="<?= $rp[0] ?>" id="iinp_<?= $rp[0] ?>" value="">
            <span id="ilabel_<?= $idx ?>" class="text-muted" style="font-size:0.8rem;">Not rated</span>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="p-2 mb-3 rounded text-center" style="background:linear-gradient(90deg,#003580,#0066cc);color:#fff;">
        <div style="font-size:0.78rem;opacity:0.8;">Weighted Inspection Score</div>
        <div style="font-size:1.8rem;font-weight:700;font-family:'Rajdhani',sans-serif;" id="iLiveScore">—</div>
    </div>

    <div class="mb-3">
        <label class="form-label">Violations Found</label>
        <textarea name="violations" class="form-control" rows="3" placeholder="Describe any hygiene violations observed..."></textarea>
    </div>
    <div class="mb-4">
        <label class="form-label">Recommendations</label>
        <textarea name="recommendations" class="form-control" rows="3" placeholder="Corrective actions recommended..."></textarea>
    </div>
    <button type="submit" class="btn btn-primary w-100 py-2 fw-600"><i class="fas fa-paper-plane me-2"></i>Submit Report</button>
</form>
</div>
</div>
</div>

<!-- PAST REPORTS -->
<div class="col-lg-7">
<div class="card">
<div class="card-header-orange"><i class="fas fa-history me-2"></i>My Inspection Reports</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Date</th><th>Vendor</th><th>Score</th><th>Violations</th></tr></thead>
<tbody>
<?php if ($myReports->num_rows === 0): ?>
<tr><td colspan="4" class="text-center py-4 text-muted">No reports submitted yet.</td></tr>
<?php else: while ($r = $myReports->fetch_assoc()): ?>
<tr>
    <td><?= date('d M Y', strtotime($r['inspection_date'])) ?></td>
    <td><strong><?= htmlspecialchars($r['vendor_name']) ?></strong></td>
    <td><?= renderStars($r['inspection_score']) ?> <strong><?= $r['inspection_score'] ?></strong></td>
    <td>
        <?php if ($r['violations'] && $r['violations'] !== 'None'): ?>
        <span class="badge bg-danger" style="font-size:0.7rem;">⚠️ Yes</span>
        <?php else: ?>
        <span class="badge bg-success" style="font-size:0.7rem;">✓ None</span>
        <?php endif; ?>
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

<script>
const iWeights = {cleanliness:0.30,food_quality:0.25,packaging:0.15,staff_hygiene:0.20,timeliness:0.10};
const iValues  = {cleanliness:0,food_quality:0,packaging:0,staff_hygiene:0,timeliness:0};
const iLabels  = ['','Very Poor','Poor','Average','Good','Excellent'];

function setInspRating(param, val, idx) {
    iValues[param] = val;
    document.getElementById('iinp_' + param).value = val;
    document.getElementById('ilabel_' + idx).textContent = iLabels[val];
    document.querySelectorAll(`[data-param="${param}"]`).forEach(s => {
        s.classList.toggle('selected', parseInt(s.dataset.val) <= val);
    });
    // Recalc live score
    let score = 0, allSet = true;
    for (const [k,w] of Object.entries(iWeights)) {
        if (!iValues[k]) { allSet = false; break; }
        score += w * iValues[k];
    }
    const el = document.getElementById('iLiveScore');
    el.textContent = allSet ? score.toFixed(2) + '/5.00' : '—';
}

document.getElementById('inspForm').addEventListener('submit', function(e) {
    for (const k of Object.keys(iValues)) {
        if (!iValues[k]) {
            e.preventDefault();
            showToast('Please rate all 5 parameters.', 'danger');
            return;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
