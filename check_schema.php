<?php
require 'config.php';
require 'app/db.php';
$stmt = pdo()->query("SHOW CREATE TABLE shift_closures");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'] ?? 'Not found';
