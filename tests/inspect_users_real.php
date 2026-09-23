<?php
$filepath = 'C:/Users/Janani Prakash/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/C9BD9873D4D1C82A37414FC14D297717E44E8A0E/transfers/2026-37/gri_db.db';
$sqlite = new PDO("sqlite:" . $filepath);

echo "USERS TABLE IN gri_db.db:\n";
print_r($sqlite->query("SELECT * FROM users")->fetchAll(PDO::FETCH_ASSOC));

echo "\nEMPLOYEE TABLE IN gri_db.db:\n";
print_r($sqlite->query("SELECT * FROM Employee")->fetchAll(PDO::FETCH_ASSOC));
