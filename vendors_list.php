<?php
require_once 'includes/config.php';
$pageTitle = 'Vendor Directory';

$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$zone   = isset($_GET['zone'])   ? sanitize($_GET['zone'])   : '';
$status_f = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$where = "WHERE 1=1";
if ($search)   $where .= " AND (v.vendor_name LIKE '%$search%' OR v.station LIKE '%$search%' OR v.license_number LIKE '%$search%')";
if ($zone)     $where .= " AND v.zone LIKE '%$zone%'";
if ($status_f) $where .= " AND v.status='$status_f'";

$vendors = $conn->query("
    SELECT v.*, COUNT(r.id) as rating_count, COUNT(DISTINCT c.id) as complaint_count
    FROM vendors v
    LEFT JOIN ratings r ON v.id = r.vendor_id
    LEFT JOIN complaints c ON v.id = c.vendor_id
    $where
    GROUP BY v.id
    ORDER BY v.current_score DESC
");

$zones = $conn->query("SELECT DISTINCT zone FROM vendors ORDER BY zone");
include 'includes/header.php';
?>

<div class="page-hero py-4">
<div class="container">
    <a href="index.php" class="btn-back mb-3 d-inline-flex"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
    <h1 style="font-size:2rem;"><i class="fas fa-store me-2"></i>Vendor Directory</h1>
    <p class="mb-0" style="opacity:0.8;">Browse and search all IRCTC authorized catering vendors</p>
</div>
</div>

<div class="container py-4">

<!-- SEARCH BAR -->
<div class="card p-3 mb-4">
<form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
        <label class="form-label">Search Vendor / Station</label>
        <input type="text" name="search" class="form-control" placeholder="Name, station, license..." value="<?= htmlspecialchars($search) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label">Filter by Zone</label>
        <select name="zone" class="form-select">
            <option value="">All Zones</option>
            <?php while ($z = $zones->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($z['zone']) ?>" <?= $zone === $z['zone'] ? 'selected' : '' ?>><?= htmlspecialchars($z['zone']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">Filter by Status</label>
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="active"       <?= $status_f==='active'       ?'selected':'' ?>>Active</option>
            <option value="under_review" <?= $status_f==='under_review'  ?'selected':'' ?>>Under Review</option>
            <option value="suspended"   <?= $status_f==='suspended'     ?'selected':'' ?>>Suspended</option>
        </select>
    </div>
    <div class="col-md-2">
        <button class="btn btn-primary w-100" type="submit"><i class="fas fa-search me-1"></i>Search</button>
    </div>
</form>
</div>

<!-- VENDOR TABLE -->
<div class="card">
<div class="card-header-blue d-flex justify-content-between align-items-center">
    <span><i class="fas fa-list me-2"></i>Vendors (<?= $vendors->num_rows ?>)</span>
</div>
<div class="table-responsive">
<table class="table mb-0">
<thead><tr>
    <th>Vendor Name</th><th>Zone</th><th>Station</th><th>License</th>
    <th>Hygiene Score</th><th>Classification</th><th>Ratings</th><th>Status</th><th>Detail</th>
</tr></thead>
<tbody>
<?php if ($vendors->num_rows === 0): ?>
<tr><td colspan="9" class="text-center py-4 text-muted">No vendors found matching your criteria.</td></tr>
<?php else: while ($v = $vendors->fetch_assoc()):
    $cls = classifyVendor($v['current_score']);
?>
<tr>
    <td><strong><?= htmlspecialchars($v['vendor_name']) ?></strong></td>
    <td><?= htmlspecialchars($v['zone']) ?></td>
    <td><i class="fas fa-map-marker-alt text-danger me-1"></i><?= htmlspecialchars($v['station']) ?></td>
    <td><code style="font-size:0.78rem;"><?= htmlspecialchars($v['license_number']) ?></code></td>
    <td>
        <?= renderStars($v['current_score']) ?>
        <strong class="ms-1"><?= number_format($v['current_score'],2) ?></strong>
    </td>
    <td><span class="score-badge bg-<?= $cls['class'] === 'warning' ? 'warning text-dark' : $cls['class'].' text-white' ?>"><?= $cls['icon'] ?> <?= $cls['label'] ?></span></td>
    <td><?= $v['rating_count'] ?></td>
    <td><span class="badge status-<?= $v['status'] ?> rounded-pill px-3"><?= ucwords(str_replace('_',' ',$v['status'])) ?></span></td>
    <td><a href="vendor_profile.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
</tr>
<?php endwhile; endif; ?>
</tbody>
</table>
</div>
</div>

</div>
<?php include 'includes/footer.php'; ?>
