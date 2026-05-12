<?php
require "../config/database.php";

// 🔥 RESET ALL STATUS
$pdo->query("
    UPDATE farm_inputs
    SET status = 'Pending'
");

// OPTIONAL: burahin distribution history (kung gusto mo clean)


header("Location: inputs.php");
exit;