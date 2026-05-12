<?php
require "../config/database.php";
require "../config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        die("Invalid credentials");
    }

    if ($user['lock_until'] && strtotime($user['lock_until']) > time()) {
        die("Account temporarily locked. Try again later.");
    }

    if (password_verify($password, $user['password'])) {

        if (!$user['email_verified']) {
            die("Please verify your email before logging in.");
        }

        $pdo->prepare("UPDATE users SET failed_attempts=0, lock_until=NULL WHERE id=?")
            ->execute([$user['id']]);

        // ✅ DIRECT LOGIN (OTP REMOVED)
        session_start();
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];

        require_once "../logs/activity_logger.php";
        logActivity($pdo, $user['id'], "User logged in.");

        // ✅ FIX: Redirect based on role
        switch ($user['role']) {
            case 'admin':
                header("Location: ../dashboard.php");
                break;
            case 'staff':
                header("Location: ../farmers/farmers.php");
                break;
            default:
                // Unknown role — redirect to login with error for safety
                session_destroy();
                header("Location: login.php?error=unauthorized");
                break;
        }
        exit();

    } else {

        $failed = $user['failed_attempts'] + 1;

        if ($failed >= 5) {
            $lockTime = date("Y-m-d H:i:s", strtotime("+10 minutes"));
            $pdo->prepare("UPDATE users SET failed_attempts=?, lock_until=? WHERE id=?")
                ->execute([$failed, $lockTime, $user['id']]);
        } else {
            $pdo->prepare("UPDATE users SET failed_attempts=? WHERE id=?")
                ->execute([$failed, $user['id']]);
        }

        die("Invalid credentials");
    }

}
?>