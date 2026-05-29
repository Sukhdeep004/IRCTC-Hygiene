<?php
require_once '../includes/config.php';
require_once '../includes/pnr_module.php';
define('BASEPATH', '../');
$pageTitle = 'Rate a Vendor';
if (!isLoggedIn() || !isPassenger()) { $_SESSION['msg_error'] = 'Please login as passenger.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];
$preVendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;
$vendors = $conn->query("SELECT id, vendor_name, station FROM vendors WHERE status='active' ORDER BY vendor_name");

$error = '';
$pnrData = null;

// PNR Verification Step
if (isset($_POST['verify_pnr'])) {
    $pnr = sanitize($_POST['pnr_number']);
    $pnrResult = verifyPNR($pnr);
    
    if ($pnrResult['success']) {
        $pnrData = $pnrResult['data'];
        storePNRVerification($pnr, $pnrData, $uid);
        $_SESSION['verified_pnr'] = $pnr;
        $_SESSION['pnr_data'] = $pnrData;
    } else {
        $error = $pnrResult['message'];
    }
}

// Rating Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $vendor_id   = (int)$_POST['vendor_id'];
    $pnr = sanitize($_POST['pnr_number']);
    $train       = sanitize($_POST['train_number']);
    $travel_date = sanitize($_POST['travel_date']);
    $c  = (int)$_POST['cleanliness'];
    $f  = (int)$_POST['food_quality'];
    $p  = (int)$_POST['packaging'];
    $s  = (int)$_POST['staff_hygiene'];
    $t  = (int)$_POST['timeliness'];
    $comments = sanitize($_POST['comments']);

    if (!$vendor_id || !$travel_date || !$c || !$f || !$p || !$s || !$t) {
        $error = 'Please fill all required fields and provide ratings for all parameters.';
    } elseif ($c < 1 || $f < 1 || $p < 1 || $s < 1 || $t < 1) {
        $error = 'All ratings must be between 1 and 5.';
    } elseif (!validatePNRFormat($pnr)) {
        $error = 'Invalid PNR format. Must be 10 digits.';
    } elseif (isPNRUsedForRating($pnr, $vendor_id)) {
        $error = 'You have already rated this vendor with this PNR.';
    } else {
        $score = calcHygieneScore($c, $f, $p, $s, $t);
        $conn->query("INSERT INTO ratings (passenger_id, vendor_id, pnr_number, train_number, travel_date, cleanliness, food_quality, packaging, staff_hygiene, timeliness, final_score, comments)
            VALUES ($uid, $vendor_id, '$pnr', '$train', '$travel_date', $c, $f, $p, $s, $t, $score, '$comments')");
        recalcVendorScore($vendor_id);
        
        unset($_SESSION['verified_pnr']);
        unset($_SESSION['pnr_data']);
        $_SESSION['msg_success'] = "Rating submitted! Score: $score/5.00 using weighted formula.";
        redirect('dashboard.php');
    }
}

// Restore PNR data from session
if (isset($_SESSION['verified_pnr']) && isset($_SESSION['pnr_data'])) {
    $pnrData = $_SESSION['pnr_data'];
}
include '../includes/header.php';
?>

<div class="page-hero py-4">
<div class="container">
    <a href="dashboard.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h1 style="font-size:2rem;"><i class="fas fa-star me-2"></i>Rate a Vendor</h1>
    <p class="mb-0" style="opacity:0.8;">Your structured feedback helps maintain hygiene standards</p>
</div>
</div>

<div class="container py-4">
<div class="row justify-content-center">
<div class="col-lg-8">

<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<!-- PNR VERIFICATION CARD -->
<?php if (!$pnrData): ?>
<div class="card mb-3">
<div class="card-header-blue"><i class="fas fa-ticket-alt me-2"></i>Step 1: Verify Your PNR</div>
<div class="p-4">
    <div class="alert alert-info mb-3" style="font-size:0.88rem;">
        <i class="fas fa-info-circle me-2"></i>Enter your 10-digit PNR number to verify your journey and proceed with rating.
    </div>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-8">
                <input type="text" name="pnr_number" class="form-control form-control-lg" 
                    placeholder="Enter 10-digit PNR (e.g., 1234567890)" 
                    pattern="\d{10}" maxlength="10" required>
            </div>
            <div class="col-md-4">
                <button type="submit" name="verify_pnr" class="btn btn-primary w-100 py-2">
                    <i class="fas fa-check-circle me-2"></i>Verify PNR
                </button>
            </div>
        </div>
    </form>
    <div class="mt-2 text-muted" style="font-size:0.78rem;">
        💡 Demo PNRs: 1234567890, 9876543210 (or any 10-digit number)
    </div>
</div>
</div>
<?php else: ?>
<!-- PNR VERIFIED - SHOW RATING FORM -->
<div class="alert alert-success mb-3">
    <i class="fas fa-check-circle me-2"></i><strong>PNR Verified!</strong> 
    Train: <?= htmlspecialchars($pnrData['train_name']) ?> (<?= $pnrData['train_number'] ?>) | 
    Journey: <?= htmlspecialchars($pnrData['from_station']) ?> → <?= htmlspecialchars($pnrData['to_station']) ?> | 
    Date: <?= $pnrData['travel_date'] ?>
</div>

<!-- FORMULA INFO -->
<div class="alert alert-info mb-4" style="font-size:0.84rem;">
    <i class="fas fa-calculator me-2"></i>
    <strong>Scoring Formula:</strong> Score = 0.30×Cleanliness + 0.25×Food Quality + 0.15×Packaging + 0.20×Staff Hygiene + 0.10×Timeliness
</div>

<div class="card">
<div class="card-header-blue"><i class="fas fa-star me-2"></i>Step 2: Submit Hygiene Rating</div>
<div class="p-4">
<form method="POST" id="ratingForm">
    <input type="hidden" name="pnr_number" value="<?= htmlspecialchars($_SESSION['verified_pnr']) ?>">

    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <label class="form-label">Select Vendor *</label>
            <select name="vendor_id" class="form-select" required>
                <option value="">-- Choose Vendor --</option>
                <?php $vendors->data_seek(0); while ($v = $vendors->fetch_assoc()): ?>
                <option value="<?= $v['id'] ?>" <?= ($preVendorId == $v['id'] || (isset($_POST['vendor_id']) && $_POST['vendor_id'] == $v['id'])) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($v['vendor_name']) ?> (<?= htmlspecialchars($v['station']) ?>)
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Train Number</label>
            <input type="text" name="train_number" class="form-control" placeholder="e.g. 12301" value="<?= htmlspecialchars($pnrData['train_number']) ?>" readonly>
        </div>
        <div class="col-md-3">
            <label class="form-label">Travel Date *</label>
            <input type="date" name="travel_date" class="form-control" value="<?= htmlspecialchars($pnrData['travel_date']) ?>" readonly required>
        </div>
    </div>

    <!-- RATING PARAMETERS -->
    <h6 class="fw-700 mb-3" style="color:#003580;border-bottom:2px solid #003580;padding-bottom:8px;">Rate Each Parameter (1=Very Poor, 5=Excellent)</h6>

    <?php
    $ratingParams = [
        ['cleanliness',  'Cleanliness',   'fas fa-broom',         '#003580', '(30% weight) Kitchen, serving area, utensils cleanliness'],
        ['food_quality', 'Food Quality',  'fas fa-utensils',      '#1a8a45', '(25% weight) Freshness, taste, temperature, preparation'],
        ['staff_hygiene','Staff Hygiene', 'fas fa-user-shield',   '#8b2fc9', '(20% weight) Gloves, uniform, personal hygiene of staff'],
        ['packaging',    'Packaging',     'fas fa-box',           '#c96010', '(15% weight) Safe, clean, sealed packaging of food'],
        ['timeliness',   'Timeliness',    'fas fa-clock',         '#c0392b', '(10% weight) Speed and punctuality of food delivery'],
    ];
    foreach ($ratingParams as $idx => $rp):
        $prevVal = isset($_POST[$rp[0]]) ? (int)$_POST[$rp[0]] : 0;
    ?>
    <div class="mb-4 p-3 rounded" style="background:#f8faff;border:1.5px solid #e0eaff;">
        <div class="d-flex align-items-center gap-3 mb-2">
            <div style="width:42px;height:42px;background:<?= $rp[3] ?>;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="<?= $rp[2] ?> text-white"></i>
            </div>
            <div>
                <div class="fw-600"><?= $rp[1] ?></div>
                <div class="text-muted" style="font-size:0.78rem;"><?= $rp[4] ?></div>
            </div>
            <div class="ms-auto" id="scoreLabel_<?= $idx ?>" style="font-family:'Rajdhani',sans-serif;font-size:1.4rem;font-weight:700;color:<?= $rp[3] ?>;">
                <?= $prevVal > 0 ? $prevVal . '/5' : '?/5' ?>
            </div>
        </div>
        <div class="d-flex gap-3 align-items-center">
            <?php for ($star = 1; $star <= 5; $star++): ?>
            <span class="rating-input-star <?= $prevVal >= $star ? 'selected' : '' ?>"
                data-param="<?= $rp[0] ?>" data-val="<?= $star ?>" data-idx="<?= $idx ?>"
                onclick="setRating('<?= $rp[0] ?>', <?= $star ?>, <?= $idx ?>)">★</span>
            <?php endfor; ?>
            <input type="hidden" name="<?= $rp[0] ?>" id="inp_<?= $rp[0] ?>" value="<?= $prevVal ?>" required>
            <span class="ms-2 text-muted" id="label_<?= $idx ?>" style="font-size:0.82rem;">
                <?php $labels = ['','Very Poor','Poor','Average','Good','Excellent']; echo $prevVal > 0 ? $labels[$prevVal] : 'Click to rate'; ?>
            </span>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- LIVE SCORE PREVIEW -->
    <div class="p-3 rounded mb-3 text-center" style="background:linear-gradient(90deg,#003580,#0066cc);color:#fff;" id="scorePreview">
        <div style="font-size:0.85rem;opacity:0.8;">Calculated Weighted Score</div>
        <div style="font-size:2.5rem;font-weight:700;font-family:'Rajdhani',sans-serif;" id="liveScore">—</div>
        <div style="font-size:0.78rem;opacity:0.7;">Rate all parameters to see your score</div>
    </div>

    <div class="mb-4">
        <label class="form-label">Additional Comments (Optional)</label>
        <textarea name="comments" class="form-control" rows="3" placeholder="Describe your experience in detail..."><?= isset($_POST['comments']) ? htmlspecialchars($_POST['comments']) : '' ?></textarea>
    </div>

    <button type="submit" name="submit_rating" class="btn btn-primary w-100 py-3 fw-600 fs-5">
        <i class="fas fa-paper-plane me-2"></i>Submit Hygiene Rating
    </button>
</form>
</div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<script>
const weights = { cleanliness:0.30, food_quality:0.25, packaging:0.15, staff_hygiene:0.20, timeliness:0.10 };
const labels  = ['','Very Poor','Poor','Average','Good','Excellent'];
const values  = { cleanliness:0, food_quality:0, packaging:0, staff_hygiene:0, timeliness:0 };

function setRating(param, val, idx) {
    values[param] = val;
    document.getElementById('inp_' + param).value = val;
    document.getElementById('scoreLabel_' + idx).textContent = val + '/5';
    document.getElementById('label_' + idx).textContent = labels[val];

    // Highlight stars
    document.querySelectorAll(`[data-param="${param}"]`).forEach(s => {
        s.classList.toggle('selected', parseInt(s.dataset.val) <= val);
    });

    updateLiveScore();
}

function updateLiveScore() {
    let score = 0;
    let allSet = true;
    for (const [k, w] of Object.entries(weights)) {
        if (!values[k]) { allSet = false; break; }
        score += w * values[k];
    }
    const el = document.getElementById('liveScore');
    if (allSet) {
        el.textContent = score.toFixed(2) + '/5.00';
        el.style.color = score >= 4.5 ? '#7aff9a' : score >= 3.5 ? '#a0d8ff' : score >= 2.5 ? '#ffd700' : '#ff8c8c';
    } else {
        el.textContent = '—';
    }
}

document.getElementById('ratingForm').addEventListener('submit', function(e) {
    for (const [k] of Object.entries(weights)) {
        if (!values[k]) {
            e.preventDefault();
            showToast('Please rate all 5 parameters before submitting.', 'danger');
            return;
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
