<?php require "config/csrf.php"; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Create Account | Agricultural Analytics System</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/register.css" rel="stylesheet">
</head>

<body>

    <div class="container-fluid vh-100">
        <div class="row h-100">

            <!-- LEFT BRANDING PANEL -->
            <div class="col-lg-6 d-none d-lg-flex branding-panel">
                <div class="branding-content text-center">
                    <h2 class="fw-bold">Agricultural Crop Monitoring</h2>
                    <h4 class="text-warning">and Farm Input Distribution</h4>
                    <p class="mt-3">Analytics System</p>
                </div>
            </div>

            <!-- RIGHT REGISTER FORM -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center bg-light">

                <div class="card register-card shadow-lg p-4">

                    <h3 class="text-center text-success mb-3">Create Account</h3>

                    <form method="POST" action="auth/register.php" id="registerForm">

                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken(); ?>">

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Email Address</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control" required>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Phone Number</label>
                                <input type="text" name="phone" class="form-control">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3 position-relative">
                                <label>Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                                <small id="strengthText"></small>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Confirm Password</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label>User Role</label>
                                <select name="role" class="form-select" required>
                                    <option value="">Select Role</option>
                                    <option value="staff">Staff</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label>Barangay</label>
                                <input type="text" name="barangay" class="form-control">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="g-recaptcha" data-sitekey="YOUR_SITE_KEY"></div>
                        </div>

                        <button class="btn register-btn w-100">Register</button>

                        <div class="text-center mt-3">
                            Already have an account?
                            <a href="index.php" class="text-success fw-bold">Login here</a>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>

    <script src="https://www.google.com/recaptcha/api.js"></script>

    <script>
        const password = document.getElementById("password");
        const strengthText = document.getElementById("strengthText");

        password.addEventListener("input", function () {
            const val = password.value;
            let strength = "Weak";
            let color = "red";

            if (val.length > 8 && /[A-Z]/.test(val) && /[0-9]/.test(val)) {
                strength = "Medium";
                color = "orange";
            }
            if (val.length > 10 && /[!@#$%^&*]/.test(val)) {
                strength = "Strong";
                color = "green";
            }

            strengthText.innerText = "Password Strength: " + strength;
            strengthText.style.color = color;
        });
    </script>

</body>

</html>