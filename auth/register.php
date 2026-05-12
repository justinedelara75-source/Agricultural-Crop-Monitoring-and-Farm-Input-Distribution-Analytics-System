<?php
require "../config/database.php";
require "../config/csrf.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!verifyCSRFToken($_POST['csrf_token'])) {
        die("Invalid CSRF token");
    }

    // Personal info
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $middle_name = htmlspecialchars(trim($_POST['middle_name']));

    // Account info
    $username = trim($_POST['username']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);

    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];

    $role = $_POST['role'];
    $barangay = trim($_POST['barangay']);

    // Validation
    if (!$email) {
        die("Invalid email format");
    }

    if (strlen($password) < 8) {
        die("Password must be at least 8 characters");
    }

    if ($password !== $confirm) {
        die("Passwords do not match");
    }

    if (
        !preg_match("/[A-Z]/", $password) ||
        !preg_match("/[0-9]/", $password)
    ) {
        die("Password must contain at least 1 uppercase letter and 1 number.");
    }

    // Check email
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$email]);

    if ($check->rowCount() > 0) {
        die("Email already registered.");
    }

    // Check username
    $checkUser = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $checkUser->execute([$username]);

    if ($checkUser->rowCount() > 0) {
        die("Username already taken.");
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

    // ✅ AUTO VERIFIED NA (email_verified = 1)
    $stmt = $pdo->prepare("INSERT INTO users 
        (last_name, first_name, middle_name, username, email, phone, password, role, barangay, email_verified) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");

    $stmt->execute([
        $last_name,
        $first_name,
        $middle_name,
        $username,
        $email,
        $phone,
        $hashedPassword,
        $role,
        $barangay
    ]);

    echo "Registration successful!";
}
?>