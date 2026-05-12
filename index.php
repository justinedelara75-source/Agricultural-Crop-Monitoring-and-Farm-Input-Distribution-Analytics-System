<?php require "config/csrf.php"; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Department of Agriculture - Login</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, Helvetica, sans-serif;
        }

        body {
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;

            background-image: url("./farm.png");
            background-size: cover;
            background-position: center;
        }

        .login-card {

            width: 650px;
            background: white;

            border-radius: 25px;

            padding: 50px;

            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.2);

            text-align: center;

        }

        .logo {
            font-weight: bold;
            color: #1b5e20;
            letter-spacing: 1px;
        }

        .logo span {
            color: #f9a825;
        }


        h1 {
            margin-top: 20px;
            font-size: 34px;
            color: #1b5e20;
        }

        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }


        .input-box {

            display: flex;
            align-items: center;

            border: 1px solid #ddd;
            border-radius: 40px;

            padding: 12px 20px;

            margin-bottom: 18px;

        }

        .input-box i {
            color: #f9a825;
            margin-right: 10px;
        }

        .input-box input {

            border: none;
            outline: none;
            width: 100%;
            font-size: 15px;

        }


        .options {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .options a {
            text-decoration: none;
            color: #1b5e20;
        }

        .login-btn {

            display: flex;
            align-items: center;
            justify-content: space-between;

            width: 100%;
            height: 55px;

            background: linear-gradient(90deg, #1b5e20, #2e7d32);

            border-radius: 40px;

            padding-left: 30px;
            padding-right: 5px;

            color: white;
            font-size: 18px;

            border: none;

            cursor: pointer;

        }

        .login-btn span {
            margin-right: 10px;
        }

        .arrow {

            background: #f9a825;

            width: 50px;
            height: 50px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-left: 10px;

        }


        .divider {

            margin: 25px 0;

            display: flex;
            align-items: center;

            color: #777;
        }

        .divider::before,
        .divider::after {

            content: "";
            flex: 1;
            height: 1px;
            background: #ddd;

        }

        .divider span {
            margin: 0 10px;
        }


        .social {

            display: flex;
            gap: 15px;

        }

        .social button {

            flex: 1;

            border: 1px solid #ddd;

            padding: 10px;

            border-radius: 10px;

            background: white;

            cursor: pointer;

        }

        .footer {

            margin-top: 25px;

            font-size: 13px;

            color: #777;

        }

        .logo img {
            width: 80px;
            margin-bottom: 10px;
        }

        .logo p {
            font-weight: bold;
            color: #1b5e20;
            letter-spacing: 1px;
        }

        .logo span {
            color: #f9a825;
            font-weight: bold;
        }

        .register-link {
            margin-top: 20px;
            font-size: 15px;
            color: #555;
        }

        .register-link a {
            color: #1b5e20;
            font-weight: bold;
            text-decoration: none;
        }

        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="login-card">

        <div class="logo">
            <img src="assets/img/logo.png" alt="Logo">
            <p>DEPARTMENT OF AGRICULTURE</p>
            <span>PALAYAN CITY</span>
        </div>

        <h1>Welcome Back!</h1>
        <p class="subtitle">Login to access the Analytics System</p>

        <form action="auth/login.php" method="POST">

            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

            <div class="input-box">
                <i class="fa fa-user"></i>
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <div class="input-box">
                <i class="fa fa-lock"></i>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="options">

                <label>
                    <input type="checkbox"> Remember Me
                </label>

                <a href="forgot_password.php">Forgot Password?</a>

            </div>

            <button class="login-btn">

                <span>LOGIN</span>

                <div class="arrow">
                    <i class="fa fa-arrow-right"></i>
                </div>

            </button>

        </form>

        <div class="divider">
            <span>or continue with</span>
        </div>

        <div class="social">

            <button>
                <i class="fa-brands fa-google"></i> Google
            </button>

            <button>
                <i class="fa-brands fa-microsoft"></i> Microsoft
            </button>

        </div>
        <div class="register-link">
            Don't have an account? <a href="register.php">Create Account</a>
        </div>

        <div class="footer">
            © 2025 Department of Agriculture - Palayan City
        </div>

    </div>

</body>

</html>