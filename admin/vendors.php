<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Manage Vendors';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

// Vendor control handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'block_vendor') {
        $vendor_id = (int)$_POST['vendor_id'];
        $reason = sanitize($_POST['reason'] ?? '');
        if (strlen(trim($reason)) < 10) {
            $_SESSION['msg_error'] = 'Reason must be at least 10 characters.';
            redirect('vendors.php');
        }
        $conn->query("UPDATE vendors SET status='suspended' WHERE id=$vendor_id");
        logComplaintHistory(0, $_SESSION['user_id'], 'admin', 'vendor_blocked', $reason);
        $_SESSION['msg_success'] = 'Vendor has been blocked (suspended).';
        redirect('vendors.php');
    }
    if ($_POST['action'] === 'remove_vendor') {
        $vendor_id = (int)$_POST['vendor_id'];
        $reason = sanitize($_POST['reason'] ?? '');
        if (strlen(trim($reason)) < 10) {
            $_SESSION['msg_error'] = 'Reason must be at least 10 characters.';
            redirect('vendors.php');
        }
        $conn->query("UPDATE vendors SET status='removed' WHERE id=$vendor_id");
        logComplaintHistory(0, $_SESSION['user_id'], 'admin', 'vendor_removed', $reason);
        $_SESSION['msg_success'] = 'Vendor has been permanently removed.';
        redirect('vendors.php');
    }
    if ($_POST['action'] === 'adjust_rating') {
        $vendor_id = (int)$_POST['vendor_id'];
        $score = (float)$_POST['new_score'];
        $reason = sanitize($_POST['reason'] ?? '');
        $admin_id = $_SESSION['user_id'];
        if ($score < 0 || $score > 5) {
            $_SESSION['msg_error'] = 'Score must be between 0.00 and 5.00.';
            redirect('vendors.php');
        }
        if (strlen(trim($reason)) < 10) {
            $_SESSION['msg_error'] = 'Reason must be at least 10 characters.';
            redirect('vendors.php');
        }
        $oldRes = $conn->query("SELECT current_score FROM vendors WHERE id=$vendor_id")->fetch_assoc();
        $old = $oldRes ? $oldRes['current_score'] : 0;
        $conn->query("UPDATE vendors SET current_score=$score WHERE id=$vendor_id");
        $safeReason = $conn->real_escape_string("Admin adjustment: $reason");
        // Derive individual param values from the 5 star inputs (1-5 each)
        $p_c = max(1, min(5, (int)($_POST['cleanliness'] ?? round($score))));
        $p_f = max(1, min(5, (int)($_POST['food_quality'] ?? round($score))));
        $p_p = max(1, min(5, (int)($_POST['packaging'] ?? round($score))));
        $p_s = max(1, min(5, (int)($_POST['staff_hygiene'] ?? round($score))));
        $p_t = max(1, min(5, (int)($_POST['timeliness'] ?? round($score))));
        $today = date('Y-m-d');
        $conn->query("INSERT INTO ratings (vendor_id, passenger_id, travel_date, cleanliness, food_quality, packaging, staff_hygiene, timeliness, final_score, comments)
            VALUES ($vendor_id, $admin_id, '$today', $p_c, $p_f, $p_p, $p_s, $p_t, $score, '$safeReason')");
        $logNote = $conn->real_escape_string("Old: $old → New: $score. $reason");
        logComplaintHistory(0, $admin_id, 'admin', 'rating_adjusted', $logNote);
        if ($score < 2.5) {
            $existing = $conn->query("SELECT id FROM alerts WHERE vendor_id=$vendor_id AND alert_type='low_score' AND is_read=0");
            if ($existing->num_rows === 0) {
                $conn->query("INSERT INTO alerts (vendor_id, alert_type, message) VALUES ($vendor_id, 'low_score', 'Admin manually adjusted score below 2.5 threshold.')");
            }
        }
        $_SESSION['msg_success'] = "Vendor rating adjusted to $score.";
        redirect('vendors.php');
    }
}

// Add vendor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (true) {
        $vname  = sanitize($_POST['vendor_name']);
        $lic    = sanitize($_POST['license_number']);
        $zone   = sanitize($_POST['zone']);
        $station= sanitize($_POST['station']);
        $train  = sanitize($_POST['train_number']);
        $email  = sanitize($_POST['contact_email']);
        $phone  = sanitize($_POST['contact_phone']);
        
        // Create user account for vendor
        $tempPass = password_hash('vendor123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (name,email,phone,password,role) VALUES ('$vname','$email','$phone','$tempPass','vendor')");
        $uid = $conn->insert_id;
        $conn->query("INSERT INTO vendors (user_id,vendor_name,license_number,zone,station,train_number,contact_email,contact_phone)
            VALUES ($uid,'$vname','$lic','$zone','$station','$train','$email','$phone')");
        $vid = $conn->insert_id;
        $conn->query("UPDATE users SET id=id WHERE id=$uid"); // set vendor_id in session if needed
        $conn->query("UPDATE users SET role='vendor' WHERE id=$uid");
        // store vendor_id reference
        $_SESSION['msg_success'] = "Vendor '$vname' added! Login: $email / vendor123";
    }
    redirect('vendors.php');
}

$vendors = $conn->query("
    SELECT v.*, COUNT(DISTINCT r.id) as rating_count
    FROM vendors v LEFT JOIN ratings r ON v.id=r.vendor_id
    GROUP BY v.id ORDER BY v.current_score DESC
");
// Collect vendors into array so we can render table and modals separately
$vendorRows = [];
while ($row = $vendors->fetch_assoc()) $vendorRows[] = $row;
include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">⚙️ Admin Panel</div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="vendors.php" class="active"><i class="fas fa-store fa-fw me-2"></i>Vendors</a>
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
    <a href="users.php"><i class="fas fa-users fa-fw me-2"></i>Users</a>
    <hr style="margin:8px;"><a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center gap-3">
        <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
        <h3 class="fw-700 mb-0"><i class="fas fa-store me-2"></i>Manage Vendors</h3>
    </div>
    <button class="btn btn-orange" data-bs-toggle="modal" data-bs-target="#addVendorModal">
        <i class="fas fa-plus me-2"></i>Add Vendor
    </button>
</div>
<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Vendor</th><th>Zone</th><th>Station</th><th>License</th><th>Score</th><th>Class</th><th>Ratings</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php foreach ($vendorRows as $v):
    $cls = classifyVendor($v['current_score']);
?>
<tr>
    <td><strong><?= htmlspecialchars($v['vendor_name']) ?></strong></td>
    <td><?= htmlspecialchars($v['zone']) ?></td>
    <td><?= htmlspecialchars($v['station']) ?></td>
    <td><code style="font-size:0.75rem;"><?= htmlspecialchars($v['license_number']) ?></code></td>
    <td><?= renderStars($v['current_score']) ?> <strong><?= number_format($v['current_score'],2) ?></strong></td>
    <td><span class="score-badge bg-<?= $cls['class']==='warning'?'warning text-dark':$cls['class'].' text-white' ?>"><?= $cls['icon'] ?> <?= $cls['label'] ?></span></td>
    <td><?= $v['rating_count'] ?></td>
    <td>
        <form method="POST" action="update_vendor_status.php" class="d-inline">
            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
            <select name="status" class="form-select form-select-sm" style="width:120px;" onchange="this.form.submit()">
                <option value="active"       <?= $v['status']==='active'      ?'selected':'' ?>>Active</option>
                <option value="under_review" <?= $v['status']==='under_review' ?'selected':'' ?>>Under Review</option>
                <option value="suspended"   <?= $v['status']==='suspended'    ?'selected':'' ?>>Suspended</option>
                <option value="removed"     <?= $v['status']==='removed'      ?'selected':'' ?>>Removed</option>
            </select>
        </form>
    </td>
    <td>
        <div class="d-flex gap-1 flex-wrap">
            <a href="../vendor_profile.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-secondary" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;" title="View Profile"><i class="fas fa-eye"></i></a>
            <button type="button" class="btn btn-sm btn-outline-primary" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;" title="Adjust Rating" data-bs-toggle="modal" data-bs-target="#ratingModal<?= $v['id'] ?>"><i class="fas fa-star-half-alt"></i></button>
            <button type="button" class="btn btn-sm btn-outline-warning" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;" title="Block Vendor" data-bs-toggle="modal" data-bs-target="#blockModal<?= $v['id'] ?>"><i class="fas fa-ban"></i></button>
            <button type="button" class="btn btn-sm btn-outline-danger" style="width:34px;height:34px;padding:0;display:flex;align-items:center;justify-content:center;" title="Remove Vendor" data-bs-toggle="modal" data-bs-target="#removeModal<?= $v['id'] ?>"><i class="fas fa-trash"></i></button>
        </div>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>

<?php foreach ($vendorRows as $v): ?>
<!-- Block Modal -->
<div class="modal fade" id="blockModal<?= $v['id'] ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-warning text-dark">
    <h5 class="modal-title fw-700">Block Vendor: <?= htmlspecialchars($v['vendor_name']) ?></h5>
    <button class="btn-close" data-bs-dismiss="modal"></button>
</div>
<form method="POST">
<input type="hidden" name="action" value="block_vendor">
<input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
<div class="modal-body">
    <label class="form-label">Reason for blocking <span class="text-muted">(min 10 characters)</span></label>
    <textarea name="reason" class="form-control" rows="3" minlength="10" required placeholder="Enter reason..."></textarea>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-warning">Block Vendor</button>
</div>
</form>
</div>
</div>
</div>

<!-- Remove Modal -->
<div class="modal fade" id="removeModal<?= $v['id'] ?>" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content">
<div class="modal-header bg-danger text-white">
    <h5 class="modal-title fw-700">Remove Vendor: <?= htmlspecialchars($v['vendor_name']) ?></h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form method="POST">
<input type="hidden" name="action" value="remove_vendor">
<input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
<div class="modal-body">
    <div class="alert alert-danger py-2 mb-3" style="font-size:0.85rem;"><i class="fas fa-exclamation-triangle me-1"></i>This action permanently removes the vendor.</div>
    <label class="form-label">Reason for removal <span class="text-muted">(min 10 characters)</span></label>
    <textarea name="reason" class="form-control" rows="3" minlength="10" required placeholder="Enter reason..."></textarea>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-danger">Remove Vendor</button>
</div>
</form>
</div>
</div>
</div>

<!-- Adjust Rating Modal -->
<div class="modal fade" id="ratingModal<?= $v['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-secondary text-white">
    <h5 class="modal-title fw-700"><i class="fas fa-star me-2"></i>Adjust Rating: <?= htmlspecialchars($v['vendor_name']) ?></h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form method="POST" id="ratingForm<?= $v['id'] ?>">
<input type="hidden" name="action" value="adjust_rating">
<input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
<div class="modal-body">
    <div class="alert alert-info py-2 mb-3" style="font-size:0.82rem;">
        <i class="fas fa-calculator me-1"></i>
        <strong>Formula:</strong> Score = 0.30×Cleanliness + 0.25×Food Quality + 0.15×Packaging + 0.20×Staff Hygiene + 0.10×Timeliness
    </div>
    <?php
    $adminParams = [
        ['cleanliness',  'Cleanliness',   'fas fa-broom',       '#003580', '30% weight'],
        ['food_quality', 'Food Quality',  'fas fa-utensils',    '#1a8a45', '25% weight'],
        ['staff_hygiene','Staff Hygiene', 'fas fa-user-shield', '#8b2fc9', '20% weight'],
        ['packaging',    'Packaging',     'fas fa-box',         '#c96010', '15% weight'],
        ['timeliness',   'Timeliness',    'fas fa-clock',       '#c0392b', '10% weight'],
    ];
    foreach ($adminParams as $ai => $rp): ?>
    <div class="mb-3 p-3 rounded" style="background:#f8faff;border:1.5px solid #e0eaff;">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div style="width:36px;height:36px;background:<?= $rp[3] ?>;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="<?= $rp[2] ?> text-white" style="font-size:0.85rem;"></i>
            </div>
            <div class="fw-600"><?= $rp[1] ?> <span class="text-muted fw-400" style="font-size:0.78rem;">(<?= $rp[4] ?>)</span></div>
            <div class="ms-auto fw-700" id="aLabel_<?= $v['id'] ?>_<?= $ai ?>" style="font-family:'Rajdhani',sans-serif;font-size:1.2rem;color:<?= $rp[3] ?>;">?/5</div>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <?php for ($star = 1; $star <= 5; $star++): ?>
            <span class="rating-input-star"
                data-vid="<?= $v['id'] ?>" data-param="<?= $rp[0] ?>" data-val="<?= $star ?>" data-ai="<?= $ai ?>"
                onclick="setAdminRating(<?= $v['id'] ?>,'<?= $rp[0] ?>',<?= $star ?>,<?= $ai ?>)"
                style="font-size:1.6rem;cursor:pointer;color:#ccc;transition:color 0.15s;">★</span>
            <?php endfor; ?>
            <input type="hidden" name="<?= $rp[0] ?>" id="ainp_<?= $v['id'] ?>_<?= $rp[0] ?>" value="" required>
            <span class="ms-1 text-muted" id="alabel_<?= $v['id'] ?>_<?= $ai ?>" style="font-size:0.8rem;">Click to rate</span>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Live score preview -->
    <div class="p-3 rounded mb-3 text-center" style="background:linear-gradient(90deg,#003580,#0066cc);color:#fff;">
        <div style="font-size:0.82rem;opacity:0.8;">Calculated Weighted Score</div>
        <div style="font-size:2rem;font-weight:700;font-family:'Rajdhani',sans-serif;" id="aLiveScore_<?= $v['id'] ?>">—</div>
    </div>
    <input type="hidden" name="new_score" id="aFinalScore_<?= $v['id'] ?>" value="">

    <div class="mb-3">
        <label class="form-label">Reason for adjustment <span class="text-muted">(min 10 characters)</span></label>
        <textarea name="reason" class="form-control" rows="3" minlength="10" required placeholder="Enter reason for this rating adjustment..."></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-secondary" onclick="return validateAdminRating(<?= $v['id'] ?>)">Adjust Rating</button>
</div>
</form>
</div>
</div>
</div>
<?php endforeach; ?>
</div>
</div>
</div>
</div>

<!-- ADD VENDOR MODAL -->
<div class="modal fade" id="addVendorModal" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
    <h5 class="modal-title fw-700">Add New Vendor</h5>
    <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<form method="POST">
<input type="hidden" name="action" value="add">
<div class="modal-body">
<div class="row g-3">
    <div class="col-md-6"><label class="form-label">Vendor Name *</label><input type="text" name="vendor_name" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">License Number *</label><input type="text" name="license_number" class="form-control" required placeholder="LIC-XXX-ZZ"></div>
    <div class="col-md-6"><label class="form-label">Zone</label><input type="text" name="zone" class="form-control" placeholder="e.g. Northern Railway"></div>
    <div class="col-md-6"><label class="form-label">Station</label><input type="text" name="station" class="form-control" placeholder="e.g. New Delhi"></div>
    <div class="col-md-4"><label class="form-label">Train Number</label><input type="text" name="train_number" class="form-control"></div>
    <div class="col-md-4"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control" required></div>
    <div class="col-md-4"><label class="form-label">Contact Phone</label><input type="text" name="contact_phone" class="form-control"></div>
</div>
<div class="alert alert-info mt-3 mb-0" style="font-size:0.82rem;">
    <i class="fas fa-info-circle me-1"></i>A vendor account will be created automatically. Default password: <code>vendor123</code>
</div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
    <button type="submit" class="btn btn-primary">Add Vendor</button>
</div>
</form>
</div>
</div>
</div>

<?php include '../includes/footer.php'; ?>
<script>
const aWeights = {cleanliness:0.30,food_quality:0.25,packaging:0.15,staff_hygiene:0.20,timeliness:0.10};
const aValues  = {};
const aLabels  = ['','Very Poor','Poor','Average','Good','Excellent'];

function setAdminRating(vid, param, val, ai) {
    if (!aValues[vid]) aValues[vid] = {};
    aValues[vid][param] = val;
    document.getElementById('ainp_' + vid + '_' + param).value = val;
    document.getElementById('aLabel_' + vid + '_' + ai).textContent = val + '/5';
    document.getElementById('alabel_' + vid + '_' + ai).textContent = aLabels[val];
    // Highlight stars for this vendor+param
    document.querySelectorAll(`[data-vid="${vid}"][data-param="${param}"]`).forEach(s => {
        s.style.color = parseInt(s.dataset.val) <= val ? '#f5a623' : '#ccc';
    });
    // Recalc weighted score
    let score = 0, allSet = true;
    for (const [k, w] of Object.entries(aWeights)) {
        if (!aValues[vid] || !aValues[vid][k]) { allSet = false; break; }
        score += w * aValues[vid][k];
    }
    const el = document.getElementById('aLiveScore_' + vid);
    if (allSet) {
        score = Math.round(score * 100) / 100;
        el.textContent = score.toFixed(2) + '/5.00';
        document.getElementById('aFinalScore_' + vid).value = score.toFixed(2);
    } else {
        el.textContent = '—';
        document.getElementById('aFinalScore_' + vid).value = '';
    }
}

function validateAdminRating(vid) {
    for (const k of Object.keys(aWeights)) {
        if (!aValues[vid] || !aValues[vid][k]) {
            alert('Please rate all 5 parameters before submitting.');
            return false;
        }
    }
    return true;
}
</script>
