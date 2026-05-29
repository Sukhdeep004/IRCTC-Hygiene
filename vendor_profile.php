<?php
require_once 'includes/config.php';
require_once 'includes/ai_module.php';
$pageTitle = 'Vendor Profile';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) redirect('vendors_list.php');

$vendor = $conn->query("SELECT * FROM vendors WHERE id=$id")->fetch_assoc();
if (!$vendor) { $_SESSION['msg_error'] = 'Vendor not found.'; redirect('vendors_list.php'); }

// Get AI predictions and insights
$aiTrend = predictVendorTrend($id);
$aiAlerts = generateIntelligentAlert($id);

$ratings = $conn->query("
    SELECT r.*, u.name as passenger_name
    FROM ratings r JOIN users u ON r.passenger_id = u.id
    WHERE r.vendor_id=$id ORDER BY r.created_at DESC LIMIT 10
");

$paramAvgs = $conn->query("
    SELECT AVG(cleanliness) as ac, AVG(food_quality) as af, AVG(packaging) as ap,
           AVG(staff_hygiene) as as2, AVG(timeliness) as at2, COUNT(*) as total
    FROM ratings WHERE vendor_id=$id
")->fetch_assoc();

$inspections = $conn->query("
    SELECT ir.*, u.name as officer_name
    FROM inspection_reports ir JOIN users u ON ir.officer_id = u.id
    WHERE ir.vendor_id=$id ORDER BY ir.inspection_date DESC LIMIT 5
");

$cls = classifyVendor($vendor['current_score']);

$pageTitle = htmlspecialchars($vendor['vendor_name']);
include 'includes/header.php';
?>

<div class="container py-5">
<div class="mb-3">
    <a href="javascript:history.back()" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="vendors_list.php">Vendors</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($vendor['vendor_name']) ?></li>
    </ol>
</nav>

<div class="row g-4">
<!-- VENDOR INFO -->
<div class="col-lg-4">
    <div class="card p-4 text-center mb-3">
        <div style="width:80px;height:80px;background:linear-gradient(135deg,#003580,#0066cc);border-radius:20px;margin:0 auto 15px;display:flex;align-items:center;justify-content:center;font-size:2.5rem;">🏪</div>
        <h4 class="fw-700"><?= htmlspecialchars($vendor['vendor_name']) ?></h4>
        <div class="text-muted mb-2"><i class="fas fa-map-marker-alt me-1 text-danger"></i><?= htmlspecialchars($vendor['station']) ?></div>
        <span class="badge status-<?= $vendor['status'] ?> fs-6 px-3 py-2 mb-3"><?= ucwords(str_replace('_',' ',$vendor['status'])) ?></span>

        <div class="mt-2" style="font-size:2.5rem;font-weight:700;font-family:'Rajdhani',sans-serif;color:#003580;"><?= number_format($vendor['current_score'],2) ?></div>
        <?= renderStars($vendor['current_score']) ?>
        <div class="mt-2">
            <span class="score-badge bg-<?= $cls['class']==='warning'?'warning text-dark':$cls['class'].' text-white' ?> fs-6">
                <?= $cls['icon'] ?> <?= $cls['label'] ?>
            </span>
        </div>

        <hr>
        <table class="table table-sm table-borderless text-start mb-0" style="font-size:0.82rem;">
            <tr><td class="text-muted">Zone</td><td class="fw-500"><?= htmlspecialchars($vendor['zone']) ?></td></tr>
            <tr><td class="text-muted">Train</td><td><?= htmlspecialchars($vendor['train_number']) ?></td></tr>
            <tr><td class="text-muted">License</td><td><code><?= htmlspecialchars($vendor['license_number']) ?></code></td></tr>
            <tr><td class="text-muted">Total Ratings</td><td><?= $paramAvgs['total'] ?></td></tr>
        </table>

        <?php if (isPassenger()): ?>
        <a href="passenger/rate.php?vendor_id=<?= $id ?>" class="btn btn-orange w-100 mt-3">
            <i class="fas fa-star me-2"></i>Rate This Vendor
        </a>
        <a href="passenger/complaint.php?vendor_id=<?= $id ?>" class="btn btn-outline-danger w-100 mt-2">
            <i class="fas fa-exclamation-triangle me-2"></i>File Complaint
        </a>
        <?php endif; ?>
    </div>

    <!-- PARAMETER BREAKDOWN -->
    <div class="card p-4">
        <h6 class="fw-700 mb-3">Parameter Breakdown</h6>
        <?php
        $params = [
            ['Cleanliness','fas fa-broom',$paramAvgs['ac'],30],
            ['Food Quality','fas fa-utensils',$paramAvgs['af'],25],
            ['Staff Hygiene','fas fa-user-shield',$paramAvgs['as2'],20],
            ['Packaging','fas fa-box',$paramAvgs['ap'],15],
            ['Timeliness','fas fa-clock',$paramAvgs['at2'],10],
        ];
        foreach ($params as $p):
            $val = round($p[2] ?? 0, 1);
            $pct = ($val / 5) * 100;
            $color = $pct >= 70 ? 'success' : ($pct >= 50 ? 'warning' : 'danger');
        ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between mb-1" style="font-size:0.82rem;">
                <span><i class="<?= $p[1] ?> me-1 text-primary"></i><?= $p[0] ?> <span class="text-muted">(<?= $p[3] ?>%)</span></span>
                <strong><?= $val ?>/5</strong>
            </div>
            <div class="progress" style="height:7px;">
                <div class="progress-bar bg-<?= $color ?>" style="width:<?= $pct ?>%"></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- RATINGS & INSPECTIONS -->
<div class="col-lg-8">
    <!-- AI INSIGHTS CARD -->
    <?php if ($aiTrend['trend'] !== 'insufficient_data' || !empty($aiAlerts)): ?>
    <div class="card mb-4" style="border-left:4px solid #8b2fc9;">
        <div class="card-header" style="background:#8b2fc9;color:#fff;">
            <i class="fas fa-robot me-2"></i>🤖 AI-Powered Insights
        </div>
        <div class="p-3">
            <?php if ($aiTrend['trend'] !== 'insufficient_data'): ?>
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="p-3 rounded text-center" style="background:#f8f9fa;">
                        <div class="text-muted" style="font-size:0.82rem;">Trend Analysis</div>
                        <div style="font-size:1.8rem;">
                            <?= $aiTrend['trend'] === 'improving' ? '📈' : ($aiTrend['trend'] === 'declining' ? '📉' : '➡️') ?>
                        </div>
                        <strong style="color:<?= $aiTrend['trend']==='improving'?'#1a8a45':($aiTrend['trend']==='declining'?'#c0392b':'#6c757d') ?>;">
                            <?= ucfirst($aiTrend['trend']) ?>
                        </strong>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded text-center" style="background:#f8f9fa;">
                        <div class="text-muted" style="font-size:0.82rem;">Current Average</div>
                        <div style="font-size:1.8rem;font-weight:700;color:#003580;">
                            <?= $aiTrend['current_avg'] ?>
                        </div>
                        <small class="text-muted">Based on <?= $aiTrend['sample_size'] ?> ratings</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="p-3 rounded text-center" style="background:#f8f9fa;">
                        <div class="text-muted" style="font-size:0.82rem;">Predicted Next Score</div>
                        <div style="font-size:1.8rem;font-weight:700;color:#e6a817;">
                            <?= $aiTrend['prediction'] ?>
                        </div>
                        <small class="text-muted">AI Forecast</small>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($aiAlerts)): ?>
            <div class="alert alert-warning mb-0" style="font-size:0.88rem;">
                <strong><i class="fas fa-exclamation-triangle me-1"></i>AI Recommendations:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($aiAlerts as $alert): ?>
                    <li><?= htmlspecialchars($alert['message']) ?> 
                        <span class="badge bg-<?= $alert['priority']==='high'?'danger':'warning' ?>"><?= ucfirst($alert['priority']) ?> Priority</span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- RECENT PASSENGER RATINGS -->
    <div class="card mb-4">
        <div class="card-header-blue"><i class="fas fa-star me-2"></i>Recent Passenger Ratings</div>
        <div class="p-3">
            <?php if ($ratings->num_rows === 0): ?>
            <div class="text-center py-4 text-muted"><i class="fas fa-star fa-2x mb-2"></i><br>No ratings yet.</div>
            <?php else: while ($r = $ratings->fetch_assoc()): ?>
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong><?= htmlspecialchars($r['passenger_name']) ?></strong>
                        <div class="mt-1">
                            <?= renderStars($r['final_score']) ?>
                            <span class="ms-2 fw-600 text-primary"><?= $r['final_score'] ?></span>
                        </div>
                    </div>
                    <small class="text-muted"><?= date('d M Y', strtotime($r['travel_date'])) ?></small>
                </div>
                <div class="row mt-2 g-1" style="font-size:0.75rem;">
                    <div class="col-auto"><span class="badge bg-light text-dark border">🧹 C: <?= $r['cleanliness'] ?>/5</span></div>
                    <div class="col-auto"><span class="badge bg-light text-dark border">🍽️ F: <?= $r['food_quality'] ?>/5</span></div>
                    <div class="col-auto"><span class="badge bg-light text-dark border">📦 P: <?= $r['packaging'] ?>/5</span></div>
                    <div class="col-auto"><span class="badge bg-light text-dark border">👤 S: <?= $r['staff_hygiene'] ?>/5</span></div>
                    <div class="col-auto"><span class="badge bg-light text-dark border">⏱️ T: <?= $r['timeliness'] ?>/5</span></div>
                </div>
                <?php if ($r['comments']): ?>
                <p class="mb-0 mt-2 text-muted" style="font-size:0.85rem;">"<?= htmlspecialchars($r['comments']) ?>"</p>
                <?php endif; ?>
            </div>
            <?php endwhile; endif; ?>
        </div>
    </div>

    <!-- LINKED COMPLAINTS FROM MESSAGES -->
    <?php
    $linkedComplaints = $conn->query("
        SELECT DISTINCT c.id, c.complaint_code, c.subject, c.status, c.created_at
        FROM messages m
        JOIN complaints c ON m.context_id = c.id
        WHERE m.context_type = 'complaint'
          AND (
            (SELECT user_id FROM vendors WHERE id=$id LIMIT 1) IN (m.sender_id, m.receiver_id)
          )
        ORDER BY c.created_at DESC
    ");
    if ($linkedComplaints && $linkedComplaints->num_rows > 0): ?>
    <div class="card mb-4">
        <div class="card-header" style="background:#003580;color:#fff;"><i class="fas fa-link me-2"></i>Complaints Linked in Messages</div>
        <div class="p-3">
        <?php while ($lc = $linkedComplaints->fetch_assoc()):
            $sl = complaintStatusLabel($lc['status']);
        ?>
        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2" style="font-size:0.85rem;">
            <div>
                <code><?= htmlspecialchars($lc['complaint_code']) ?></code>
                <span class="ms-2"><?= htmlspecialchars($lc['subject']) ?></span>
            </div>
            <span class="badge <?= $sl['class'] ?>"><?= $sl['label'] ?></span>
        </div>
        <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- INSPECTION REPORTS -->
    <div class="card">
        <div class="card-header-orange"><i class="fas fa-clipboard-check me-2"></i>Inspection Reports</div>
        <div class="p-3">
            <?php if ($inspections->num_rows === 0): ?>
            <div class="text-center py-3 text-muted">No inspections recorded yet.</div>
            <?php else: while ($ir = $inspections->fetch_assoc()): ?>
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between">
                    <div>
                        <strong><i class="fas fa-user-shield me-1 text-primary"></i><?= htmlspecialchars($ir['officer_name']) ?></strong>
                        <span class="badge bg-primary ms-2">Score: <?= $ir['inspection_score'] ?>/5</span>
                    </div>
                    <small class="text-muted"><?= date('d M Y', strtotime($ir['inspection_date'])) ?></small>
                </div>
                <?php if ($ir['violations']): ?>
                <div class="mt-2 p-2 rounded" style="background:#fff3cd;font-size:0.82rem;">
                    <i class="fas fa-exclamation-triangle text-warning me-1"></i><strong>Violations:</strong> <?= htmlspecialchars($ir['violations']) ?>
                </div>
                <?php endif; ?>
                <?php if ($ir['recommendations']): ?>
                <div class="mt-1 p-2 rounded" style="background:#d1e7dd;font-size:0.82rem;">
                    <i class="fas fa-lightbulb text-success me-1"></i><strong>Recommendations:</strong> <?= htmlspecialchars($ir['recommendations']) ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; endif; ?>
        </div>
    </div>
</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
