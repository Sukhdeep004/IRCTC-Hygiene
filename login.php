<?php
require_once 'includes/config.php';
$pageTitle = 'Login';
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':    redirect('admin/dashboard.php'); break;
        case 'officer':  redirect('officer/dashboard.php'); break;
        case 'vendor':   redirect('vendor/dashboard.php'); break;
        default:         redirect('passenger/dashboard.php');
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email']);
    $password = $_POST['password'];
    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $res = $conn->query("SELECT * FROM users WHERE email='$email' AND is_active=1");
        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name']    = $user['name'];
                $_SESSION['email']   = $user['email'];
                $_SESSION['role']    = $user['role'];
                $_SESSION['msg_success'] = 'Welcome back, ' . $user['name'] . '!';
                switch ($user['role']) {
                    case 'admin':   redirect('admin/dashboard.php'); break;
                    case 'officer': redirect('officer/dashboard.php'); break;
                    case 'vendor':
                        $vid = $conn->query("SELECT id FROM vendors WHERE user_id={$user['id']}")->fetch_assoc();
                        $_SESSION['vendor_id'] = $vid ? $vid['id'] : 0;
                        $vStatus = $conn->query("SELECT status FROM vendors WHERE user_id={$user['id']}")->fetch_assoc();
                        if ($vStatus && in_array($vStatus['status'], ['suspended', 'removed'])) {
                            session_destroy();
                            session_start();
                            $_SESSION['msg_error'] = 'Your vendor account has been ' . $vStatus['status'] . '. Contact IRCTC administration.';
                            redirect('login.php');
                        }
                        redirect('vendor/dashboard.php'); break;
                    default: redirect('passenger/dashboard.php');
                }
            } else { $error = 'Incorrect password.'; }
        } else { $error = 'No account found with this email.'; }
    }
}
include 'includes/header.php';
?>
<div class="container py-5">
<div class="mb-3">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
</div>
<div class="row justify-content-center">
<div class="col-md-5 col-lg-4">

<div class="card p-4">
    <div class="text-center mb-4">
        <div style="font-size:2.5rem;">🔐</div>
        <h3 class="fw-700 mt-2">Login</h3>
        <p class="text-muted mb-0">IRCTC Hygiene Rating System</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

    <!-- DEMO CREDENTIALS -->
    <!-- <div class="mb-3 p-3 rounded" style="background:#f0f5ff;font-size:0.78rem;">
        <strong>Demo Accounts (password: <code>password</code>)</strong><br>
        👤 Admin: <code>admin@irctc.com</code><br>
        🔍 Officer: <code>officer1@irctc.com</code><br>
        🏪 Vendor: <code>vendor1@irctc.com</code><br>
        🧑 Passenger: <code>passenger1@gmail.com</code>
    </div> -->

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
                <input type="password" name="password" id="pwd" class="form-control" placeholder="Password" required>
                <button class="btn btn-outline-secondary" type="button" onclick="document.getElementById('pwd').type=document.getElementById('pwd').type==='password'?'text':'password'"><i class="fas fa-eye"></i></button>
            </div>
        </div>
        <button class="btn btn-primary w-100 py-2 fw-600" type="submit"><i class="fas fa-sign-in-alt me-2"></i>Login</button>
    </form>
    <div class="text-center mt-3" style="font-size:0.88rem;">
        Don't have an account? <a href="register.php" class="text-primary fw-600">Register here</a>
    </div>
</div>

</div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
