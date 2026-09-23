<?php
$filepath = 'C:/Users/Janani Prakash/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/C9BD9873D4D1C82A37414FC14D297717E44E8A0E/transfers/2026-37/gri_db.db';

$sqlite = new PDO("sqlite:" . $filepath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$tablesStmt = $sqlite->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
$tables = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);

echo "=========================================\n";
echo "TABLES IN USER'S REAL DATABASE (gri_db.db)\n";
echo "=========================================\n\n";

foreach ($tables as $t) {
    $tableName = $t['name'];
    $countStmt = $sqlite->query("SELECT COUNT(*) as cnt FROM \"{$tableName}\"");
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
    echo "Table: {$tableName} (Rows: {$count})\n";
    echo "Schema:\n{$t['sql']}\n";
    
    // Sample rows
    $sampleStmt = $sqlite->query("SELECT * FROM \"{$tableName}\" LIMIT 3");
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample data:\n";
    print_r($samples);
    echo "-----------------------------------------\n\n";
}
