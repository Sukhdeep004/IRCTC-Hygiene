<?php
require_once '../includes/config.php';
require_once '../includes/pnr_module.php';
require_once '../includes/ai_module.php';
define('BASEPATH', '../');
$pageTitle = 'File Complaint';
if (!isLoggedIn() || !isPassenger()) { $_SESSION['msg_error'] = 'Please login.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];
$preVendorId = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id'] : 0;
$vendors = $conn->query("SELECT id, vendor_name, station FROM vendors WHERE status != 'suspended' ORDER BY vendor_name");
$error = '';
$pnrData = null;

// Withdraw complaint (within 8 hours)
if (isset($_GET['withdraw']) && is_numeric($_GET['withdraw'])) {
    $cid = (int)$_GET['withdraw'];
    $row = $conn->query("SELECT id, created_at, status FROM complaints WHERE id=$cid AND passenger_id=$uid")->fetch_assoc();
    if ($row && in_array($row['status'], ['submitted','under_verification']) && (time() - strtotime($row['created_at'])) < 28800) {
        $now = date('Y-m-d H:i:s');
        $conn->query("UPDATE complaints SET status='withdrawn', withdrawn_at='$now' WHERE id=$cid");
        logComplaintHistory($cid, $uid, 'passenger', 'withdrawn', 'Complaint withdrawn by passenger within 8 hours.');
        $_SESSION['msg_success'] = 'Complaint withdrawn successfully.';
    } else {
        $_SESSION['msg_error'] = 'Cannot withdraw — either 8 hours have passed or complaint is already being processed.';
    }
    redirect('complaint.php');
}

// Submit additional info for more_info_requested complaints
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_more_info'])) {
    $cid = (int)$_POST['complaint_id'];
    $addInfo = sanitize($_POST['additional_info'] ?? '');
    $row = $conn->query("SELECT id, status FROM complaints WHERE id=$cid AND passenger_id=$uid")->fetch_assoc();
    if (!$row || $row['status'] !== 'more_info_requested') {
        $_SESSION['msg_error'] = 'Invalid request.';
        redirect('complaint.php');
    }
    if (strlen(trim($addInfo)) < 5) {
        $_SESSION['msg_error'] = 'Please provide more details (at least 5 characters).';
        redirect('complaint.php');
    }
    // Handle optional new image
    $newImageSql = '';
    if (!empty($_FILES['more_info_image']['name'])) {
        $file = $_FILES['more_info_image'];
        $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 5*1024*1024 && $file['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/complaints/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $filename = 'complaint_'.$uid.'_'.time().'.'.$ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) {
                $imgPath = $conn->real_escape_string('uploads/complaints/'.$filename);
                $newImageSql = ", image_path='$imgPath'";
            }
        }
    }
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE complaints SET status='under_verification', passenger_additional_info='$addInfo', officer_updated_at='$now'$newImageSql WHERE id=$cid");
    logComplaintHistory($cid, $uid, 'passenger', 'additional_info_provided', $addInfo);
    $_SESSION['msg_success'] = 'Additional information submitted. Your complaint is back under verification.';
    redirect('complaint.php');
}// PNR Verification
if (isset($_POST['verify_pnr'])) {
    $pnr = sanitize($_POST['pnr_number']);
    if (!validatePNRFormat($pnr)) {
        $error = 'Invalid PNR format. Must be exactly 10 digits.';
    } else {
        $pnrResult = verifyPNR($pnr);
        if ($pnrResult['success']) {
            $pnrData = $pnrResult['data'];
            storePNRVerification($pnr, $pnrData, $uid);
            $_SESSION['verified_pnr']      = $pnr;
            $_SESSION['pnr_data']          = $pnrData;
            $_SESSION['pnr_verified_uid']  = $uid;
        } else {
            $error = $pnrResult['message'];
            unset($_SESSION['verified_pnr'], $_SESSION['pnr_data'], $_SESSION['pnr_verified_uid']);
        }
    }
}

// Complaint Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $vendor_id   = (int)$_POST['vendor_id'];
    $subject     = sanitize($_POST['subject']);
    $category    = sanitize($_POST['category']);
    $description = sanitize($_POST['description']);
    $pnr         = sanitize($_POST['pnr_number']);
    $contact     = sanitize($_POST['passenger_contact'] ?? '');

    // Must have a verified PNR in session belonging to this user
    $sessionPnrOk = isset($_SESSION['verified_pnr'], $_SESSION['pnr_data'], $_SESSION['pnr_verified_uid'])
                    && $_SESSION['verified_pnr'] === $pnr
                    && $_SESSION['pnr_verified_uid'] === $uid;

    if (!$vendor_id || !$subject || !$description || !$category) {
        $error = 'Please fill all required fields.';
    } elseif (!$contact) {
        $error = 'Please provide your contact information (phone or email).';
    } elseif (strlen($_POST['description']) < 20) {
        $error = 'Description must be at least 20 characters.';
    } elseif (!validatePNRFormat($pnr)) {
        $error = 'Invalid PNR format. Must be 10 digits.';
    } elseif (!$sessionPnrOk) {
        $error = 'PNR not verified. Please verify your PNR before submitting.';
        unset($_SESSION['verified_pnr'], $_SESSION['pnr_data'], $_SESSION['pnr_verified_uid']);
    } elseif (isPNRUsedForComplaint($pnr, $vendor_id)) {
        $error = 'You have already filed a complaint for this vendor with this PNR.';
    } else {
        $imagePath = null;
        if (!empty($_FILES['complaint_image']['name'])) {
            $file = $_FILES['complaint_image'];
            $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
            if (!in_array($file['type'], $allowed)) { $error = 'Only JPG, PNG, GIF, or WEBP images allowed.'; }
            elseif ($file['size'] > 5*1024*1024) { $error = 'Image must be under 5MB.'; }
            elseif ($file['error'] !== UPLOAD_ERR_OK) { $error = 'Upload failed.'; }
            else {
                $uploadDir = '../uploads/complaints/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'complaint_'.$uid.'_'.time().'.'.$ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir.$filename)) $imagePath = 'uploads/complaints/'.$filename;
                else $error = 'Could not save image. Check folder permissions.';
            }
        }
        if (!$error) {
            $sentiment = analyzeSentiment($description);
            $aiCat     = categorizeComplaint($subject, $description);
            $code = genComplaintCode();
            while ($conn->query("SELECT id FROM complaints WHERE complaint_code='$code'")->num_rows > 0) $code = genComplaintCode();
            $sentimentType  = sanitize($sentiment['sentiment']);
            $sentimentScore = $sentiment['confidence'];
            $aiCategory     = sanitize($aiCat['primary_category']);
            $imgSql         = $imagePath ? "'".$conn->real_escape_string($imagePath)."'" : "NULL";
            // Journey details — use passenger-edited values, fall back to PNR session
            $trainNo  = sanitize($_POST['journey_train'] ?? ($_SESSION['pnr_data']['train_number'] ?? ''));
            $fromSt   = sanitize($_POST['journey_from']  ?? ($_SESSION['pnr_data']['from_station'] ?? ''));
            $toSt     = sanitize($_POST['journey_to']    ?? ($_SESSION['pnr_data']['to_station'] ?? ''));
            $conn->query("INSERT INTO complaints (passenger_id,vendor_id,complaint_code,subject,category,description,passenger_contact,pnr_number,train_number,from_station,to_station,sentiment,sentiment_score,ai_category,image_path,status)
                VALUES ($uid,$vendor_id,'$code','$subject','$category','$description','$contact','$pnr','$trainNo','$fromSt','$toSt','$sentimentType',$sentimentScore,'$aiCategory',$imgSql,'submitted')");
            $newId = $conn->insert_id;
            logComplaintHistory($newId, $uid, 'passenger', 'submitted', 'Complaint submitted by passenger.');
            unset($_SESSION['verified_pnr'], $_SESSION['pnr_data'], $_SESSION['pnr_verified_uid']);
            $_SESSION['msg_success'] = "Complaint filed! Code: <strong>$code</strong>. Status: <strong>Submitted</strong> — awaiting officer verification.";
            redirect('dashboard.php');
        }
    }
}
if (isset($_SESSION['verified_pnr'], $_SESSION['pnr_data'], $_SESSION['pnr_verified_uid'])
    && $_SESSION['pnr_verified_uid'] === $uid) {
    $pnrData = $_SESSION['pnr_data'];
}

// Passenger's complaints with history
$myComplaints = $conn->query("
    SELECT c.*, v.vendor_name FROM complaints c
    JOIN vendors v ON c.vendor_id=v.id
    WHERE c.passenger_id=$uid ORDER BY c.created_at DESC
");
include '../includes/header.php';
?>
<div class="page-hero py-4">
<div class="container">
    <a href="dashboard.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h1 style="font-size:2rem;"><i class="fas fa-file-alt me-2"></i>File a Complaint</h1>
    <p class="mb-0" style="opacity:0.8;">Report hygiene violations or service issues</p>
</div>
</div>
<div class="container py-4">
<div class="row g-4">
<div class="col-lg-6">
<?php if ($error): ?><div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

<?php if (!$pnrData): ?>
<div class="card mb-3">
<div class="card-header-blue"><i class="fas fa-ticket-alt me-2"></i>Step 1: Verify Your PNR</div>
<div class="p-4">
    <div class="alert alert-info mb-3" style="font-size:0.88rem;"><i class="fas fa-info-circle me-2"></i>Enter your 10-digit PNR to verify your journey.</div>
    <form method="POST">
        <div class="row g-3">
            <div class="col-md-8"><input type="text" name="pnr_number" class="form-control form-control-lg" placeholder="10-digit PNR" pattern="\d{10}" maxlength="10" required></div>
            <div class="col-md-4"><button type="submit" name="verify_pnr" class="btn btn-primary w-100 py-2"><i class="fas fa-check-circle me-2"></i>Verify PNR</button></div>
        </div>
    </form>
    <div class="mt-2 text-muted" style="font-size:0.78rem;">💡 Demo PNRs: 1234567890, 9876543210</div>
</div>
</div>
<?php else: ?>
<div class="alert alert-success mb-3">
    <i class="fas fa-check-circle me-2"></i><strong>PNR Verified!</strong>
    Train: <?= htmlspecialchars($pnrData['train_name']) ?> (<?= $pnrData['train_number'] ?>) |
    Journey: <?= htmlspecialchars($pnrData['from_station']) ?> → <?= htmlspecialchars($pnrData['to_station']) ?> | Date: <?= $pnrData['travel_date'] ?>
</div>
<div class="card">
<div class="card-header-orange"><i class="fas fa-exclamation-triangle me-2"></i>Step 2: File Your Complaint</div>
<div class="p-4">
<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="pnr_number" value="<?= htmlspecialchars($_SESSION['verified_pnr']) ?>">
    <!-- Journey details — pre-filled from PNR, editable -->
    <div class="p-3 rounded mb-3" style="background:#f0f5ff;border:1px solid #d0daf0;">
        <div class="fw-600 mb-2" style="font-size:0.85rem;color:#003580;"><i class="fas fa-train me-1"></i>Journey Details</div>
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label" style="font-size:0.8rem;">Train Number</label>
                <input type="text" name="journey_train" class="form-control form-control-sm" value="<?= htmlspecialchars($pnrData['train_number'] ?? '') ?>" placeholder="e.g. 12301">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:0.8rem;">Source Station</label>
                <input type="text" name="journey_from" class="form-control form-control-sm" value="<?= htmlspecialchars($pnrData['from_station'] ?? '') ?>" placeholder="From">
            </div>
            <div class="col-md-4">
                <label class="form-label" style="font-size:0.8rem;">Destination</label>
                <input type="text" name="journey_to" class="form-control form-control-sm" value="<?= htmlspecialchars($pnrData['to_station'] ?? '') ?>" placeholder="To">
            </div>
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label">Select Vendor *</label>
        <select name="vendor_id" class="form-select" required>
            <option value="">-- Choose Vendor --</option>
            <?php $vendors->data_seek(0); while ($v = $vendors->fetch_assoc()): ?>
            <option value="<?= $v['id'] ?>" <?= $preVendorId==$v['id']?'selected':'' ?>><?= htmlspecialchars($v['vendor_name']) ?> (<?= htmlspecialchars($v['station']) ?>)</option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Category *</label>
        <select name="category" class="form-select" required>
            <option value="">-- Select Category --</option>
            <option value="food_quality">Food Quality</option>
            <option value="hygiene">Hygiene / Cleanliness</option>
            <option value="staff_behavior">Staff Behavior</option>
            <option value="packaging">Packaging</option>
            <option value="delivery">Delivery / Timeliness</option>
            <option value="pricing">Overcharging / Pricing</option>
            <option value="other">Other</option>
        </select>
    </div>
    <div class="mb-3">
        <label class="form-label">Subject *</label>
        <input type="text" name="subject" class="form-control" placeholder="Brief title of the issue" value="<?= isset($_POST['subject'])?htmlspecialchars($_POST['subject']):'' ?>" required>
    </div>
    <div class="mb-3">
        <label class="form-label">Detailed Description * <small class="text-muted">(min 20 chars)</small></label>
        <textarea name="description" id="complaintDesc" class="form-control" rows="5" placeholder="Describe the issue in detail..." required><?= isset($_POST['description'])?htmlspecialchars($_POST['description']):'' ?></textarea>
        <div id="aiPreview" class="mt-2 p-2 rounded" style="background:#f8f9fa;font-size:0.82rem;display:none;">
            <i class="fas fa-robot me-1"></i><strong>AI Analysis:</strong> <span id="aiSentiment"></span> | <span id="aiCategory"></span>
        </div>
    </div>
    <div class="mb-4">
        <label class="form-label">Attach Image <small class="text-muted">(optional, max 5MB)</small></label>
        <input type="file" name="complaint_image" id="complaintImage" class="form-control" accept="image/jpeg,image/png,image/gif,image/webp">
        <div id="imagePreview" class="mt-2" style="display:none;"><img id="previewImg" src="" alt="Preview" style="max-height:160px;border-radius:8px;border:1px solid #dee2e6;"></div>
    </div>
    <div class="mb-3">
        <label class="form-label">Contact Phone Number * <small class="text-muted">(for follow-up by admin/officer)</small></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-phone"></i></span>
            <input type="tel" name="passenger_contact" class="form-control"
                   placeholder="e.g. 9876543210"
                   pattern="[0-9]{10,15}"
                   title="Enter a valid phone number (10-15 digits)"
                   value="<?= isset($_POST['passenger_contact']) ? htmlspecialchars($_POST['passenger_contact']) : '' ?>"
                   required>
        </div>
        <div class="form-text text-muted" style="font-size:0.78rem;"><i class="fas fa-info-circle me-1"></i>This number will only be used by IRCTC officials to confirm complaint resolution.</div>
    </div>
    <button type="submit" name="submit_complaint" class="btn btn-primary w-100 py-2 fw-600">
        <i class="fas fa-paper-plane me-2"></i>Submit Complaint
    </button>
</form>
</div>
</div>
<?php endif; ?>
</div>

<!-- My Complaints with full timeline -->
<div class="col-lg-6">
<div class="card">
<div class="card-header-blue"><i class="fas fa-list me-2"></i>My Complaints</div>
<div class="p-3">
<?php if ($myComplaints->num_rows === 0): ?>
<p class="text-muted text-center py-3">No complaints filed yet.</p>
<?php else: while ($c = $myComplaints->fetch_assoc()):
    $sl = complaintStatusLabel($c['status']);
?>
<div class="border-bottom pb-3 mb-3">
    <div class="d-flex justify-content-between align-items-start mb-1">
        <strong style="font-size:0.88rem;"><?= htmlspecialchars($c['subject']) ?></strong>
        <span class="badge <?= $sl['class'] ?> ms-2"><?= $sl['label'] ?></span>
    </div>
    <div class="text-muted" style="font-size:0.78rem;">
        🏪 <?= htmlspecialchars($c['vendor_name']) ?> | 🔖 <code><?= $c['complaint_code'] ?></code>
        <?php if ($c['category']): ?> | 🏷️ <?= ucfirst(str_replace('_',' ',$c['category'])) ?><?php endif; ?>
        | <?= timeAgo($c['created_at']) ?>
    </div>
    <?php if ($c['sentiment']): ?>
    <div class="mt-1" style="font-size:0.75rem;">
        <i class="fas fa-robot me-1"></i>
        <span class="badge bg-<?= $c['sentiment']==='negative'?'danger':($c['sentiment']==='positive'?'success':'secondary') ?>"><?= ucfirst($c['sentiment']) ?> Sentiment</span>
    </div>
    <?php endif; ?>
    <!-- Timeline -->
    <?php if (!empty($c['more_info_request'])): ?>
    <div class="mt-2 p-2 rounded" style="background:#fff3cd;font-size:0.78rem;">
        <i class="fas fa-question-circle me-1 text-warning"></i><strong>Officer needs more info:</strong> <?= htmlspecialchars($c['more_info_request']) ?>
    </div>
    <?php if ($c['status'] === 'more_info_requested'): ?>
    <div class="mt-2 p-3 rounded" style="background:#f0f5ff;border:1px solid #cce0ff;">
        <div class="fw-600 mb-2" style="font-size:0.82rem;color:#003580;"><i class="fas fa-reply me-1"></i>Provide Additional Information</div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
            <div class="mb-2">
                <textarea name="additional_info" class="form-control form-control-sm" rows="3"
                    placeholder="Provide the requested details here..." required minlength="5"></textarea>
            </div>
            <div class="mb-2">
                <label class="form-label mb-1" style="font-size:0.78rem;">Attach new photo (optional)</label>
                <input type="file" name="more_info_image" class="form-control form-control-sm" accept="image/jpeg,image/png,image/gif,image/webp">
            </div>
            <button type="submit" name="submit_more_info" class="btn btn-sm btn-primary">
                <i class="fas fa-paper-plane me-1"></i>Submit Info
            </button>
        </form>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($c['officer_note'])): ?>
    <div class="mt-1 p-2 rounded" style="background:#e8f5e9;font-size:0.78rem;">
        <i class="fas fa-user-shield me-1 text-success"></i>
        <strong>Officer:</strong> <?= htmlspecialchars($c['officer_note']) ?>
        <?php if ($c['officer_action']): ?><span class="badge bg-success ms-1" style="font-size:0.65rem;"><?= ucfirst(str_replace('_',' ',$c['officer_action'])) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['admin_ack_note']) || !empty($c['department'])): ?>
    <div class="mt-1 p-2 rounded" style="background:#cff4fc;font-size:0.78rem;">
        <i class="fas fa-check-double me-1 text-info"></i><strong>Admin:</strong>
        <?php if ($c['department']): ?> Assigned to <em><?= htmlspecialchars($c['department']) ?></em>.<?php endif; ?>
        <?php if ($c['admin_ack_note']): ?> <?= htmlspecialchars($c['admin_ack_note']) ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['vendor_comment'])): ?>
    <div class="mt-1 p-2 rounded" style="background:#fff8e1;font-size:0.78rem;border-left:3px solid #f5a623;">
        <i class="fas fa-store me-1" style="color:#e87722;"></i><strong>Vendor Response:</strong> <?= htmlspecialchars($c['vendor_comment']) ?>
        <span class="text-muted ms-1">(<?= timeAgo($c['vendor_commented_at']) ?>)</span>
    </div>
    <?php endif; ?>
    <?php
    date_default_timezone_set('Asia/Kolkata');
    $canWithdraw = in_array($c['status'], ['submitted','under_verification']) && (time() - strtotime($c['created_at'])) < 28800;
    if ($canWithdraw):
        $hoursLeft = round((28800 - (time() - strtotime($c['created_at']))) / 3600, 1);
    ?>
    <div class="mt-2">
        <a href="complaint.php?withdraw=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
           onclick="return confirm('Are you sure you want to withdraw this complaint?')">
            <i class="fas fa-undo me-1"></i>Withdraw <small>(<?= $hoursLeft ?>h left)</small>
        </a>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['image_path'])): ?>
    <div class="mt-2">
        <a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank">
            <img src="../<?= htmlspecialchars($c['image_path']) ?>" alt="Complaint image" style="max-height:80px;border-radius:6px;border:1px solid #dee2e6;">
        </a>
    </div>
    <?php endif; ?>
    <!-- View Details button -->
    <div class="mt-2">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#detailModal<?= $c['id'] ?>">
            <i class="fas fa-eye me-1"></i>View Full Details
        </button>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal<?= $c['id'] ?>" tabindex="-1">
<div class="modal-dialog modal-lg">
<div class="modal-content">
<div class="modal-header bg-primary text-white">
    <h5 class="modal-title fw-700"><i class="fas fa-file-alt me-2"></i><?= $c['complaint_code'] ?></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>
<div class="modal-body">
    <div class="p-3 rounded mb-3" style="background:#f0f5ff;font-size:0.88rem;">
        <div class="row g-2">
            <div class="col-md-6"><strong>Vendor:</strong> <?= htmlspecialchars($c['vendor_name']) ?></div>
            <div class="col-md-6"><strong>Status:</strong> <span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></div>
            <div class="col-md-6"><strong>Category:</strong> <?= $c['category'] ? ucfirst(str_replace('_',' ',$c['category'])) : '—' ?></div>
            <div class="col-md-6"><strong>Filed:</strong> <?= date('d M Y H:i', strtotime($c['created_at'])) ?></div>
            <?php if (!empty($c['pnr_number'])): ?><div class="col-md-6"><strong>PNR:</strong> <code><?= htmlspecialchars($c['pnr_number']) ?></code></div><?php endif; ?>
            <?php if (!empty($c['passenger_contact'])): ?><div class="col-md-6"><strong><i class="fas fa-phone me-1 text-success"></i>Contact:</strong> <a href="tel:<?= htmlspecialchars($c['passenger_contact']) ?>"><?= htmlspecialchars($c['passenger_contact']) ?></a></div><?php endif; ?>
            <?php if (!empty($c['train_number'])): ?><div class="col-md-6"><strong>Train:</strong> <?= htmlspecialchars($c['train_number']) ?></div><?php endif; ?>
            <?php if (!empty($c['from_station']) || !empty($c['to_station'])): ?>
            <div class="col-md-12"><strong>Journey:</strong> <?= htmlspecialchars($c['from_station'] ?? '?') ?> → <?= htmlspecialchars($c['to_station'] ?? '?') ?></div>
            <?php endif; ?>
            <?php if (!empty($c['priority'])): ?>
            <?php $prioCfg=['low'=>['bg-success','Low',''],'medium'=>['bg-warning text-dark','Medium',''],'high'=>['text-white','High','background:#e65c00'],'critical'=>['bg-danger','Critical','']]; $pr=$prioCfg[$c['priority']]??$prioCfg['medium']; ?>
            <div class="col-md-6"><strong>Priority:</strong> <span class="badge <?= $pr[0] ?>" <?= $pr[2]?'style="'.$pr[2].'"':'' ?>><?= $pr[1] ?></span></div>
            <?php endif; ?>
        </div>
    </div>
    <div class="mb-3"><strong>Subject:</strong> <?= htmlspecialchars($c['subject']) ?></div>
    <div class="mb-3"><strong>Description:</strong><p class="mt-1 mb-0"><?= nl2br(htmlspecialchars($c['description'])) ?></p></div>
    <?php if (!empty($c['image_path'])): ?>
    <div class="mb-3">
        <strong>Attached Photo:</strong>
        <div class="mt-2">
            <a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank">
                <img src="../<?= htmlspecialchars($c['image_path']) ?>" style="max-height:220px;border-radius:8px;border:1px solid #dee2e6;">
            </a>
        </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['more_info_request'])): ?>
    <div class="p-2 rounded mb-2" style="background:#fff3cd;font-size:0.85rem;"><i class="fas fa-question-circle me-1 text-warning"></i><strong>More Info Requested:</strong> <?= htmlspecialchars($c['more_info_request']) ?></div>
    <?php endif; ?>
    <?php if (!empty($c['admin_ack_note']) || !empty($c['department'])): ?>
    <div class="p-2 rounded mb-2" style="background:#cff4fc;font-size:0.85rem;border-left:3px solid #0dcaf0;">
        <i class="fas fa-check-double me-1 text-info"></i><strong>Admin Action:</strong>
        <?php if ($c['department']): ?> Assigned to <em><?= htmlspecialchars($c['department']) ?></em>.<?php endif; ?>
        <?php if ($c['admin_ack_note']): ?> <?= htmlspecialchars($c['admin_ack_note']) ?><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['vendor_comment'])): ?>
    <div class="p-2 rounded mb-2" style="background:#fff8e1;font-size:0.85rem;border-left:3px solid #f5a623;"><i class="fas fa-store me-1"></i><strong>Vendor Response:</strong> <?= htmlspecialchars($c['vendor_comment']) ?></div>
    <?php endif; ?>
</div>
</div>
</div>
</div>
<?php endwhile; endif; ?>
</div>
</div>
</div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
<script>
const imgInput = document.getElementById('complaintImage');
if (imgInput) {
    imgInput.addEventListener('change', function() {
        const preview = document.getElementById('imagePreview');
        const img = document.getElementById('previewImg');
        if (this.files[0]) { img.src = URL.createObjectURL(this.files[0]); preview.style.display='block'; }
        else preview.style.display='none';
    });
}
const descField = document.getElementById('complaintDesc');
if (descField) {
    let timeout;
    descField.addEventListener('input', function() {
        clearTimeout(timeout);
        const text = this.value.trim();
        if (text.length < 20) { document.getElementById('aiPreview').style.display='none'; return; }
        timeout = setTimeout(() => {
            fetch('../api/ai_sentiment.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({text,categorize:true}) })
            .then(r=>r.json()).then(data => {
                if (data.success) {
                    const s=data.data.sentiment, cat=data.data.category.primary_category;
                    const colors={positive:'#28a745',negative:'#dc3545',neutral:'#6c757d'};
                    document.getElementById('aiSentiment').innerHTML=`<span style="color:${colors[s]};font-weight:600;">${s.toUpperCase()}</span> (${data.data.confidence.toFixed(1)}%)`;
                    document.getElementById('aiCategory').innerHTML=`Category: <strong>${cat.replace('_',' ')}</strong>`;
                    document.getElementById('aiPreview').style.display='block';
                }
            });
        }, 1000);
    });
}
</script>
