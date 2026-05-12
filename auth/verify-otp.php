<?php
session_start();
require "../config/database.php";
require "../config/csrf.php";

if (!isset($_SESSION['temp_user_id'])) {
    header("Location: ../index.php");
    exit();
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $otp_input = trim($_POST['otp']);
    $user_id = $_SESSION['temp_user_id'];

    $stmt = $pdo->prepare("SELECT otp_code, otp_expiry, role FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if ($user && $otp_input === $user['otp_code'] && strtotime($user['otp_expiry']) > time()) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $user['role'];

        unset($_SESSION['temp_user_id']);

        header("Location: ../dashboard.php");
        exit();

    } else {
        $message = "Invalid or expired OTP.";
    }
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>OTP Verification</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

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

    <div class="card shadow-lg p-4">

        <h4 class="text-center text-success mb-3">OTP Verification</h4>

        <p class="text-center text-muted">
            Enter the 6-digit code sent to your email.
        </p>

        <?php if ($message): ?>
            <div class="alert alert-danger">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST">

            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

            <div class="mb-3">
                <label>Enter OTP</label>
                <input type="text" name="otp" maxlength="6" class="form-control text-center" required>
            </div>

            <button class="btn verify-btn w-100">
                Verify OTP
            </button>

        </form>

        <hr>

        <button class="btn btn-outline-secondary w-100 mt-2" id="resendBtn">
            Resend OTP (<span id="timer">30</span>s)
        </button>

    </div>

    <script>

        let time = 30;
        const timer = document.getElementById("timer");
        const resendBtn = document.getElementById("resendBtn");

        resendBtn.disabled = true;

        const countdown = setInterval(() => {

            time--;
            timer.textContent = time;

            if (time <= 0) {
                clearInterval(countdown);
                resendBtn.disabled = false;
                resendBtn.innerText = "Resend OTP";
            }

        }, 1000);

        resendBtn.onclick = function () {
            window.location.href = "resend-otp.php";
        };

    </script>

</body>

</html>