<?php
require_once '../includes/config.php';
if (!isLoggedIn() || !isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid    = (int)$_POST['vendor_id'];
    $status = sanitize($_POST['status']);
    $allowed = ['active','under_review','suspended'];
    if ($vid && in_array($status, $allowed)) {
        $conn->query("UPDATE vendors SET status='$status' WHERE id=$vid");
        $_SESSION['msg_success'] = 'Vendor status updated!';
    }
}
redirect('dashboard.php');
?>
