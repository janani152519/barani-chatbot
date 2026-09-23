<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

$passHash = password_hash('password123', PASSWORD_BCRYPT);

$users = [
    ['username' => 'janani', 'email' => 'janani@company.com', 'role' => 'employee'],
    ['username' => 'rajesh', 'email' => 'rajesh@company.com', 'role' => 'manager'],
    ['username' => 'priya', 'email' => 'priya@company.com', 'role' => 'hr'],
    ['username' => 'amit', 'email' => 'amit@company.com', 'role' => 'finance'],
];

foreach ($users as $u) {
    $check = $pdo->prepare("SELECT id FROM users WHERE username = :uname OR email = :email");
    $check->execute([':uname' => $u['username'], ':email' => $u['email']]);
    $existingId = $check->fetchColumn();

    if ($existingId) {
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash, role = :role, is_active = 1 WHERE id = :id");
        $stmt->execute([':hash' => $passHash, ':role' => $u['role'], ':id' => $existingId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, is_active) VALUES (:uname, :email, :hash, :role, 1)");
        $stmt->execute([':uname' => $u['username'], ':email' => $u['email'], ':hash' => $passHash, ':role' => $u['role']]);
    }
}

echo "Test users seeded/updated successfully!\n";
