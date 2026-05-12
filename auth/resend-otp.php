<?php
session_start();
require "../config/database.php";
require "../config/mailer.php";

if (!isset($_SESSION['temp_user_id'])) {
header("Location: ../index.php");
exit();
}

$user_id = $_SESSION['temp_user_id'];

$stmt = $pdo->prepare("SELECT email FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$otp = rand(100000,999999);
$expiry = date("Y-m-d H:i:s", strtotime("+5 minutes"));

$pdo->prepare("UPDATE users SET otp_code=?, otp_expiry=? WHERE id=?")
->execute([$otp,$expiry,$user_id]);

sendMail($user['email'],"Your New OTP Code","Your OTP code is: $otp");

header("Location: verify-otp.php");
exit();
?>