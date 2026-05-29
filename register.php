<?php
require_once 'includes/config.php';
$pageTitle = 'Register';
if (isLoggedIn()) redirect('index.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name']);
    $email    = sanitize($_POST['email']);
    $phone    = sanitize($_POST['phone']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm'];

    if (!$name || !$email || !$password) {
        $error = 'Please fill all required fields.';
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $check = $conn->query("SELECT id FROM users WHERE email='$email'");
        if ($check->num_rows > 0) {
            $error = 'This email is already registered.';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $conn->query("INSERT INTO users (name, email, phone, password, role) VALUES ('$name','$email','$phone','$hashed','passenger')");
            $_SESSION['msg_success'] = 'Registration successful! Please login.';
            redirect('login.php');
        }
    }
}
include 'includes/header.php';
?>
<div class="container py-5">
<div class="mb-3">
    <a href="index.php" class="btn-back"><i class="fas fa-arrow-left me-1"></i>Back to Home</a>
</div>
<div class="row justify-content-center">
<div class="col-md-6 col-lg-5">
<div class="card p-4">
    <div class="text-center mb-4">
        <div style="font-size:2.5rem;">🧑</div>
        <h3 class="fw-700 mt-2">Register as Passenger</h3>
        <p class="text-muted mb-0">Join the IRCTC Hygiene Monitoring Platform</p>
    </div>

    <?php if ($error): ?><div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i><?= $error ?></div><?php endif; ?>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label">Full Name *</label>
            <input type="text" name="name" class="form-control" placeholder="Your full name" value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Email Address *</label>
            <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Mobile Number</label>
            <input type="tel" name="phone" class="form-control" placeholder="10-digit mobile" value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
        </div>
        <div class="mb-3">
            <label class="form-label">Password * <small class="text-muted">(min 6 chars)</small></label>
            <input type="password" name="password" class="form-control" placeholder="Create password" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Confirm Password *</label>
            <input type="password" name="confirm" class="form-control" placeholder="Repeat password" required>
        </div>
        <button class="btn btn-primary w-100 py-2 fw-600"><i class="fas fa-user-plus me-2"></i>Create Account</button>
    </form>
    <div class="text-center mt-3" style="font-size:0.88rem;">
        Already registered? <a href="login.php" class="text-primary fw-600">Login here</a>
    </div>
</div>
</div>
</div>
</div>
<?php include 'includes/footer.php'; ?>
