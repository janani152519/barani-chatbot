<?php
require_once __DIR__ . '/../config/database.php';
$pdo = Database::getConnection();

try {
    $cols = $pdo->query("SHOW COLUMNS FROM scheduled_emails LIKE 'report_format'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE scheduled_emails ADD COLUMN report_format VARCHAR(20) DEFAULT 'pdf' AFTER report_type");
        echo "Successfully added report_format column to scheduled_emails.\n";
    } else {
        echo "Column report_format already exists.\n";
    }
} catch (Exception $e) {
    echo "Migration error: " . $e->getMessage() . "\n";
}
