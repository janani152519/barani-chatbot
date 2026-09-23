<?php
/**
 * Migration Script: Import SQLite gri_db.db into MySQL gri_db
 */

$sqlitePath = 'C:/Users/Janani Prakash/AppData/Local/Packages/5319275A.WhatsAppDesktop_cv1g1gvanyjgm/LocalState/sessions/C9BD9873D4D1C82A37414FC14D297717E44E8A0E/transfers/2026-37/gri_db.db';

if (!file_exists($sqlitePath)) {
    die("Error: SQLite file not found at {$sqlitePath}\n");
}

$sqlite = new PDO("sqlite:" . $sqlitePath);
$sqlite->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Connect to MySQL server
$mysqlHost = '127.0.0.1';
$mysqlUser = 'root';
$mysqlPass = '';

$mysqlPdo = new PDO("mysql:host={$mysqlHost};charset=utf8mb4", $mysqlUser, $mysqlPass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$mysqlPdo->exec("CREATE DATABASE IF NOT EXISTS `gri_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$mysqlPdo->exec("USE `gri_db`");
$mysqlPdo->exec("SET FOREIGN_KEY_CHECKS = 0");

// Get all tables from SQLite
$tablesStmt = $sqlite->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
$tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

echo "Found " . count($tables) . " tables in SQLite database.\n";

foreach ($tables as $tableName) {
    echo "Processing table: {$tableName} ... ";
    
    // Get SQLite table schema / columns
    $colsStmt = $sqlite->query("PRAGMA table_info(\"{$tableName}\")");
    $columns = $colsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Drop existing table in MySQL
    $mysqlPdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
    
    // Build MySQL CREATE TABLE SQL
    $colDefs = [];
    $pkCols = [];
    
    foreach ($columns as $col) {
        $colName = $col['name'];
        $type = strtoupper($col['type']);
        $notNull = $col['notnull'] ? 'NOT NULL' : 'NULL';
        $dflt = $col['dflt_value'] !== null ? "DEFAULT " . $mysqlPdo->quote($col['dflt_value']) : "";
        $isPk = $col['pk'] >= 1;
        
        // Map SQLite types to MySQL types
        if (str_contains($type, 'INT')) {
            $mysqlType = $isPk && count($columns) > 1 && count(array_filter($columns, fn($c) => $c['pk'] >= 1)) === 1 ? 'INT AUTO_INCREMENT' : 'BIGINT';
        } elseif (str_contains($type, 'REAL') || str_contains($type, 'FLOAT') || str_contains($type, 'DOUBLE') || str_contains($type, 'NUMERIC')) {
            $mysqlType = 'DOUBLE';
        } elseif (str_contains($type, 'DATE') || str_contains($type, 'TIME')) {
            $mysqlType = 'VARCHAR(100)';
        } elseif (str_contains($type, 'BLOB')) {
            $mysqlType = 'LONGBLOB';
        } else {
            $mysqlType = $isPk ? 'VARCHAR(255)' : 'LONGTEXT';
        }
        
        if ($isPk) {
            $pkCols[] = "`{$colName}`";
        }
        
        $colDefs[] = "`{$colName}` {$mysqlType} {$notNull} {$dflt}";
    }
    
    $createSql = "CREATE TABLE `{$tableName}` (\n  " . implode(",\n  ", $colDefs);
    if (!empty($pkCols)) {
        $createSql .= ",\n  PRIMARY KEY (" . implode(", ", $pkCols) . ")";
    }
    $createSql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $mysqlPdo->exec($createSql);
    
    // Copy data from SQLite to MySQL
    $selectStmt = $sqlite->query("SELECT * FROM \"{$tableName}\"");
    $rows = $selectStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($rows)) {
        $colNames = array_keys($rows[0]);
        $escapedColNames = array_map(fn($c) => "`{$c}`", $colNames);
        $placeholders = implode(', ', array_fill(0, count($colNames), '?'));
        
        $insertSql = "INSERT INTO `{$tableName}` (" . implode(', ', $escapedColNames) . ") VALUES ({$placeholders})";
        $insertStmt = $mysqlPdo->prepare($insertSql);
        
        $mysqlPdo->beginTransaction();
        foreach ($rows as $row) {
            $values = array_values($row);
            $insertStmt->execute($values);
        }
        $mysqlPdo->commit();
    }
    
    echo "Done! (" . count($rows) . " rows inserted)\n";
}

// Add auth columns if missing in `users` table
$usersColsStmt = $mysqlPdo->query("SHOW COLUMNS FROM `users`");
$usersCols = $usersColsStmt->fetchAll(PDO::FETCH_COLUMN);

if (!in_array('email', $usersCols)) {
    $mysqlPdo->exec("ALTER TABLE `users` ADD COLUMN `email` VARCHAR(255) NULL AFTER `username`");
}
if (!in_array('is_active', $usersCols)) {
    $mysqlPdo->exec("ALTER TABLE `users` ADD COLUMN `is_active` TINYINT DEFAULT 1");
}
if (!in_array('employee_id', $usersCols)) {
    $mysqlPdo->exec("ALTER TABLE `users` ADD COLUMN `employee_id` INT NULL");
}

// Ensure admin user exists in `users` table for session login
$adminCheck = $mysqlPdo->prepare("SELECT COUNT(*) FROM `users` WHERE `username` = 'admin' OR `email` = 'admin@barani.com'");
$adminCheck->execute();
$hasAdmin = $adminCheck->fetchColumn();

$adminHash = password_hash('admin123', PASSWORD_BCRYPT);

if ($hasAdmin) {
    $updateAdmin = $mysqlPdo->prepare("UPDATE `users` SET `email` = 'admin@barani.com', `password_hash` = :hash, `role` = 'admin', `is_active` = 1 WHERE `username` = 'admin' OR `email` = 'admin@barani.com'");
    $updateAdmin->execute([':hash' => $adminHash]);
} else {
    $sql = "INSERT INTO `users` (`username`, `email`, `password_hash`, `role`, `is_active`) VALUES ('admin', 'admin@barani.com', :hash, 'admin', 1)";
    $stmt = $mysqlPdo->prepare($sql);
    $stmt->execute([':hash' => $adminHash]);
}

$mysqlPdo->exec("SET FOREIGN_KEY_CHECKS = 1");

echo "\n=========================================\n";
echo "SUCCESSFULLY IMPORTED ALL REAL TABLES & DATA TO MySQL gri_db!\n";
echo "=========================================\n";
