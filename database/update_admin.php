<?php
require_once __DIR__ . '/../config/database.php';

$pdo = Database::getConnection();
$hash = password_hash('admin123', PASSWORD_BCRYPT);

$stmt = $pdo->prepare("UPDATE users SET email = 'admin@barani.com', password_hash = :hash WHERE username = 'admin'");
$stmt->execute([':hash' => $hash]);

echo "Admin password updated to 'admin123' and email to 'admin@barani.com'\n";
