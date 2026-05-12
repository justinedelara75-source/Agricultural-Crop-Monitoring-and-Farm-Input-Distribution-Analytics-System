<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require "config/database.php";
require "config/csrf.php";

if (isset($_POST['verify'])) {
    $otp = trim($_POST['otp']);
    $email = $_SESSION['reset_email'] ?? null;

    if (!$email) {
        $error = "Session expired. Please request OTP again.";
    } else {
        $stmt = $pdo->prepare("
            SELECT * FROM users
            WHERE email = ?
            AND otp_code = ?
            AND otp_expiry > NOW()
        ");
        $stmt->execute([$email, $otp]);
        $user = $stmt->fetch();

        if ($user) {
            $_SESSION['otp_verified'] = true;
            header("Location: reset-password.php");
            exit();
        } else {
            $error = "Invalid or expired OTP.";
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Verify OTP</title>
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

        <h1>OTP Verification</h1>
        <p class="subtitle">Enter the OTP sent to your email</p>

        <?php if (isset($error))
            echo "<p style='color:red; margin-bottom:15px;'>$error</p>"; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

            <div class="input-box">
                <i class="fa fa-key"></i>
                <input type="text" name="otp" placeholder="Enter OTP" required>
            </div>

            <button class="login-btn" name="verify">
                <span>VERIFY OTP</span>
                <div class="arrow"><i class="fa fa-check"></i></div>
            </button>
        </form>
    </div>
</body>

</html>