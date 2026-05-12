<?php

$host = "localhost";
$user = "root";
$pass = "";
$db = "agriculture_db";

$backup_file = "../backups/backup_" . date("Y-m-d_H-i-s") . ".sql";

$command = "mysqldump --user=$user --password=$pass --host=$host $db > $backup_file";

system($command);

echo "Backup Successful!";