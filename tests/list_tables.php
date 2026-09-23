<?php
$filepath = 'C:/Users/Janani Prakash/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/C9BD9873D4D1C82A37414FC14D297717E44E8A0E/transfers/2026-37/gri_db.db';
$sqlite = new PDO("sqlite:" . $filepath);

$tablesStmt = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

echo "TABLE LIST IN REAL gri_db.db:\n";
foreach ($tables as $t) {
    $count = $sqlite->query("SELECT COUNT(*) FROM \"{$t}\"")->fetchColumn();
    echo "- {$t} (Rows: {$count})\n";
}
