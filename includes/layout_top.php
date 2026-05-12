<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detect current URI for active link highlighting
$currentUri = $_SERVER['REQUEST_URI'];
$inReports   = strpos($currentUri, '/reports/') !== false;
$isDamage    = strpos($currentUri, 'damage_report.php') !== false;
$isDistrib   = strpos($currentUri, 'distribution_report.php') !== false;
$isHarvest   = strpos($currentUri, 'harvest_report.php') !== false;
$isHistory   = strpos($currentUri, 'report_history.php') !== false;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Agricultural Monitoring System</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        /* ===================================================
           GLOBAL
        =================================================== */
        * { box-sizing: border-box; }

        body {
            background: url('https://www.transparenttextures.com/patterns/green-dust-and-scratches.png'),
                linear-gradient(135deg, #e8f5e9 0%, #f1f8e9 60%, #f9fbe7 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', sans-serif;
            margin: 0;
            padding: 0;
        }

        /* ===================================================
           SIDEBAR — farm illustration background
        =================================================== */
        .sidebar {
            width: 210px;
            height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            background:
                url('../assets/img/side-bar_background.png') bottom center / cover no-repeat,
                linear-gradient(180deg, #1a3d1f 0%, #2d6a31 60%, #3a8a3e 100%);
            padding: 0 10px 20px 10px;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 3px 0 18px rgba(0,0,0,0.30);
        }

        /* Logo */
        .logo-box {
            text-align: center;
            padding: 30px 12px 22px;
            border-bottom: 1px solid rgba(255,255,255,0.15);
            margin-bottom: 8px;
        }

        .logo-box img {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255,255,255,0.75);
            box-shadow: 0 6px 24px rgba(0,0,0,0.40), 0 0 0 2px rgba(255,255,255,0.15);
            margin-bottom: 12px;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .logo-box h4 {
            font-size: 15px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 4px rgba(0,0,0,0.4);
        }

        /* Regular nav links */
        .sidebar a {
            display: flex;
            align-items: center;
            gap: 10px;
            color: rgba(255,255,255,0.88);
            padding: 10px 13px;
            margin: 2px 0;
            border-radius: 10px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 500;
            transition: background 0.2s, color 0.2s, transform 0.2s;
        }

        .sidebar a i {
            width: 17px;
            text-align: center;
            font-size: 13px;
            flex-shrink: 0;
            opacity: 0.85;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.14);
            color: #ffffff;
            transform: translateX(3px);
        }

        /* ⭐ ACTIVE — yellow pill */
        .sidebar a.active-link {
            background: #f9a825 !important;
            color: #1a3d1f !important;
            font-weight: 700;
            box-shadow: 0 3px 12px rgba(249,168,37,0.40);
        }

        .sidebar a.active-link i {
            opacity: 1;
            color: #1a3d1f !important;
        }

        .sidebar a.active-link:hover { transform: none; }

        /* Reports parent (nav-parent) */
        .sidebar .nav-parent {
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: rgba(255,255,255,0.88);
            padding: 10px 13px;
            margin: 2px 0;
            border-radius: 10px;
            background: rgba(255,255,255,0.10);
            font-size: 13.5px;
            font-weight: 600;
            cursor: default;
        }

        .sidebar .nav-parent .chevron {
            font-size: 10px;
            opacity: 0.75;
        }

        /* Sub-menu */
        .sidebar .sub-menu { margin: 1px 0 4px; padding: 0; }

        .sidebar .sub-menu a {
            display: flex;
            align-items: center;
            gap: 9px;
            color: rgba(255,255,255,0.78);
            padding: 9px 13px 9px 28px;
            margin: 1px 0;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: background 0.2s, color 0.2s, border-color 0.2s;
        }

        .sidebar .sub-menu a i { width: 15px; font-size: 12px; }

        .sidebar .sub-menu a:hover {
            background: rgba(255,255,255,0.13);
            color: #fff;
            border-left-color: #f9a825;
            transform: translateX(3px);
        }

        /* Active sub-link */
        .sidebar .sub-menu a.active-link {
            background: #f9a825 !important;
            color: #1a3d1f !important;
            border-left-color: transparent;
            font-weight: 700;
            box-shadow: 0 3px 10px rgba(249,168,37,0.35);
        }

        .sidebar .sub-menu a.active-link i { color: #1a3d1f !important; opacity:1; }

        /* Logout */
        .sidebar a[href*="logout"] { color: rgba(255,160,160,0.88); margin-top: auto; }
        .sidebar a[href*="logout"]:hover { background: rgba(255,100,100,0.18); color:#ffaaaa; }

        /* ===================================================
           MAIN CONTENT
        =================================================== */
        .main {
            margin-left: 210px;
            padding: 28px 32px;
            min-height: 100vh;
        }

        /* ===================================================
           TOPBAR / PAGE HEADER / BADGES
        =================================================== */
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .system-title { font-size: 20px; font-weight: 700; color: #1b5e20; }

        .page-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 26px;
        }

        .page-header img { width: 50px; }

        .page-header h2 { margin: 0; font-weight: 700; color: #1b5e20; }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            background: white;
            padding: 7px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.09);
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 500;
            color: #2e7d32;
            border: 1px solid #e8f5e9;
        }

        .admin-profile img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; }

        /* ===================================================
           REPORT BANNER / BADGE-BOX
        =================================================== */
        .report-banner {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 24px;
            gap: 16px;
        }

        .report-banner h2 { font-size: 22px; font-weight: 700; color: #1b5e20; margin:0 0 4px; }
        .report-banner p  { color: #555; font-size: 14px; margin: 0; }

        .top-badges { display: flex; gap: 10px; align-items: center; }

        .badge-box {
            display: flex;
            align-items: center;
            gap: 7px;
            background: white;
            padding: 8px 14px;
            border-radius: 50px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
        }

        .badge-box i { color: #2e7d32; }

        /* ===================================================
           REPORT FORM CARD
        =================================================== */
        .report-form-card {
            background: white;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
        }

        .report-form-card h3 {
            font-size: 17px;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-form-card > p {
            background: none !important;
            color: #6b7280;
            font-size: 13.5px;
            margin-bottom: 20px;
            padding: 0 !important;
            border-radius: 0 !important;
            display: block !important;
        }

        /* ===================================================
           EXPORT CARDS
        =================================================== */
        .export-options { display: flex; gap: 12px; }

        .export-card {
            flex: 1;
            border: 2px solid #e5e7eb;
            border-radius: 14px;
            padding: 16px 12px;
            cursor: pointer;
            text-align: center;
            font-weight: 600;
            font-size: 14px;
            color: #374151;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }

        .export-card input[type="radio"] { display: none; }

        .export-card:hover { border-color: #81c784; background: #f9fbe7; }

        .export-card.active {
            border-color: #2e7d32;
            background: #f1f8e9;
            color: #1b5e20;
            box-shadow: 0 3px 12px rgba(46,125,50,0.15);
        }

        .export-card .export-label { font-size: 15px; font-weight: 700; }
        .export-card .export-desc  { font-size: 11.5px; color: #6b7280; font-weight: 400; }
        .export-card.active .export-desc { color: #388e3c; }

        /* ===================================================
           ABOUT PANEL (right side on damage_report.php)
        =================================================== */
        .about-panel {
            background: white;
            border-radius: 18px;
            padding: 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            height: fit-content;
            position: sticky;
            top: 28px;
        }

        .about-panel .about-title {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 8px;
        }

        .about-panel .about-title i { color: #2e7d32; }

        .about-panel .about-desc {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 18px;
            line-height: 1.5;
        }

        .about-panel .tips-title {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 14px;
            font-weight: 700;
            color: #1b5e20;
            margin-bottom: 10px;
        }

        .about-panel .tip-item {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            font-size: 12.5px;
            color: #374151;
            margin-bottom: 9px;
            line-height: 1.4;
            background: none !important;
            padding: 0 !important;
            border-radius: 0 !important;
            justify-content: flex-start !important;
        }

        .about-panel .tip-item i { color: #2e7d32; margin-top: 2px; flex-shrink: 0; }

        .about-panel .farm-illustration {
            margin-top: 20px;
            border-radius: 14px;
            overflow: hidden;
            width: 100%;
        }

        .about-panel .farm-illustration svg {
            width: 100%;
            height: auto;
            display: block;
        }

        /* ===================================================
           STAT CARDS
        =================================================== */
        .stat-card {
            border: none;
            border-radius: 18px;
            padding: 20px 22px;
            background: white;
            box-shadow: 0 4px 16px rgba(0,0,0,0.07);
            transition: transform 0.25s, box-shadow 0.25s;
        }

        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 10px 28px rgba(0,0,0,0.11); }
        .stat-card h5 { color: #6b7280; font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
        .stat-card h3 { font-weight: 800; color: #1b5e20; margin: 0; font-size: 26px; }
        .stat-icon { width: 38px; height: 38px; object-fit: contain; margin-bottom: 6px; display: block; }

        /* ===================================================
           PANELS (generic)
        =================================================== */
        .panel {
            background: white;
            border: none;
            border-radius: 20px;
            padding: 26px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.07);
            height: 100%;
        }

        .panel h5 { font-weight: 700; color: #1b5e20; margin-bottom: 16px; font-size: 15px; }

        .panel p {
            background: #f1f8e9;
            padding: 11px 14px;
            border-radius: 10px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }

        /* ===================================================
           TABLE
        =================================================== */
        .table thead { background: #2e7d32; color: white; }
        .table thead th { font-weight: 600; font-size: 13px; border: none; }
        .table td { vertical-align: middle; font-size: 14px; }
        .table tbody tr:hover { background: #f9fbe7; }

        /* ===================================================
           BADGES
        =================================================== */
        .badge { padding: 6px 12px; font-size: 11px; border-radius: 8px; font-weight: 600; }

        /* ===================================================
           CHART
        =================================================== */
        canvas { margin-top: 10px; width: 100% !important; height: 280px !important; }

        /* ===================================================
           STATS BOX
        =================================================== */
        .stats-box {
            background: white;
            border-radius: 18px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.07);
        }

        .stats-box i { font-size: 24px; padding: 16px; border-radius: 50%; color: white; flex-shrink: 0; }

        .green  { background: #2e7d32; }
        .lime   { background: #4caf50; }
        .yellow { background: #f9a825; }
        .olive  { background: #7cb342; }

        /* ===================================================
           FORM CONTROLS
        =================================================== */
        .form-control, .form-select {
            border-radius: 10px;
            padding: 10px 14px;
            border: 1.5px solid #d1d5db;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #4caf50;
            box-shadow: 0 0 0 3px rgba(76,175,80,0.15);
            outline: none;
        }

        .btn-success {
            background: #2e7d32;
            border: none;
            border-radius: 10px;
            padding: 11px 24px;
            font-weight: 600;
            font-size: 14px;
            transition: background 0.2s, transform 0.2s;
        }

        .btn-success:hover { background: #1b5e20; transform: translateY(-1px); }

        .modal-content { border-radius: 18px; border: none; }

        .row { align-items: stretch; }
    </style>
</head>

<body>

    <div class="sidebar">
        <div class="logo-box">
            <img src="../assets/img/lolobron4.png" alt="logo">
            <h4>Agriculture</h4>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'admin'): ?>

            <a href="/agriculture/dashboard.php"
               class="<?= strpos($currentUri, 'dashboard.php') !== false ? 'active-link' : '' ?>">
                <i class="fa fa-home"></i> Dashboard
            </a>

            <?php if ($inReports && !$isHistory): ?>
                <!-- Expanded Reports sub-menu (only when inside a report page) -->
                <div class="nav-parent">
                    <span><i class="fa fa-chart-bar"></i>&nbsp; Reports</span>
                    <span class="chevron"><i class="fa fa-chevron-down"></i></span>
                </div>
                <div class="sub-menu">
                    <a href="/agriculture/reports/damage_report.php"
                       class="<?= $isDamage ? 'active-link' : '' ?>">
                        <i class="fa fa-triangle-exclamation"></i> Damage Report
                    </a>
                    <a href="/agriculture/reports/distribution_report.php"
                       class="<?= $isDistrib ? 'active-link' : '' ?>">
                        <i class="fa fa-truck"></i> Distribution Report
                    </a>
                    
                </div>
            <?php else: ?>
                <!-- Collapsed Reports link (on all other pages) -->
                <a href="/agriculture/reports/damage_report.php">
                    <i class="fa fa-chart-bar"></i> Reports
                </a>
            <?php endif; ?>

            <a href="/agriculture/reports/report_history.php"
               class="<?= $isHistory ? 'active-link' : '' ?>">
                <i class="fa fa-search"></i> Search Reports
            </a>

            <a href="/agriculture/logs/logs.php"
               class="<?= strpos($currentUri, 'logs.php') !== false ? 'active-link' : '' ?>">
                <i class="fa fa-history"></i> Activity Logs
            </a>

        <?php elseif (isset($_SESSION['role']) && ($_SESSION['role'] == 'employee' || $_SESSION['role'] == 'staff')): ?>

            <a href="/agriculture/farmers/farmers.php">
                <i class="fa fa-users"></i> Farmers
            </a>

            <a href="/agriculture/crop_monitoring/crop.php">
                <i class="fa fa-seedling"></i> Crop Monitoring
            </a>

            <a href="/agriculture/farm_inputs/inputs.php">
                <i class="fa fa-tractor"></i> Farm Inputs
            </a>

        <?php endif; ?>

        <a href="/agriculture/auth/logout.php">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <div class="main">