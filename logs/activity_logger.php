<?php
function logActivity($pdo, $userId, $action) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $pdo->prepare("INSERT INTO activity_logs (user_id, action, ip_address)
                   VALUES (?, ?, ?)")
        ->execute([$userId, $action, $ip]);
}
?>