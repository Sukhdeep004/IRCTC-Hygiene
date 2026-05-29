<?php
require_once '../includes/config.php';
define('BASEPATH', '../');
$pageTitle = 'Complaint Analytics';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

// --- Date range validation ---
$from = null;
$to   = null;
$dateWhere = '';

$rawFrom = $_GET['date_from'] ?? '';
$rawTo   = $_GET['date_to']   ?? '';

$dtFrom = $rawFrom ? DateTime::createFromFormat('Y-m-d', $rawFrom) : false;
$dtTo   = $rawTo   ? DateTime::createFromFormat('Y-m-d', $rawTo)   : false;

if ($dtFrom && $dtFrom->format('Y-m-d') === $rawFrom) {
    $from = $rawFrom;
}
if ($dtTo && $dtTo->format('Y-m-d') === $rawTo) {
    $to = $rawTo;
}

if ($from && $to) {
    $dateWhere = "AND created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'";
} elseif ($from) {
    $dateWhere = "AND created_at >= '$from 00:00:00'";
} elseif ($to) {
    $dateWhere = "AND created_at <= '$to 23:59:59'";
}

// Build WHERE clause for simple complaints queries
$mainWhere = $dateWhere ? "WHERE 1=1 $dateWhere" : '';

// --- Query 1: Total count ---
$totalRes = $conn->query("SELECT COUNT(*) as cnt FROM complaints $mainWhere");
$total    = (int)$totalRes->fetch_assoc()['cnt'];

// --- Query 2: Category breakdown ---
$catRes = $conn->query("
    SELECT category, COUNT(*) as cnt
    FROM complaints
    $mainWhere
    GROUP BY category
    ORDER BY cnt DESC
");

// --- Query 3: Top-5 vendors ---
$vendorJoinWhere = $dateWhere ? "AND c.created_at BETWEEN '$from 00:00:00' AND '$to 23:59:59'" : '';
// If only from or only to, adjust accordingly
if (!$from && $to) {
    $vendorJoinWhere = "AND c.created_at <= '$to 23:59:59'";
} elseif ($from && !$to) {
    $vendorJoinWhere = "AND c.created_at >= '$from 00:00:00'";
}

$vendorRes = $conn->query("
    SELECT v.vendor_name, COUNT(c.id) as cnt
    FROM vendors v
    LEFT JOIN complaints c ON v.id = c.vendor_id $vendorJoinWhere
    GROUP BY v.id
    ORDER BY cnt DESC
    LIMIT 5
");

// --- Query 4: Monthly trend (current year) ---
$monthlyWhere = $dateWhere ? $dateWhere : '';
$monthlyRes = $conn->query("
    SELECT MONTH(created_at) as m, COUNT(*) as cnt
    FROM complaints
    WHERE YEAR(created_at) = YEAR(CURDATE()) $monthlyWhere
    GROUP BY m
");

// --- Process results ---
$catLabels = [];
$catCounts = [];
while ($row = $catRes->fetch_assoc()) {
    $catLabels[] = $row['category'] ? ucfirst(str_replace('_', ' ', $row['category'])) : 'Uncategorized';
    $catCounts[] = (int)$row['cnt'];
}

$vendorLabels = [];
$vendorCounts = [];
while ($row = $vendorRes->fetch_assoc()) {
    $vendorLabels[] = $row['vendor_name'];
    $vendorCounts[] = (int)$row['cnt'];
}

// Fill monthly trend Jan–Dec with 0 for missing months
$monthlyData = array_fill(1, 12, 0);
while ($row = $monthlyRes->fetch_assoc()) {
    $monthlyData[(int)$row['m']] = (int)$row['cnt'];
}
$monthNames  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$monthCounts = array_values($monthlyData); // index 0 = Jan

include '../includes/header.php';
?>
<div style="background:#f0f5ff;min-height:100vh;">
<div class="container-fluid py-4">
<div class="row">

<!-- SIDEBAR -->
<div class="col-xl-2 col-lg-3 mb-4">
<div class="sidebar-nav">
    <div class="sidebar-header">⚙️ Admin Panel</div>
    <a href="dashboard.php"><i class="fas fa-tachometer-alt fa-fw me-2"></i>Dashboard</a>
    <a href="vendors.php"><i class="fas fa-store fa-fw me-2"></i>Vendors</a>
    <a href="ratings.php"><i class="fas fa-star fa-fw me-2"></i>Ratings</a>
    <a href="complaints.php"><i class="fas fa-file-alt fa-fw me-2"></i>Complaints
      <?php $nc=((int)$conn->query("SELECT COUNT(*) as c FROM complaints WHERE status='forwarded_to_admin'")->fetch_assoc()['c']); if($nc>0): ?><span class="badge bg-danger ms-auto"><?= $nc ?></span><?php endif; ?>
    </a>
    <a href="inspections.php"><i class="fas fa-clipboard-check fa-fw me-2"></i>Inspections</a>
    <a href="officers.php"><i class="fas fa-user-shield fa-fw me-2"></i>Officers</a>
    <a href="alerts.php"><i class="fas fa-bell fa-fw me-2"></i>Alerts</a>
    <a href="analytics.php" class="active"><i class="fas fa-chart-bar fa-fw me-2"></i>Analytics</a>
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

<!-- MAIN -->
<div class="col-xl-10 col-lg-9">
<div class="d-flex align-items-center gap-3 mb-3">
    <a href="dashboard.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
    <h3 class="fw-700 mb-0"><i class="fas fa-chart-bar me-2"></i>Complaint Analytics</h3>
</div>

<?php if (isset($_SESSION['msg_success'])): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <?= $_SESSION['msg_success'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg_success']); endif; ?>
<?php if (isset($_SESSION['msg_error'])): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <?= $_SESSION['msg_error'] ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php unset($_SESSION['msg_error']); endif; ?>

<!-- Date-range filter form -->
<form method="GET" action="analytics.php" class="card p-3 mb-4">
<div class="row g-2 align-items-end">
    <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">From Date</label>
        <input type="date" name="date_from" class="form-control form-control-sm"
               value="<?= htmlspecialchars($rawFrom) ?>">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold mb-1">To Date</label>
        <input type="date" name="date_to" class="form-control form-control-sm"
               value="<?= htmlspecialchars($rawTo) ?>">
    </div>
    <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm w-100">
            <i class="fas fa-filter me-1"></i>Apply Filter
        </button>
    </div>
    <div class="col-md-2">
        <a href="analytics.php" class="btn btn-outline-secondary btn-sm w-100">Clear</a>
    </div>
</div>
</form>

<!-- Total count stat -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num text-primary"><?= $total ?></div>
            <div class="stat-label">Total Complaints<?= ($from || $to) ? ' (filtered)' : '' ?></div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num text-info"><?= count($catLabels) ?></div>
            <div class="stat-label">Categories</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num text-warning"><?= count($vendorLabels) ?></div>
            <div class="stat-label">Vendors Shown</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <div class="stat-num text-success"><?= array_sum($monthCounts) ?></div>
            <div class="stat-label">This Year</div>
        </div>
    </div>
</div>

<?php if ($total === 0): ?>
<div class="alert alert-info text-center py-4">
    <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
    <strong>No complaints found for the selected period.</strong>
    <?php if ($from || $to): ?>
    <div class="mt-2"><a href="analytics.php" class="btn btn-sm btn-outline-primary">Clear filter to see all</a></div>
    <?php endif; ?>
</div>
<?php else: ?>

<!-- Charts row 1: Category breakdown + Top-5 vendors -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="fw-semibold mb-3"><i class="fas fa-tags me-2 text-primary"></i>Complaints by Category</h6>
            <canvas id="categoryChart" height="260"></canvas>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card p-3">
            <h6 class="fw-semibold mb-3"><i class="fas fa-store me-2 text-warning"></i>Top 5 Vendors by Complaints</h6>
            <canvas id="vendorChart" height="260"></canvas>
        </div>
    </div>
</div>

<!-- Charts row 2: Monthly trend -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card p-3">
            <h6 class="fw-semibold mb-3"><i class="fas fa-chart-line me-2 text-success"></i>Monthly Trend (<?= date('Y') ?>)</h6>
            <canvas id="monthlyChart" height="120"></canvas>
        </div>
    </div>
</div>

<?php endif; ?>

</div><!-- /col main -->
</div><!-- /row -->
</div><!-- /container -->
</div><!-- /bg -->

<?php include '../includes/footer.php'; ?>

<!-- Chart.js CDN — loaded only on this page -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php if ($total > 0): ?>
<script>
// Category bar chart
new Chart(document.getElementById('categoryChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{
            label: 'Complaints',
            data: <?= json_encode($catCounts) ?>,
            backgroundColor: 'rgba(0, 53, 128, 0.75)',
            borderColor: 'rgba(0, 53, 128, 1)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Top-5 vendors horizontal bar chart
new Chart(document.getElementById('vendorChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($vendorLabels) ?>,
        datasets: [{
            label: 'Complaints',
            data: <?= json_encode($vendorCounts) ?>,
            backgroundColor: 'rgba(230, 168, 23, 0.75)',
            borderColor: 'rgba(230, 168, 23, 1)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// Monthly trend line chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($monthNames) ?>,
        datasets: [{
            label: 'Complaints',
            data: <?= json_encode($monthCounts) ?>,
            fill: true,
            backgroundColor: 'rgba(26, 138, 69, 0.15)',
            borderColor: 'rgba(26, 138, 69, 1)',
            pointBackgroundColor: 'rgba(26, 138, 69, 1)',
            tension: 0.3
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});
</script>
<?php endif; ?>
