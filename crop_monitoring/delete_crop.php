<?php
require "../config/database.php";

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: crop.php");
    exit;
}

$id = $_GET['id'];

/* 🔍 CHECK IF EXISTS */
$check = $pdo->prepare("SELECT id FROM crop_monitoring WHERE id = ?");
$check->execute([$id]);

if (!$check->fetch()) {
    // walang record
    header("Location: crop.php");
    exit;
}

/* 🗑️ DELETE */
$stmt = $pdo->prepare("DELETE FROM crop_monitoring WHERE id = ?");
$stmt->execute([$id]);

header("Location: crop.php");
exit;