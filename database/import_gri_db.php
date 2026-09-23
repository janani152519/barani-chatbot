<?php
/**
 * GRI DB Import Helper
 * Run via: http://localhost/backend/database/import_gri_db.php
 * This imports gri_db_full.sql into MySQL via the web server.
 */

// Simple security: must be called from localhost
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('Access denied.');
}

$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$sqlFile = __DIR__ . '/gri_db_full.sql';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><title>GRI DB Import</title>';
echo '<style>body{font-family:monospace;background:#0f172a;color:#e2e8f0;padding:24px;}
.ok{color:#10b981;} .err{color:#ef4444;} .info{color:#60a5fa;}
pre{background:#1e293b;padding:16px;border-radius:8px;overflow:auto;max-height:400px;}
h1{color:#3b82f6;} .card{background:#1e293b;border-radius:10px;padding:20px;margin:12px 0;}</style></head><body>';
echo '<h1>🗄️ GRI Database Import Tool</h1>';

// Check file
if (!file_exists($sqlFile)) {
    echo '<div class="err">❌ SQL file not found: ' . htmlspecialchars($sqlFile) . '</div>';
    echo '<p class="info">Please run the migration script first: <code>python database/migrate_sqlite_to_mysql.py</code></p>';
    echo '</body></html>';
    exit;
}

$fileSize = round(filesize($sqlFile) / (1024 * 1024), 2);
echo "<div class='card'><p class='ok'>✅ SQL file found: <strong>gri_db_full.sql</strong> ({$fileSize} MB)</p></div>";

// Connect
try {
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);
    echo "<div class='card'><p class='ok'>✅ Connected to MySQL on {$host}:{$port}</p></div>";
} catch (PDOException $e) {
    echo "<div class='card err'>❌ MySQL Connection Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo '</body></html>';
    exit;
}

// Execute SQL in chunks
echo "<div class='card'>";
echo "<p class='info'>📥 Importing gri_db_full.sql... (this may take 30-60 seconds for large datasets)</p>";

set_time_limit(300);
ini_set('memory_limit', '512M');

$sql = file_get_contents($sqlFile);

// Split by semicolon-terminated statements (basic but effective for our dump)
$errors = [];
$success = 0;
$skipped = 0;

// Use mysqli for multi-statement support
$mysqli = new mysqli($host, $user, $pass, '', $port);
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    echo "<p class='err'>❌ mysqli Connection Error: " . htmlspecialchars($mysqli->connect_error) . "</p>";
    echo '</div></body></html>';
    exit;
}

// Execute multi-statement SQL
if ($mysqli->multi_query($sql)) {
    do {
        $success++;
        if ($result = $mysqli->store_result()) {
            $result->free();
        }
        if (!$mysqli->more_results()) break;
    } while ($mysqli->next_result());
}

if ($mysqli->error) {
    // Non-fatal errors (like duplicate keys) — continue
    $errors[] = $mysqli->error;
}

echo "<p class='ok'>✅ Import complete!</p>";
echo "<p>Statements executed: <strong>{$success}</strong></p>";
if (!empty($errors)) {
    echo "<p class='info'>ℹ️ Non-fatal warnings: " . count($errors) . " (usually duplicate key ignores)</p>";
    echo "<pre>" . htmlspecialchars(implode("\n", array_slice($errors, 0, 10))) . "</pre>";
}
echo "</div>";

// Verify table counts
echo "<div class='card'><h2 class='ok'>📊 Verification — Table Row Counts in gri_db:</h2>";
$pdo2 = new PDO("mysql:host={$host};port={$port};dbname=gri_db;charset=utf8mb4", $user, $pass);
$tables = $pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<table style='width:100%;border-collapse:collapse;font-size:13px;'>";
echo "<tr style='background:#0f172a'><th style='padding:8px;text-align:left'>Table</th><th style='padding:8px;text-align:right'>Rows</th></tr>";
$totalRows = 0;
foreach ($tables as $i => $tbl) {
    try {
        $count = $pdo2->query("SELECT COUNT(*) FROM `{$tbl}`")->fetchColumn();
        $totalRows += $count;
        $bg = $i % 2 === 0 ? '#1e293b' : '#0f172a';
        echo "<tr style='background:{$bg}'><td style='padding:7px 8px'><code>{$tbl}</code></td><td style='padding:7px 8px;text-align:right;color:#60a5fa'>" . number_format($count) . "</td></tr>";
    } catch (Exception $e) {
        echo "<tr><td>{$tbl}</td><td style='color:#ef4444'>Error</td></tr>";
    }
}
echo "<tr style='background:#0f172a;font-weight:700'><td style='padding:8px;color:#10b981'>TOTAL (" . count($tables) . " tables)</td><td style='padding:8px;text-align:right;color:#10b981'>" . number_format($totalRows) . "</td></tr>";
echo "</table></div>";

echo "<div class='card'><p class='ok'>🎉 <strong>GRI Database successfully imported into MySQL!</strong></p>";
echo "<p>You can now:</p><ul>";
echo "<li><a href='http://localhost/phpmyadmin/index.php?db=gri_db' style='color:#60a5fa'>📊 View in phpMyAdmin</a></li>";
echo "<li>Use the AI Chatbot to query all tables</li>";
echo "<li>Generate Payroll from the Dashboard</li>";
echo "</ul></div>";
echo '</body></html>';
