<?php
session_start();
require "../config/database.php";
require "../includes/layout_top.php";

// Fetch logs
$stmt = $pdo->query("
    SELECT activity_logs.*, users.username AS user_name
    FROM activity_logs
    LEFT JOIN users ON activity_logs.user_id = users.id
    ORDER BY activity_logs.created_at ASC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-header">
    <h2><i class="fa fa-history"></i> Activity Logs</h2>
</div>

<div class="panel">

    <table class="table table-bordered table-hover">
        <thead>
            <tr>
                <th>User Name</th>
                <th>Action</th>
                <th>Date & Time</th>
            </tr>
        </thead>

        <tbody>
            <?php if ($logs): ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $log['user_name'] ?? 'Unknown' ?></td>
                        <td><?= $log['action'] ?></td>
                        <td><?= date("F d, Y h:i A", strtotime($log['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3" class="text-center">No logs found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>

</div>

</div> <!-- END main -->
</body>

</html>