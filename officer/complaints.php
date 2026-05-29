<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Complaint Verification';
if (!isLoggedIn() || !isOfficer()) { $_SESSION['msg_error'] = 'Officer access required.'; redirect('../login.php'); }

$uid = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $cid    = (int)$_POST['complaint_id'];
    $action = sanitize($_POST['officer_action']);
    $note   = sanitize($_POST['officer_note'] ?? '');
    $moreInfo = sanitize($_POST['more_info_request'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');
    if (!in_array($priority, ['low','medium','high','critical'])) $priority = 'medium';

    $allowed = ['approve','reject','request_more_info'];
    if (!in_array($action, $allowed)) { $_SESSION['msg_error'] = 'Invalid action.'; redirect('complaints.php'); }

    $now = date('Y-m-d H:i:s');
    if ($action === 'approve') {
        if (!$note) { $_SESSION['msg_error'] = 'Please add a verification note.'; redirect('complaints.php'); }
        $conn->query("UPDATE complaints SET status='approved', priority='$priority', officer_id=$uid, officer_note='$note',
            officer_action='approved', officer_updated_at='$now', passenger_notified=1 WHERE id=$cid");
        logComplaintHistory($cid, $uid, 'officer', 'approved', $note);
        $_SESSION['msg_success'] = 'Complaint approved. You can now forward it to Admin.';
    } elseif ($action === 'reject') {
        if (!$note) { $_SESSION['msg_error'] = 'Please add a rejection reason.'; redirect('complaints.php'); }
        $conn->query("UPDATE complaints SET status='rejected', priority='$priority', officer_id=$uid, officer_note='$note',
            officer_action='rejected', officer_updated_at='$now', passenger_notified=1 WHERE id=$cid");
        logComplaintHistory($cid, $uid, 'officer', 'rejected', $note);
        $_SESSION['msg_success'] = 'Complaint rejected.';
    } elseif ($action === 'request_more_info') {
        if (!$moreInfo) { $_SESSION['msg_error'] = 'Please specify what information is needed.'; redirect('complaints.php'); }
        $conn->query("UPDATE complaints SET status='more_info_requested', priority='$priority', officer_id=$uid,
            more_info_request='$moreInfo', officer_updated_at='$now', passenger_notified=1 WHERE id=$cid");
        logComplaintHistory($cid, $uid, 'officer', 'more_info_requested', $moreInfo);
        $_SESSION['msg_success'] = 'Passenger notified to provide more information.';
    }
    redirect('complaints.php');
}

// Forward approved complaint to admin
if (isset($_GET['forward']) && is_numeric($_GET['forward'])) {
    $cid = (int)$_GET['forward'];
    $now = date('Y-m-d H:i:s');
    $conn->query("UPDATE complaints SET status='forwarded_to_admin', officer_updated_at='$now' WHERE id=$cid AND status='approved'");
    logComplaintHistory($cid, $uid, 'officer', 'forwarded_to_admin', 'Forwarded to Admin for final action.');
    $_SESSION['msg_success'] = 'Complaint forwarded to Admin.';
    redirect('complaints.php');
}

$filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$where  = $filter ? "WHERE c.status='$filter'" : '';

// Mark submitted ones as under_verification when officer views them
$conn->query("UPDATE complaints SET status='under_verification' WHERE status='submitted'");

// ── 8hr auto-escalation: forward to admin if officer hasn't acted ────────────
$conn->query("
    UPDATE complaints
    SET status='forwarded_to_admin', officer_updated_at=NOW()
    WHERE status='under_verification'
      AND TIMESTAMPDIFF(HOUR, created_at, NOW()) >= 8
      AND officer_id IS NULL
");
// Log auto-escalations
$autoEsc = $conn->query("SELECT id FROM complaints WHERE status='forwarded_to_admin' AND officer_id IS NULL AND officer_updated_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
if ($autoEsc) {
    while ($ae = $autoEsc->fetch_assoc()) {
        logComplaintHistory($ae['id'], 0, 'system', 'auto_escalated', 'Auto-forwarded to Admin: officer did not act within 8 hours.');
    }
}

$complaints = $conn->query("
    SELECT c.*, v.vendor_name, u.name AS passenger_name
    FROM complaints c
    JOIN vendors v ON c.vendor_id=v.id
    JOIN users u ON c.passenger_id=u.id
    $where ORDER BY c.created_at DESC
");

// Stats
$stats = [];
foreach (['submitted','under_verification','approved','rejected','more_info_requested','forwarded_to_admin'] as $s) {
    $stats[$s] = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='$s'")->fetch_assoc()['c'];
}

include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">🛡️ Officer Panel</div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="complaints.php" class="active"><i class="fas fa-file-alt fa-fw me-2"></i>Complaint Verification</a>
    <a href="messages.php"><i class="fas fa-comments fa-fw me-2"></i>Messages (Admin)
        <?php
        $adminResOC = $conn->query("SELECT id FROM users WHERE role='admin' LIMIT 1");
        $admin_id_oc = (int)$adminResOC->fetch_assoc()['id'];
        $ucAdmin = (int)$conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND sender_id=$admin_id_oc AND is_read=0")->fetch_assoc()['c'];
        if ($ucAdmin > 0): ?>
        <span class="badge bg-danger ms-auto"><?= $ucAdmin ?></span>
        <?php endif; ?>
    </a>
    <a href="vendor_chat.php"><i class="fas fa-store fa-fw me-2"></i>Message Vendors
        <?php $ucVendors = (int)$conn->query("SELECT COUNT(*) as c FROM messages m JOIN users u ON m.sender_id=u.id WHERE m.receiver_id=$uid AND u.role='vendor' AND m.is_read=0")->fetch_assoc()['c'];
        if ($ucVendors > 0): ?><span class="badge bg-danger ms-auto"><?= $ucVendors ?></span><?php endif; ?>
    </a>
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-shield-alt me-2"></i>Complaint Verification</h3>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-secondary"><?= $stats['submitted'] + $stats['under_verification'] ?></div><div class="stat-label">Pending</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-info"><?= $stats['approved'] ?></div><div class="stat-label">Approved</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-danger"><?= $stats['rejected'] ?></div><div class="stat-label">Rejected</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-warning"><?= $stats['more_info_requested'] ?></div><div class="stat-label">More Info</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-primary"><?= $stats['forwarded_to_admin'] ?></div><div class="stat-label">Forwarded</div></div></div>
</div>

<!-- Filter tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $tabs = [''=> 'All Complaints', 'submitted'=>'Submitted', 'under_verification'=>'Under Verification',
             'approved'=>'Approved', 'rejected'=>'Rejected', 'more_info_requested'=>'More Info', 'forwarded_to_admin'=>'Forwarded'];
    foreach ($tabs as $k => $label):
    ?>
    <a href="complaints.php<?= $k?'?status='.$k:'' ?>" class="btn btn-sm <?= $filter===$k?'btn-primary':'btn-outline-secondary' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Code</th><th>Passenger</th><th>Vendor</th><th>Subject / Category</th><th>Priority</th><th>Filed</th><th>Status</th><th>Actions</th></tr></thead>
<tbody>
<?php if ($complaints->num_rows === 0): ?>
<tr><td colspan="7" class="text-center py-4 text-muted">No complaints found.</td></tr>
<?php else: while ($c = $complaints->fetch_assoc()):
    $sl = complaintStatusLabel($c['status']);
    $prioCfg = ['low'=>['bg-success','Low'],'medium'=>['bg-warning text-dark','Medium'],'high'=>['text-white','High','background:#e65c00'],'critical'=>['bg-danger','Critical']];
    $prio = $prioCfg[$c['priority'] ?? 'medium'] ?? $prioCfg['medium'];
    $hoursOld = round((time() - strtotime($c['created_at'])) / 3600, 1);
    $escalationWarning = in_array($c['status'],['under_verification','submitted']) && $hoursOld >= 6 && $hoursOld < 8;
?>
<tr>
    <td><code style="font-size:0.75rem;"><?= $c['complaint_code'] ?></code></td>
    <td style="font-size:0.85rem;"><?= htmlspecialchars($c['passenger_name']) ?></td>
    <td style="font-size:0.85rem;"><?= htmlspecialchars($c['vendor_name']) ?></td>
    <td>
        <strong style="font-size:0.85rem;"><?= htmlspecialchars(substr($c['subject'],0,35)) ?></strong>
        <?php if ($c['category']): ?><div><span class="badge bg-light text-dark border" style="font-size:0.7rem;"><?= ucfirst(str_replace('_',' ',$c['category'])) ?></span></div><?php endif; ?>
        <?php if ($c['sentiment']): ?>
        <span class="badge bg-<?= $c['sentiment']==='negative'?'danger':($c['sentiment']==='positive'?'success':'secondary') ?>" style="font-size:0.68rem;"><i class="fas fa-robot"></i> <?= ucfirst($c['sentiment']) ?></span>
        <?php endif; ?>
        <?php if (!empty($c['image_path'])): ?>
        <div class="mt-1"><a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank"><img src="../<?= htmlspecialchars($c['image_path']) ?>" style="max-height:36px;border-radius:4px;border:1px solid #dee2e6;"></a></div>
        <?php endif; ?>
    </td>
    <td>
        <?php
        $badgeStyle = isset($prio[2]) ? 'style="'.$prio[2].'"' : '';
        ?>
        <span class="badge <?= $prio[0] ?>" <?= $badgeStyle ?>><?= $prio[1] ?></span>
        <?php if ($escalationWarning): ?>
        <div class="text-danger" style="font-size:0.68rem;"><i class="fas fa-clock"></i> <?= $hoursOld ?>h — escalates soon!</div>
        <?php endif; ?>
    </td>
    <td><small><?= date('d M Y', strtotime($c['created_at'])) ?></small></td>
    <td><span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
    <td>
        <div class="d-flex gap-1 flex-wrap">
            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal<?= $c['id'] ?>">View</button>
            <?php if ($c['status'] === 'approved'): ?>
            <a href="complaints.php?forward=<?= $c['id'] ?>" class="btn btn-sm btn-primary" onclick="return confirm('Forward this complaint to Admin?')">
                <i class="fas fa-share me-1"></i>Forward to Admin
            </a>
            <?php endif; ?>
        </div>

        <!-- DETAIL MODAL -->
        <div class="modal fade" id="modal<?= $c['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-shield-alt me-2"></i><?= $c['complaint_code'] ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <!-- Details -->
            <div class="p-3 rounded mb-3" style="background:#f0f5ff;">
                <div class="row g-2" style="font-size:0.88rem;">
                    <div class="col-md-6"><strong>Passenger:</strong> <?= htmlspecialchars($c['passenger_name']) ?></div>
                    <div class="col-md-6"><strong>Vendor:</strong> <?= htmlspecialchars($c['vendor_name']) ?></div>
                    <div class="col-md-6"><strong>Category:</strong> <?= $c['category'] ? ucfirst(str_replace('_',' ',$c['category'])) : '—' ?></div>
                    <div class="col-md-6"><strong>PNR:</strong> <?= $c['pnr_number'] ? '<code>'.$c['pnr_number'].'</code>' : '—' ?></div>
                    <?php if (!empty($c['passenger_contact'])): ?>
                    <div class="col-md-6"><strong><i class="fas fa-phone me-1 text-success"></i>Passenger Phone:</strong>
                        <a href="tel:<?= htmlspecialchars($c['passenger_contact']) ?>" class="fw-600 text-success"><?= htmlspecialchars($c['passenger_contact']) ?></a>
                        <span class="text-muted ms-1" style="font-size:0.75rem;">(call to confirm)</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($c['train_number'])): ?><div class="col-md-6"><strong>Train:</strong> <?= htmlspecialchars($c['train_number']) ?></div><?php endif; ?>
                    <?php if (!empty($c['from_station']) || !empty($c['to_station'])): ?>
                    <div class="col-md-6"><strong>Journey:</strong> <?= htmlspecialchars($c['from_station'] ?? '?') ?> → <?= htmlspecialchars($c['to_station'] ?? '?') ?></div>
                    <?php endif; ?>
                    <div class="col-md-6"><strong>Filed:</strong> <?= date('d M Y H:i', strtotime($c['created_at'])) ?></div>
                    <div class="col-md-6"><strong>Status:</strong> <span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></div>
                    <div class="col-md-6"><strong>Priority:</strong> <span class="badge <?= $prio[0] ?>" <?= isset($prio[2])? 'style="'.$prio[2].'"':'' ?>><?= $prio[1] ?></span></div>
                </div>
            </div>
            <div class="mb-3"><strong>Subject:</strong> <?= htmlspecialchars($c['subject']) ?></div>
            <div class="mb-3"><strong>Description:</strong><p class="mt-1 mb-0"><?= nl2br(htmlspecialchars($c['description'])) ?></p></div>
            <?php if (!empty($c['image_path'])): ?>
            <div class="mb-3"><strong>Attached Image:</strong><div class="mt-2"><a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank"><img src="../<?= htmlspecialchars($c['image_path']) ?>" style="max-height:200px;border-radius:8px;border:1px solid #dee2e6;"></a></div></div>
            <?php endif; ?>
            <?php if ($c['sentiment']): ?>
            <div class="mb-3 p-2 rounded" style="background:#fff3cd;font-size:0.85rem;">
                <i class="fas fa-robot me-1"></i><strong>AI:</strong>
                <span class="badge bg-<?= $c['sentiment']==='negative'?'danger':($c['sentiment']==='positive'?'success':'secondary') ?>"><?= ucfirst($c['sentiment']) ?> (<?= number_format($c['sentiment_score'],1) ?>%)</span>
                <?php if ($c['ai_category']): ?><span class="badge bg-info ms-1"><?= ucfirst(str_replace('_',' ',$c['ai_category'])) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- History -->
            <?php
            $hist = $conn->query("SELECT ch.*, u.name FROM complaint_history ch JOIN users u ON ch.action_by=u.id WHERE ch.complaint_id={$c['id']} ORDER BY ch.created_at ASC");
            if ($hist->num_rows > 0): ?>
            <div class="mb-3">
                <strong>Complaint History:</strong>
                <div class="mt-2" style="font-size:0.82rem;">
                <?php while ($h = $hist->fetch_assoc()): ?>
                <div class="d-flex gap-2 mb-2">
                    <div class="text-muted" style="min-width:130px;"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></div>
                    <div><span class="badge bg-secondary"><?= ucfirst($h['role']) ?></span> <strong><?= htmlspecialchars($h['name']) ?></strong> — <?= ucfirst(str_replace('_',' ',$h['action'])) ?><?php if ($h['note']): ?>: <em><?= htmlspecialchars($h['note']) ?></em><?php endif; ?></div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Officer Action Form -->
            <?php if (in_array($c['status'], ['under_verification','submitted','more_info_requested'])): ?>
            <hr>
            <strong>Take Action:</strong>
            <form method="POST" class="mt-2">
                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                <div class="mb-3">
                    <label class="form-label">Set Priority</label>
                    <div class="d-flex gap-2">
                        <?php
                        $prioRadio = ['low'=>['bg-success','Low'],'medium'=>['bg-warning text-dark','Medium'],'high'=>['text-white','High','background:#e65c00'],'critical'=>['bg-danger','Critical']];
                        foreach ($prioRadio as $pk=>$pv): ?>
                        <label class="d-flex align-items-center gap-1" style="cursor:pointer;">
                            <input type="radio" name="priority" value="<?= $pk ?>" <?= ($c['priority']??'medium')===$pk?'checked':'' ?>>
                            <span class="badge <?= $pv[0] ?>" <?= isset($pv[2])? 'style="'.$pv[2].'"':'' ?>><?= $pv[1] ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="mb-3">
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" name="officer_action" value="approve" class="btn btn-success" onclick="document.getElementById('note<?= $c['id'] ?>').required=true;">
                            <i class="fas fa-check me-1"></i>Approve
                        </button>
                        <button type="submit" name="officer_action" value="reject" class="btn btn-danger" onclick="document.getElementById('note<?= $c['id'] ?>').required=true;">
                            <i class="fas fa-times me-1"></i>Reject
                        </button>
                        <button type="submit" name="officer_action" value="request_more_info" class="btn btn-warning" onclick="document.getElementById('moreinfo<?= $c['id'] ?>').required=true;">
                            <i class="fas fa-question me-1"></i>Request More Info
                        </button>
                    </div>
                </div>
                <div class="mb-2">
                    <label class="form-label">Verification Note (for Approve/Reject) *</label>
                    <textarea id="note<?= $c['id'] ?>" name="officer_note" class="form-control" rows="2" placeholder="Describe your findings or reason for decision..."></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label">More Info Request (for Request More Info) *</label>
                    <textarea id="moreinfo<?= $c['id'] ?>" name="more_info_request" class="form-control" rows="2" placeholder="What additional information do you need from the passenger?"></textarea>
                </div>
            </form>
            <?php elseif ($c['status'] === 'approved'): ?>
            <div class="alert alert-success mt-2 mb-0">
                <i class="fas fa-check-circle me-2"></i>Complaint approved. Click <strong>Forward to Admin</strong> to escalate.
            </div>
            <?php endif; ?>
        </div>
        </div>
        </div>
        </div>
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
</div>
<?php include '../includes/footer.php'; ?>
