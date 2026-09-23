<?php
$filepath = 'C:/Users/Janani Prakash/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/C9BD9873D4D1C82A37414FC14D297717E44E8A0E/transfers/2026-37/gri_db.db';

$handle = fopen($filepath, 'r');
$header = fread($handle, 100);
fclose($handle);

echo "Header preview:\n" . bin2hex($header) . "\n";
echo "Header string:\n" . substr($header, 0, 50) . "\n";
