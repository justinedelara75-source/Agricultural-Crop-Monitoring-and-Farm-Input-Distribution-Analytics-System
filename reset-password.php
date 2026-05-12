<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require "config/database.php";
require "config/csrf.php";

if (!isset($_SESSION['otp_verified']) || !isset($_SESSION['reset_email'])) {
    header("Location: forgot_password.php");
    exit();
}

if (isset($_POST['reset'])) {
    $password = trim($_POST['password']);
    $email = $_SESSION['reset_email'];

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?, otp_code = NULL, otp_expiry = NULL
        WHERE email = ?
    ");
    $stmt->execute([$hashedPassword, $email]);

    // auto login after reset
    $getUser = $pdo->prepare("SELECT id, role FROM users WHERE email = ?");
    $getUser->execute([$email]);
    $user = $getUser->fetch();

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    // clear reset session
    unset($_SESSION['otp_verified']);
    unset($_SESSION['reset_email']);

    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        <?php include "auth-style.css"; ?>
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <img src="assets/img/logo.png" width="80">
        <p>DEPARTMENT OF AGRICULTURE</p>
        <span>PALAYAN CITY</span>
    </div>

    <h1>Reset Password</h1>
    <p class="subtitle">Enter your new password</p>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

        <div class="input-box">
            <i class="fa fa-lock"></i>
            <input type="password" name="password" placeholder="New Password" required>
        </div>

        <button class="login-btn" name="reset">
            <span>RESET PASSWORD</span>
            <div class="arrow"><i class="fa fa-check"></i></div>
        </button>
    </form>
</div>
</body>
</html>