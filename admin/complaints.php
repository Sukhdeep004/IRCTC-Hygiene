<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Complaints Management';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complaint_id'])) {
    $cid    = (int)$_POST['complaint_id'];
    $action = sanitize($_POST['admin_action']);
    $note   = sanitize($_POST['admin_ack_note'] ?? '');
    $dept   = sanitize($_POST['department'] ?? '');

    $now = date('Y-m-d H:i:s');
    if ($action === 'delete') {
        $conn->query("DELETE FROM complaint_history WHERE complaint_id=$cid");
        $conn->query("DELETE FROM complaints WHERE id=$cid");
        $_SESSION['msg_success'] = 'Complaint deleted successfully.';
        redirect('complaints.php');
    } elseif ($action === 'resolve') {
        $conn->query("UPDATE complaints SET status='resolved', admin_acknowledged=1, admin_ack_note='$note', department='$dept', updated_at='$now' WHERE id=$cid");
        logComplaintHistory($cid, $_SESSION['user_id'], 'admin', 'resolved', $note);
        $_SESSION['msg_success'] = 'Complaint marked as resolved.';
    } elseif ($action === 'close') {
        $conn->query("UPDATE complaints SET status='closed', admin_acknowledged=1, admin_ack_note='$note', department='$dept', updated_at='$now' WHERE id=$cid");
        logComplaintHistory($cid, $_SESSION['user_id'], 'admin', 'closed', $note);
        $_SESSION['msg_success'] = 'Complaint closed.';
    } elseif ($action === 'assign') {
        if (!$dept) { $_SESSION['msg_error'] = 'Please specify a department.'; redirect('complaints.php'); }
        $conn->query("UPDATE complaints SET department='$dept', admin_acknowledged=1, admin_ack_note='$note', updated_at='$now' WHERE id=$cid");
        logComplaintHistory($cid, $_SESSION['user_id'], 'admin', 'assigned_department', "Assigned to: $dept. $note");
        $_SESSION['msg_success'] = "Complaint assigned to $dept.";
    }
    redirect('complaints.php');
}

// Multi-condition filter builder
$status    = isset($_GET['status'])    ? sanitize($_GET['status'])    : '';
$vendor_id = isset($_GET['vendor_id']) ? (int)$_GET['vendor_id']      : 0;
$category  = isset($_GET['category'])  ? sanitize($_GET['category'])  : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to'])   ? sanitize($_GET['date_to'])   : '';

$conditions = [];
if ($status)    $conditions[] = "c.status='$status'";
if ($vendor_id) $conditions[] = "c.vendor_id=$vendor_id";
if ($category)  $conditions[] = "c.category='$category'";
if ($date_from) $conditions[] = "DATE(c.created_at) >= '$date_from'";
if ($date_to)   $conditions[] = "DATE(c.created_at) <= '$date_to'";
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$complaints = $conn->query("
    SELECT c.*, v.vendor_name, u.name AS passenger_name, o.name AS officer_name
    FROM complaints c
    JOIN vendors v ON c.vendor_id=v.id
    JOIN users u ON c.passenger_id=u.id
    LEFT JOIN users o ON c.officer_id=o.id
    $where ORDER BY c.created_at DESC
");

// Populate filter dropdowns
$vendorsList    = $conn->query("SELECT id, vendor_name FROM vendors ORDER BY vendor_name");
$categoriesList = $conn->query("SELECT DISTINCT category FROM complaints WHERE category IS NOT NULL ORDER BY category");

$stats = [];
foreach (['forwarded_to_admin','resolved','closed','rejected'] as $s)
    $stats[$s] = $conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='$s'")->fetch_assoc()['c'];
$stats['all'] = $conn->query("SELECT COUNT(*) as c FROM complaints")->fetch_assoc()['c'];

// Build current query string for filter form (preserves all params)
$currentParams = array_filter([
    'status'    => $status,
    'vendor_id' => $vendor_id ?: '',
    'category'  => $category,
    'date_from' => $date_from,
    'date_to'   => $date_to,
]);

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
    <a href="complaints.php" class="active"><i class="fas fa-file-alt fa-fw me-2"></i>Complaints
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
    <hr style="margin:8px;">
    <a href="../logout.php"><i class="fas fa-sign-out-alt fa-fw me-2"></i>Logout</a>
</div>
</div>
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-file-alt me-2"></i>Complaints Management</h3>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-primary"><?= $stats['forwarded_to_admin'] ?></div><div class="stat-label">Awaiting Action</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-success"><?= $stats['resolved'] ?></div><div class="stat-label">Resolved</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-dark"><?= $stats['closed'] ?></div><div class="stat-label">Closed</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-danger"><?= $stats['rejected'] ?></div><div class="stat-label">Rejected</div></div></div>
    <div class="col-6 col-md-2"><div class="stat-card"><div class="stat-num text-secondary"><?= $stats['all'] ?></div><div class="stat-label">Total</div></div></div>
</div>

<!-- Multi-field filter form -->
<form method="GET" action="complaints.php" class="card p-3 mb-3">
<div class="row g-2 align-items-end">
    <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">Vendor</label>
        <select name="vendor_id" class="form-select form-select-sm">
            <option value="">All Vendors</option>
            <?php while ($v = $vendorsList->fetch_assoc()): ?>
            <option value="<?= $v['id'] ?>" <?= $vendor_id == $v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['vendor_name']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">Category</label>
        <select name="category" class="form-select form-select-sm">
            <option value="">All Categories</option>
            <?php while ($cat = $categoriesList->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $cat['category'])) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">From Date</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= htmlspecialchars($date_from) ?>">
    </div>
    <div class="col-md-2">
        <label class="form-label small fw-semibold mb-1">To Date</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= htmlspecialchars($date_to) ?>">
    </div>
    <div class="col-md-2">
        <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-filter me-1"></i>Apply</button>
    </div>
    <div class="col-md-1">
        <a href="complaints.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
    </div>
</div>
</form>

<!-- Status tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
    <?php
    $tabs = [
        ''                   => 'All Complaints',
        'forwarded_to_admin' => 'Forwarded (Action Needed)',
        'resolved'           => 'Resolved',
        'closed'             => 'Closed',
        'rejected'           => 'Rejected',
    ];
    foreach ($tabs as $k => $label):
        // Build tab URL preserving other filters
        $tabParams = array_filter([
            'status'    => $k,
            'vendor_id' => $vendor_id ?: '',
            'category'  => $category,
            'date_from' => $date_from,
            'date_to'   => $date_to,
        ]);
        $tabUrl = 'complaints.php' . ($tabParams ? '?' . http_build_query($tabParams) : '');
    ?>
    <a href="<?= $tabUrl ?>" class="btn btn-sm <?= $status === $k ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
    <?php endforeach; ?>
</div>

<!-- Showing count -->
<p class="text-muted small mb-2"><i class="fas fa-list me-1"></i>Showing <strong><?= $complaints->num_rows ?></strong> complaint<?= $complaints->num_rows !== 1 ? 's' : '' ?></p>

<div class="card">
<div class="table-responsive">
<table class="table mb-0">
<thead><tr><th>Code</th><th>Passenger</th><th>Vendor</th><th>Subject</th><th>Priority</th><th>Officer</th><th>Filed</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php if ($complaints->num_rows === 0): ?>
<tr><td colspan="8" class="text-center py-4 text-muted">No complaints found.</td></tr>
<?php else: while ($c = $complaints->fetch_assoc()):
    $sl = complaintStatusLabel($c['status']);
    $prioCfg = ['low'=>['bg-success','Low',''],'medium'=>['bg-warning text-dark','Medium',''],'high'=>['text-white','High','background:#e65c00'],'critical'=>['bg-danger','Critical','']];
    $pr = $prioCfg[$c['priority'] ?? 'medium'] ?? $prioCfg['medium'];
?>
<tr>
    <td><code style="font-size:0.75rem;"><?= $c['complaint_code'] ?></code></td>
    <td style="font-size:0.85rem;"><?= htmlspecialchars($c['passenger_name']) ?></td>
    <td style="font-size:0.85rem;"><?= htmlspecialchars($c['vendor_name']) ?></td>
    <td>
        <strong style="font-size:0.85rem;"><?= htmlspecialchars(substr($c['subject'],0,35)) ?></strong>
        <?php if ($c['category']): ?><div><span class="badge bg-light text-dark border" style="font-size:0.7rem;"><?= ucfirst(str_replace('_',' ',$c['category'])) ?></span></div><?php endif; ?>
        <?php if ($c['department']): ?><div class="text-muted" style="font-size:0.72rem;"><i class="fas fa-building me-1"></i><?= htmlspecialchars($c['department']) ?></div><?php endif; ?>
        <?php if (!empty($c['image_path'])): ?><div class="mt-1"><a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank"><img src="../<?= htmlspecialchars($c['image_path']) ?>" style="max-height:36px;border-radius:4px;border:1px solid #dee2e6;"></a></div><?php endif; ?>
    </td>
    <td>
        <?php if (!empty($c['priority'])): ?>
        <span class="badge <?= $pr[0] ?>" <?= $pr[2]?'style="'.$pr[2].'"':'' ?>><?= $pr[1] ?></span>
        <?php else: ?>
        <span class="text-muted" style="font-size:0.78rem;">—</span>
        <?php endif; ?>
    </td>
    <td style="font-size:0.82rem;"><?= $c['officer_name'] ? htmlspecialchars($c['officer_name']) : '<span class="text-muted">—</span>' ?></td>
    <td><small><?= date('d M Y', strtotime($c['created_at'])) ?></small></td>
    <td><span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></td>
    <td>
        <div class="d-flex gap-1 flex-wrap">
        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal<?= $c['id'] ?>">Manage</button>
        <form method="POST" onsubmit="return confirm('Permanently delete complaint <?= $c['complaint_code'] ?>? This cannot be undone.');" style="display:inline;">
            <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
            <input type="hidden" name="admin_action" value="delete">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
        </form>
        </div>

        <!-- ADMIN MODAL -->
        <div class="modal fade" id="modal<?= $c['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
        <div class="modal-content">
        <div class="modal-header bg-primary text-white">
            <h5 class="modal-title fw-700"><i class="fas fa-file-alt me-2"></i><?= $c['complaint_code'] ?></h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
            <!-- Complaint Info -->
            <div class="p-3 rounded mb-3" style="background:#f0f5ff;font-size:0.88rem;">
                <div class="row g-2">
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
                    <?php if (!empty($c['train_number'])): ?><div class="col-md-6"><strong>Train No:</strong> <?= htmlspecialchars($c['train_number']) ?></div><?php endif; ?>
                    <?php if (!empty($c['from_station']) || !empty($c['to_station'])): ?>
                    <div class="col-md-6"><strong>Journey:</strong> <?= htmlspecialchars($c['from_station'] ?? '?') ?> → <?= htmlspecialchars($c['to_station'] ?? '?') ?></div>
                    <?php endif; ?>
                    <div class="col-md-6"><strong>Filed:</strong> <?= date('d M Y H:i', strtotime($c['created_at'])) ?></div>
                    <div class="col-md-6"><strong>Status:</strong> <span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span></div>
                    <?php if (!empty($c['priority'])): ?>
                    <div class="col-md-6"><strong>Priority:</strong> <span class="badge <?= $pr[0] ?>" <?= $pr[2]?'style="'.$pr[2].'"':'' ?>><?= $pr[1] ?></span></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="mb-3"><strong>Subject:</strong> <?= htmlspecialchars($c['subject']) ?></div>
            <div class="mb-3"><strong>Description:</strong><p class="mt-1 mb-0"><?= nl2br(htmlspecialchars($c['description'])) ?></p></div>
            <?php if (!empty($c['image_path'])): ?>
            <div class="mb-3"><strong>Attached Image:</strong><div class="mt-2"><a href="../<?= htmlspecialchars($c['image_path']) ?>" target="_blank"><img src="../<?= htmlspecialchars($c['image_path']) ?>" style="max-height:200px;border-radius:8px;border:1px solid #dee2e6;"></a></div></div>
            <?php endif; ?>

            <!-- AI Analysis -->
            <?php if ($c['sentiment']): ?>
            <div class="mb-3 p-2 rounded" style="background:#fff3cd;font-size:0.85rem;">
                <i class="fas fa-robot me-1"></i><strong>AI Analysis:</strong>
                <span class="badge bg-<?= $c['sentiment']==='negative'?'danger':($c['sentiment']==='positive'?'success':'secondary') ?>"><?= ucfirst($c['sentiment']) ?> (<?= number_format($c['sentiment_score'],1) ?>%)</span>
                <?php if ($c['ai_category']): ?><span class="badge bg-info ms-1"><?= ucfirst(str_replace('_',' ',$c['ai_category'])) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Officer Notes -->
            <?php if ($c['officer_note'] || $c['officer_action']): ?>
            <div class="mb-3 p-3 rounded" style="background:#e8f5e9;">
                <strong><i class="fas fa-user-shield me-1 text-success"></i>Officer Verification:</strong>
                <?php if ($c['officer_action']): ?><span class="badge bg-success ms-1"><?= ucfirst(str_replace('_',' ',$c['officer_action'])) ?></span><?php endif; ?>
                <?php if ($c['officer_name']): ?><span class="text-muted ms-2" style="font-size:0.82rem;">by <?= htmlspecialchars($c['officer_name']) ?></span><?php endif; ?>
                <?php if ($c['officer_note']): ?><p class="mt-2 mb-0"><?= nl2br(htmlspecialchars($c['officer_note'])) ?></p><?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Complaint History -->
            <?php
            $hist = $conn->query("SELECT ch.*, u.name FROM complaint_history ch JOIN users u ON ch.action_by=u.id WHERE ch.complaint_id={$c['id']} ORDER BY ch.created_at ASC");
            if ($hist->num_rows > 0): ?>
            <div class="mb-3">
                <strong>Full History / Audit Trail:</strong>
                <div class="mt-2 p-2 rounded" style="background:#f8f9fa;font-size:0.8rem;max-height:160px;overflow-y:auto;">
                <?php while ($h = $hist->fetch_assoc()): ?>
                <div class="d-flex gap-2 mb-1 border-bottom pb-1">
                    <div class="text-muted" style="min-width:120px;"><?= date('d M Y H:i', strtotime($h['created_at'])) ?></div>
                    <div><span class="badge bg-secondary"><?= ucfirst($h['role']) ?></span> <strong><?= htmlspecialchars($h['name']) ?></strong> — <?= ucfirst(str_replace('_',' ',$h['action'])) ?><?php if ($h['note']): ?>: <em><?= htmlspecialchars(substr($h['note'],0,80)) ?></em><?php endif; ?></div>
                </div>
                <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Admin Action Form -->
            <?php if (!in_array($c['status'], ['resolved','closed'])): ?>
            <hr>
            <form method="POST">
                <input type="hidden" name="complaint_id" value="<?= $c['id'] ?>">
                <div class="mb-3">
                    <label class="form-label fw-600">Assign to Department</label>
                    <select name="department" class="form-select">
                        <option value="" <?= !$c['department']?'selected':'' ?>>-- No Department --</option>
                        <option value="Food Safety" <?= $c['department']==='Food Safety'?'selected':'' ?>>Food Safety</option>
                        <option value="Hygiene Inspection" <?= $c['department']==='Hygiene Inspection'?'selected':'' ?>>Hygiene Inspection</option>
                        <option value="Customer Relations" <?= $c['department']==='Customer Relations'?'selected':'' ?>>Customer Relations</option>
                        <option value="Vendor Management" <?= $c['department']==='Vendor Management'?'selected':'' ?>>Vendor Management</option>
                        <option value="Legal" <?= $c['department']==='Legal'?'selected':'' ?>>Legal</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600">Admin Note / Action Taken</label>
                    <textarea name="admin_ack_note" class="form-control" rows="2" placeholder="Describe action taken or final remarks..."><?= htmlspecialchars($c['admin_ack_note'] ?? '') ?></textarea>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="submit" name="admin_action" value="assign" class="btn btn-outline-primary"><i class="fas fa-building me-1"></i>Assign Department</button>
                    <button type="submit" name="admin_action" value="resolve" class="btn btn-success"><i class="fas fa-check me-1"></i>Mark Resolved</button>
                    <button type="submit" name="admin_action" value="close" class="btn btn-dark"><i class="fas fa-lock me-1"></i>Close Complaint</button>
                </div>
            </form>
            <?php else: ?>
            <div class="alert alert-success mb-0">
                <i class="fas fa-check-double me-2"></i>This complaint has been <strong><?= $c['status'] ?></strong>.
                <?php if ($c['admin_ack_note']): ?><br><em><?= htmlspecialchars($c['admin_ack_note']) ?></em><?php endif; ?>
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
