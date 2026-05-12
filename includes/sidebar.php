<?php session_start(); ?>

<div class="sidebar">
    <div class="logo-box">
        <img src="../assets/img/logo.png" alt="logo">
        <h4>Agriculture</h4>
    </div>

    <?php if ($_SESSION['role'] == 'admin'): ?>

        <a href="../dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
        <a href="reports.php"><i class="fa fa-chart-bar"></i> Reports</a>
        <a href="report_history.php"><i class="fa fa-search"></i> Search Reports</a>
        <a href="../logs/logs.php"><i class="fa fa-history"></i> Activity Logs</a>

    <?php elseif ($_SESSION['role'] == 'employee'): ?>

        <a href="../farmers/farmers.php"><i class="fa fa-users"></i> Farmers</a>
        <a href="../crop_monitoring/crops.php"><i class="fa fa-seedling"></i> Crop Monitoring</a>
        <a href="../farm_inputs/inputs.php"><i class="fa fa-tractor"></i> Farm Inputs</a>

    <?php endif; ?>

    <a href="../auth/logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
</div>