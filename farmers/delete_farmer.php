<?php
require "../config/database.php";

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM farmers WHERE id=?");
$stmt->execute([$id]);

header("Location: farmers.php");