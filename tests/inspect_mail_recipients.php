<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

echo "--- USERS ---\n";
$stmt = $pdo->query("SELECT id, username, email, role FROM users");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

echo "--- MAIL RECIPIENTS ---\n";
try {
    $stmt = $pdo->query("SELECT * FROM mail_recipients");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo "mail_recipients error: " . $e->getMessage() . "\n";
}
