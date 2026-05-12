<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require "config/database.php";
require "config/mailer.php";
require "config/csrf.php";

if (isset($_POST['recover'])) {
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $error = "Email not found.";
    } else {
        $otp = strval(rand(100000, 999999));
        $expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

        $update = $pdo->prepare("
            UPDATE users 
            SET otp_code = ?, otp_expiry = ?
            WHERE email = ?
        ");
        $update->execute([$otp, $expiry, $email]);

        sendMail($email, "Password Reset OTP", "
            Your OTP code is: <b>$otp</b><br>
            This code will expire in 5 minutes.
        ");

        $_SESSION['reset_email'] = $email;

        header("Location: verify-reset-otp.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #2E7D32, #A5D6A7);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: sans-serif;
        }

        .card {
            border-radius: 20px;
            width: 400px;
        }

        .verify-btn {
            background: linear-gradient(to right, #FBC02D, #FFA000);
            border: none;
            color: white;
            font-weight: bold;
        }

        .verify-btn:hover {
            opacity: .9;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="logo">
        <img src="assets/img/logo.png" width="80">
        <p>DEPARTMENT OF AGRICULTURE</p>
        <span>PALAYAN CITY</span>
    </div>

    <h1>Forgot Password</h1>
    <p class="subtitle">Enter your email to receive OTP</p>

    <?php if(isset($error)) echo "<p style='color:red; margin-bottom:15px;'>$error</p>"; ?>

    <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

        <div class="input-box">
            <i class="fa fa-envelope"></i>
            <input type="email" name="email" placeholder="Email Address" required>
        </div>

        <button class="login-btn" name="recover">
            <span>SEND OTP</span>
            <div class="arrow"><i class="fa fa-arrow-right"></i></div>
        </button>
    </form>
</div>
</body>
</html>