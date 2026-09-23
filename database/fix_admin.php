<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

$hash = password_hash('admin123', PASSWORD_BCRYPT);
$stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, email = 'admin@barani.com', is_active = 1 WHERE username = 'admin' OR email = 'admin@barani.com'");
$stmt->execute([':hash' => $hash]);

echo "Admin password updated successfully!\n";
