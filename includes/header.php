<?php if (!isset($pageTitle)) $pageTitle = 'IRCTC Hygiene Rating'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | IRCTC Hygiene</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&family=Rajdhani:wght@600;700&display=swap" rel="stylesheet">
<style>
:root {
    --navy: #003580;
    --blue: #0066cc;
    --orange: #e87722;
    --green: #1a8a45;
    --red: #c0392b;
    --light: #f0f5ff;
    --shadow: 0 4px 20px rgba(0,53,128,0.12);
}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Poppins', sans-serif; background: var(--light); color: #222; }

/* NAVBAR */
.main-nav {
    background: linear-gradient(90deg, #002060 0%, #003580 50%, #0055b3 100%);
    box-shadow: 0 3px 15px rgba(0,0,0,0.25);
}
.nav-brand {
    display: flex; align-items: center; gap: 12px;
    font-family: 'Rajdhani', sans-serif;
    font-size: 1.4rem; font-weight: 700; color: #fff !important;
    text-decoration: none;
}
.nav-brand .logo-box {
    background: var(--orange);
    width: 42px; height: 42px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; flex-shrink: 0;
}
.nav-brand small { display: block; font-size: 0.65rem; font-weight: 400; opacity: 0.8; font-family: 'Poppins', sans-serif; }
.nav-link-custom {
    color: rgba(255,255,255,0.85) !important;
    font-weight: 500; font-size: 0.88rem;
    padding: 22px 14px !important;
    text-decoration: none;
    display: inline-block;
    transition: color 0.2s, border-bottom 0.2s;
    border-bottom: 3px solid transparent;
}
.nav-link-custom:hover, .nav-link-custom.active {
    color: #ffd700 !important;
    border-bottom-color: #ffd700;
}
.btn-nav-login {
    background: var(--orange); color: #fff !important;
    border-radius: 8px; padding: 8px 20px !important;
    font-weight: 600; font-size: 0.85rem;
    border: none; margin: 12px 0 12px 8px;
    text-decoration: none; display: inline-block;
    transition: background 0.2s;
}
.btn-nav-login:hover { background: #c96010; }

/* HERO */
.page-hero {
    background: linear-gradient(135deg, #002060 0%, #003580 60%, #004daa 100%);
    color: #fff; padding: 40px 0 50px;
    position: relative; overflow: hidden;
}
.page-hero::after {
    content: ''; position: absolute;
    right: -80px; top: -80px;
    width: 350px; height: 350px;
    background: rgba(232,119,34,0.15);
    border-radius: 50%;
}
.page-hero h1 { font-family: 'Rajdhani', sans-serif; font-size: 2.5rem; font-weight: 700; }

/* CARDS */
.card { border: none; border-radius: 14px; box-shadow: var(--shadow); overflow: hidden; }
.card:hover { box-shadow: 0 8px 30px rgba(0,53,128,0.18); transition: box-shadow 0.3s; }
.card-header-blue { background: linear-gradient(90deg, var(--navy), var(--blue)); color: #fff; padding: 15px 20px; font-weight: 600; }
.card-header-orange { background: linear-gradient(90deg, #c96010, var(--orange)); color: #fff; padding: 15px 20px; font-weight: 600; }

/* STARS */
.stars-wrap .star { color: #ddd; font-size: 1.1rem; }
.stars-wrap .star.active { color: #f5a623; }
.stars-wrap .star.half { color: #f5a623; opacity: 0.6; }
.rating-input-star { font-size: 2.2rem; cursor: pointer; color: #ddd; transition: color 0.15s, transform 0.15s; }
.rating-input-star:hover, .rating-input-star.selected { color: #f5a623; transform: scale(1.15); }

/* STAT CARD */
.stat-card { background: #fff; border-radius: 14px; padding: 22px; box-shadow: var(--shadow); text-align: center; }
.stat-card .stat-num { font-family: 'Rajdhani', sans-serif; font-size: 2.4rem; font-weight: 700; }
.stat-card .stat-label { color: #666; font-size: 0.85rem; margin-top: 2px; }

/* SCORE BADGE */
.score-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 14px; border-radius: 20px;
    font-weight: 600; font-size: 0.85rem;
}

/* SIDEBAR NAV */
.sidebar-nav { background: #fff; border-radius: 14px; box-shadow: var(--shadow); overflow: hidden; }
.sidebar-nav a {
    display: flex; align-items: center; gap: 12px;
    padding: 13px 18px; color: #333;
    text-decoration: none; font-weight: 500;
    border-left: 4px solid transparent;
    transition: all 0.2s;
}
.sidebar-nav a:hover, .sidebar-nav a.active {
    background: var(--light);
    color: var(--navy);
    border-left-color: var(--orange);
}
.sidebar-nav .sidebar-header {
    background: linear-gradient(90deg, var(--navy), var(--blue));
    color: #fff; padding: 15px 18px;
    font-weight: 700; font-family: 'Rajdhani', sans-serif;
    font-size: 1.1rem;
}

/* FORM */
.form-control, .form-select {
    border: 1.5px solid #d0daf0;
    border-radius: 9px; padding: 10px 14px;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: var(--blue);
    box-shadow: 0 0 0 3px rgba(0,102,204,0.12);
}
.form-label { font-weight: 500; font-size: 0.88rem; margin-bottom: 5px; color: #444; }

/* BUTTONS */
.btn-primary { background: linear-gradient(90deg, var(--navy), var(--blue)); border: none; border-radius: 9px; font-weight: 600; }
.btn-primary:hover { background: linear-gradient(90deg, #001a4d, #0055aa); transform: translateY(-1px); }
.btn-orange { background: linear-gradient(90deg, #c96010, var(--orange)); color: #fff; border: none; border-radius: 9px; font-weight: 600; }
.btn-orange:hover { color: #fff; transform: translateY(-1px); }
.btn { transition: all 0.2s; }

/* TABLE */
.table th { background: #f0f5ff; font-weight: 600; font-size: 0.82rem; color: #444; }
.table td { vertical-align: middle; font-size: 0.88rem; }

/* ALERT NOTIFICATION */
.notif-dot { position: absolute; top: -4px; right: -4px; width: 16px; height: 16px; background: #e74c3c; border-radius: 50%; font-size: 0.6rem; color: #fff; display: flex; align-items: center; justify-content: center; }

/* STATUS BADGES */
.status-pending             { background: #fff3cd; color: #664d03; }
.status-submitted           { background: #e2e3e5; color: #383d41; }
.status-under_verification  { background: #fff3cd; color: #664d03; }
.status-approved            { background: #cff4fc; color: #055160; }
.status-more_info_requested { background: #fff3cd; color: #664d03; }
.status-forwarded_to_admin  { background: #cfe2ff; color: #084298; }
.status-in_progress         { background: #cfe2ff; color: #084298; }
.status-resolved            { background: #d1e7dd; color: #0a3622; }
.status-closed              { background: #343a40; color: #fff; }
.status-rejected            { background: #f8d7da; color: #58151c; }
.status-active              { background: #d1e7dd; color: #0a3622; }
.status-under_review        { background: #fff3cd; color: #664d03; }
.status-suspended           { background: #f8d7da; color: #58151c; }

/* ALERT BOX */
.alert { border-radius: 10px; border: none; }

/* PROGRESS */
.progress { border-radius: 10px; height: 8px; }

/* TOAST */
.toast-area { position: fixed; top: 20px; right: 20px; z-index: 9999; width: 360px; }
.toast-card {
    background: #fff; border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.18);
    overflow: hidden; margin-bottom: 12px;
    animation: slideInRight 0.35s cubic-bezier(.22,.68,0,1.2) forwards;
}
.toast-card.hiding { animation: slideOutRight 0.3s ease forwards; }
.toast-card .toast-bar { height: 5px; }
.toast-card .toast-bar.success { background: linear-gradient(90deg,#1a8a45,#28c76f); }
.toast-card .toast-bar.danger  { background: linear-gradient(90deg,#c0392b,#e74c3c); }
.toast-card .toast-body { padding: 14px 16px 16px; }
.toast-card .toast-icon { width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.1rem;flex-shrink:0; }
.toast-card .toast-icon.success { background:#e8f5e9;color:#1a8a45; }
.toast-card .toast-icon.danger  { background:#fdecea;color:#c0392b; }
.toast-card .toast-title { font-weight:700;font-size:0.9rem;color:#222; }
.toast-card .toast-msg { font-size:0.82rem;color:#555;margin-top:2px; }
.toast-card .toast-code { display:inline-block;background:#f0f5ff;color:#003580;font-family:monospace;font-weight:700;font-size:0.85rem;padding:2px 10px;border-radius:6px;margin-top:6px;border:1px solid #d0daf0; }
.toast-card .toast-status { display:inline-block;font-size:0.72rem;font-weight:600;padding:2px 10px;border-radius:20px;margin-top:4px; }
.toast-card .toast-close { background:none;border:none;color:#aaa;font-size:1rem;cursor:pointer;padding:0;line-height:1; }
.toast-card .toast-close:hover { color:#333; }
@keyframes slideInRight {
    from { opacity:0; transform:translateX(60px) scale(0.95); }
    to   { opacity:1; transform:translateX(0) scale(1); }
}
@keyframes slideOutRight {
    from { opacity:1; transform:translateX(0); }
    to   { opacity:0; transform:translateX(60px); }
}

/* BACK BUTTON */
.btn-back { display:inline-flex; align-items:center; gap:6px; color:var(--navy); font-weight:500; font-size:0.88rem; text-decoration:none; padding:6px 14px; border-radius:8px; border:1.5px solid #d0daf0; background:#fff; transition:all 0.2s; }
.btn-back:hover { background:var(--light); color:var(--navy); border-color:var(--blue); }

footer {
    background: linear-gradient(135deg, #002060, #001440);
    color: rgba(255,255,255,0.7);
    padding: 35px 0 15px; margin-top: 60px;
}
footer h6 { color: #fff; font-weight: 600; }
footer a { color: rgba(255,255,255,0.6); text-decoration: none; }
footer a:hover { color: #ffd700; }

@media(max-width:768px) {
    .page-hero h1 { font-size: 1.8rem; }
}
</style>
</head>
<body>

<!-- TOAST AREA -->
<div class="toast-area" id="toastArea">
<?php if (isset($_SESSION['msg_success'])):
    $rawMsg = $_SESSION['msg_success'];
    // Extract complaint code if present (CMP-YYYY-XXXX)
    preg_match('/CMP-\d{4}-\d{4}/', $rawMsg, $codeMatch);
    $cmpCode = $codeMatch[0] ?? null;
    // Strip HTML tags for plain message
    $plainMsg = strip_tags($rawMsg);
    unset($_SESSION['msg_success']);
?>
<div class="toast-card" id="phpToastSuccess">
    <div class="toast-bar success"></div>
    <div class="toast-body">
        <div class="d-flex align-items-start gap-3">
            <div class="toast-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="toast-title"><?= $cmpCode ? 'Complaint Submitted Successfully' : 'Success' ?></div>
                    <button class="toast-close ms-2" onclick="dismissToast('phpToastSuccess')"><i class="fas fa-times"></i></button>
                </div>
                <?php if ($cmpCode): ?>
                <div class="toast-msg">Your complaint has been filed and is awaiting officer verification.</div>
                <div><span class="toast-code"><i class="fas fa-hashtag me-1"></i><?= $cmpCode ?></span></div>
                <div><span class="toast-status" style="background:#d1e7dd;color:#0a3622;">Submitted</span></div>
                <?php else: ?>
                <div class="toast-msg"><?= htmlspecialchars($plainMsg) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<?php if (isset($_SESSION['msg_error'])):
    $errMsg = strip_tags($_SESSION['msg_error']);
    unset($_SESSION['msg_error']);
?>
<div class="toast-card" id="phpToastError">
    <div class="toast-bar danger"></div>
    <div class="toast-body">
        <div class="d-flex align-items-start gap-3">
            <div class="toast-icon danger"><i class="fas fa-exclamation-circle"></i></div>
            <div class="flex-grow-1">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="toast-title">Error</div>
                    <button class="toast-close ms-2" onclick="dismissToast('phpToastError')"><i class="fas fa-times"></i></button>
                </div>
                <div class="toast-msg"><?= htmlspecialchars($errMsg) ?></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
</div>

<!-- TOP NAV -->
<nav class="main-nav">
<div class="container">
<div class="d-flex align-items-center justify-content-between flex-wrap py-0">
    <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>index.php" class="nav-brand py-2">
        <div class="logo-box">🚂</div>
        <div>
            IRCTC <span style="color:#ffd700;">Hygiene</span>
            <small>Food Hygiene Rating System</small>
        </div>
    </a>

    <button class="navbar-toggler d-md-none border-0 bg-transparent text-white" type="button" id="navToggle">
        <i class="fas fa-bars fa-lg"></i>
    </button>

    <div class="d-none d-md-flex align-items-center" id="navLinks">
        <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>index.php" class="nav-link-custom"><i class="fas fa-home me-1"></i>Home</a>
        <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>vendors_list.php" class="nav-link-custom"><i class="fas fa-store me-1"></i>Vendors</a>

        <?php if (isLoggedIn()): ?>
            <?php if (isPassenger()): ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>passenger/dashboard.php" class="nav-link-custom"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>passenger/rate.php" class="nav-link-custom"><i class="fas fa-star me-1"></i>Rate Vendor</a>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>passenger/complaint.php" class="nav-link-custom"><i class="fas fa-file-alt me-1"></i>Complaint</a>
            <?php elseif (isAdmin()): ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>admin/dashboard.php" class="nav-link-custom"><i class="fas fa-tachometer-alt me-1"></i>Admin Panel</a>
            <?php elseif (isOfficer()): ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>officer/dashboard.php" class="nav-link-custom"><i class="fas fa-clipboard-check me-1"></i>Inspections</a>
            <?php
            $pendingCount = $GLOBALS['conn']->query("SELECT COUNT(*) as c FROM complaints WHERE status IN ('submitted','under_verification')")->fetch_assoc()['c'];
            ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>officer/complaints.php" class="nav-link-custom" style="position:relative;">
                <i class="fas fa-file-alt me-1"></i>Complaints
                <?php if ($pendingCount > 0): ?>
                <span style="position:absolute;top:14px;right:4px;background:#e74c3c;color:#fff;border-radius:50%;width:16px;height:16px;font-size:0.6rem;display:flex;align-items:center;justify-content:center;font-weight:700;"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>
            <?php elseif (isVendor()): ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>vendor/dashboard.php" class="nav-link-custom"><i class="fas fa-chart-bar me-1"></i>My Performance</a>
            <?php endif; ?>

            <div class="dropdown">
                <button class="btn-nav-login dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-user me-1"></i><?= htmlspecialchars($_SESSION['name']) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text text-muted small"><?= ucfirst($_SESSION['role']) ?></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= defined('BASEPATH') ? BASEPATH : '' ?>logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i>Logout</a></li>
                </ul>
            </div>
        <?php else: ?>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>login.php" class="btn-nav-login"><i class="fas fa-sign-in-alt me-1"></i>Login</a>
            <a href="<?= defined('BASEPATH') ? BASEPATH : '' ?>register.php" class="nav-link-custom" style="border:1.5px solid rgba(255,255,255,0.4);border-radius:8px;padding:8px 16px !important;margin:12px 0 12px 6px;">Register</a>
        <?php endif; ?>
    </div>
</div>
</div>
</nav>

<script>
document.getElementById('navToggle')?.addEventListener('click',()=>{
    const nl = document.getElementById('navLinks');
    nl.classList.toggle('d-none');
    nl.classList.toggle('d-flex');
    nl.style.flexDirection = 'column';
    nl.style.width = '100%';
    nl.style.paddingBottom = '10px';
});

function dismissToast(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('hiding');
    setTimeout(() => el.remove(), 300);
}

// Auto-dismiss PHP toasts after 5s
setTimeout(() => {
    ['phpToastSuccess','phpToastError'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.add('hiding'); setTimeout(() => el.remove(), 300); }
    });
}, 5000);
</script>
