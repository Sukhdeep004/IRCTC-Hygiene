<?php
// ============================================
// IRCTC HYGIENE RATING SYSTEM - CONFIG
// Edit DB settings below before running!
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'irctc_hygiene_db');
define('BASE_URL', 'http://localhost/irctc_hygiene');
define('SITE_NAME', 'IRCTC Hygiene Rating');

// Scoring Weights (from paper)
define('W_CLEANLINESS',  0.30);
define('W_FOOD_QUALITY', 0.25);
define('W_PACKAGING',    0.15);
define('W_STAFF_HYGIENE',0.20);
define('W_TIMELINESS',   0.10);

// Alert Threshold
define('ALERT_THRESHOLD', 2.5);

// Connect
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("
    <div style='font-family:sans-serif;background:#fee;border:2px solid red;padding:30px;margin:30px;border-radius:10px;'>
        <h2>⚠️ Database Connection Failed!</h2>
        <p><strong>Error:</strong> " . $conn->connect_error . "</p>
        <ol>
            <li>Open phpMyAdmin</li>
            <li>Import <strong>database.sql</strong></li>
            <li>Edit DB_USER and DB_PASS in <strong>includes/config.php</strong></li>
        </ol>
    </div>");
}
$conn->set_charset("utf8mb4");

if (session_status() === PHP_SESSION_NONE) session_start();

// Auto-run DB migration for new columns (runs once, safe to keep)
require_once __DIR__ . '/migrate.php';

// ============================================
// HELPER FUNCTIONS
// ============================================

function isLoggedIn() { return isset($_SESSION['user_id']); }
function isAdmin()    { return isset($_SESSION['role']) && $_SESSION['role'] === 'admin'; }
function isOfficer()  { return isset($_SESSION['role']) && $_SESSION['role'] === 'officer'; }
function isVendor()   { return isset($_SESSION['role']) && $_SESSION['role'] === 'vendor'; }
function isPassenger(){ return isset($_SESSION['role']) && $_SESSION['role'] === 'passenger'; }

function redirect($url) { header("Location: $url"); exit(); }

function sanitize($data) {
    global $conn;
    return $conn->real_escape_string(htmlspecialchars(trim($data)));
}

// Weighted Score Formula from Project Paper
function calcHygieneScore($c, $f, $p, $s, $t) {
    return round(
        (W_CLEANLINESS   * $c) +
        (W_FOOD_QUALITY  * $f) +
        (W_PACKAGING     * $p) +
        (W_STAFF_HYGIENE * $s) +
        (W_TIMELINESS    * $t),
        2
    );
}

// Vendor Classification from Paper
function classifyVendor($score) {
    if ($score >= 4.5) return ['label' => 'Excellent',    'class' => 'success',   'icon' => '🏆'];
    if ($score >= 3.5) return ['label' => 'Good',         'class' => 'primary',   'icon' => '👍'];
    if ($score >= 2.5) return ['label' => 'Average',      'class' => 'warning',   'icon' => '⚠️'];
    if ($score >= 1.5) return ['label' => 'Poor',         'class' => 'danger',    'icon' => '👎'];
    return                    ['label' => 'Critical',     'class' => 'dark',      'icon' => '🚨'];
}

// Recalculate vendor overall score and update DB
function recalcVendorScore($vendor_id) {
    global $conn;
    $r = $conn->query("SELECT AVG(final_score) as avg FROM ratings WHERE vendor_id=$vendor_id");
    $avg = $r->fetch_assoc()['avg'] ?? 0;
    $score = round($avg, 2);
    $conn->query("UPDATE vendors SET current_score=$score WHERE id=$vendor_id");

    // Check alert threshold
    if ($score < ALERT_THRESHOLD) {
        $existing = $conn->query("SELECT id FROM alerts WHERE vendor_id=$vendor_id AND alert_type='low_score' AND is_read=0");
        if ($existing->num_rows == 0) {
            $conn->query("INSERT INTO alerts (vendor_id, alert_type, message) VALUES 
                ($vendor_id, 'low_score', 'Vendor score dropped below 2.5 threshold. Inspection recommended.')");
        }
    }
    return $score;
}

// Render stars HTML
function renderStars($score) {
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= floor($score)) $html .= '<span class="star active">★</span>';
        elseif ($i - 0.5 <= $score) $html .= '<span class="star half">★</span>';
        else $html .= '<span class="star">★</span>';
    }
    return '<span class="stars-wrap">' . $html . '</span>';
}

// Time ago
function timeAgo($dt) {
    $t = time() - strtotime($dt);
    if ($t < 60) return 'Just now';
    if ($t < 3600) return floor($t/60) . 'm ago';
    if ($t < 86400) return floor($t/3600) . 'h ago';
    return date('d M Y', strtotime($dt));
}

// Generate complaint code
function genComplaintCode() {
    return 'CMP-' . date('Y') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
}

// Get unread alert count
function getAlertCount() {
    global $conn;
    $r = $conn->query("SELECT COUNT(*) as c FROM alerts WHERE is_read=0");
    return $r->fetch_assoc()['c'];
}

// Get unread message count for a user
function getUnreadMessageCount($user_id) {
    global $conn;
    $uid = (int)$user_id;
    $r = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id=$uid AND is_read=0");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
}

// Log complaint history
function logComplaintHistory($complaint_id, $action_by, $role, $action, $note = '') {
    global $conn;
    $note = $conn->real_escape_string($note);
    $action = $conn->real_escape_string($action);
    $role = $conn->real_escape_string($role);
    $cid_sql = ($complaint_id > 0) ? (int)$complaint_id : 'NULL';
    $conn->query("INSERT INTO complaint_history (complaint_id, action_by, role, action, note)
        VALUES ($cid_sql, $action_by, '$role', '$action', '$note')");
}

// Status label helper
function complaintStatusLabel($status) {
    $map = [
        'submitted'          => ['label'=>'Submitted',            'class'=>'bg-secondary'],
        'under_verification' => ['label'=>'Under Verification',   'class'=>'bg-warning text-dark'],
        'approved'           => ['label'=>'Approved',             'class'=>'bg-info text-dark'],
        'rejected'           => ['label'=>'Rejected',             'class'=>'bg-danger'],
        'more_info_requested'=> ['label'=>'More Info Requested',  'class'=>'bg-warning text-dark'],
        'forwarded_to_admin' => ['label'=>'Forwarded to Admin',   'class'=>'bg-primary'],
        'resolved'           => ['label'=>'Resolved',             'class'=>'bg-success'],
        'closed'             => ['label'=>'Closed',               'class'=>'bg-dark'],
        'withdrawn'          => ['label'=>'Withdrawn',            'class'=>'bg-secondary'],
    ];
    return $map[$status] ?? ['label'=>ucfirst($status), 'class'=>'bg-secondary'];
}
?>
