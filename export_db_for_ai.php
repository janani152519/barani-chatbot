<?php
/**
 * Export all gri_db tables and records to clean JSON for AI training & vector indexing.
 */
require_once __DIR__ . '/config/database.php';

$pdo = Database::getConnection();
$export = [];

// 1. Knowledge Base (SCADA Manual)
$stmt = $pdo->query("SELECT * FROM knowledge_base");
$export['knowledge_base'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Alarm Mappings
$stmt = $pdo->query("SELECT * FROM alarm_mappings");
$export['alarm_mappings'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Parameter Limits
$stmt = $pdo->query("SELECT * FROM parameter_limits");
$export['parameter_limits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Recipes
$stmt = $pdo->query("SELECT id, name, product_no, plc_record_name, fast_app_position, fast_app_speed, first_pressing_position, first_pressing_pressure, first_pressing_speed, curing_time_sec FROM recipes WHERE name IS NOT NULL AND name != ''");
$export['recipes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Down Time
$stmt = $pdo->query("SELECT * FROM down_time");
$export['down_time'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. Work Orders
$stmt = $pdo->query("SELECT * FROM work_orders");
$export['work_orders'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Critical Spares
$stmt = $pdo->query("SELECT * FROM critical_spares");
$export['critical_spares'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Tool Master
$stmt = $pdo->query("SELECT * FROM toolmaster LIMIT 50");
$export['toolmaster'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 9. Users
$stmt = $pdo->query("SELECT id, username, email, role, is_active FROM users");
$export['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 10. Runlog Stats
$stmt = $pdo->query("SELECT COUNT(*) as total_runs, MAX(`Total Cycle Time`) as max_cycle, AVG(`Total Cycle Time`) as avg_cycle FROM runlog");
$export['runlog_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);

// 11. Bumping Log & Recipes
try {
    $export['bumping_recipes'] = $pdo->query("SELECT * FROM bumping_recipes")->fetchAll(PDO::FETCH_ASSOC);
    $export['bumping_log'] = $pdo->query("SELECT * FROM bumping_log LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 12. App Settings & Reports
try {
    $export['app_settings'] = $pdo->query("SELECT * FROM app_settings")->fetchAll(PDO::FETCH_ASSOC);
    $export['reports'] = $pdo->query("SELECT * FROM reports LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 13. IO Labels (144 PLC Digital/Binary Signals)
try {
    $export['io_labels'] = $pdo->query("SELECT * FROM io_labels")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 14. Recipe Logs & Tool Allocations
try {
    $export['recipe_log'] = $pdo->query("SELECT * FROM recipe_log ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $export['recipe_tool_allocations'] = $pdo->query("SELECT * FROM recipe_tool_allocations")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 15. Permissions & Translations summary
try {
    $export['permissions'] = $pdo->query("SELECT DISTINCT role, screen_name, can_view, can_edit FROM permissions LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
    $export['translations_summary'] = $pdo->query("SELECT language_code, COUNT(*) as count FROM translations GROUP BY language_code")->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

// 16. Full Database Schema Metadata (all 52 tables)
try {
    $tablesStmt = $pdo->query("
        SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH 
        FROM information_schema.tables 
        WHERE table_schema = 'gri_db'
        ORDER BY TABLE_NAME
    ");
    $export['all_tables'] = $tablesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (\Throwable $e) {}

if (!is_dir(__DIR__ . '/ai_index')) {
    mkdir(__DIR__ . '/ai_index', 0777, true);
}

file_put_contents(__DIR__ . '/ai_index/gri_db_export.json', json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "Successfully exported " . count($export) . " data domains from gri_db to ai_index/gri_db_export.json\n";
