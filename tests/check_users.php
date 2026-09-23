<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();
$stmt = $pdo->query("SELECT id, username, email, password_hash, is_active FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
