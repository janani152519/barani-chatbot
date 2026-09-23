<?php
/**
 * Dynamic Field & Table Allowlist Registry targeting MySQL gri_db
 */

require_once __DIR__ . '/../config/database.php';

class FieldRegistry
{
    private static ?array $cachedTables = null;
    private static array $cachedColumns = [];

    /**
     * Allowed aggregation functions.
     */
    private static array $allowedAggregations = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * Get list of all tables present in MySQL gri_db.
     */
    public static function getAllowedTables(): array
    {
        if (self::$cachedTables !== null) {
            return self::$cachedTables;
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query("SHOW TABLES");
            self::$cachedTables = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            self::$cachedTables = [
                'recipes', 'work_orders', 'parameter_limits', 'users', 'employee', 'logevents0',
                'runlog', 'critical_spares', 'down_time', 'knowledge_base', 'bumping_recipes',
                'tool_master', 'audit_logs', 'employees', 'departments', 'attendance', 'payroll'
            ];
        }

        return self::$cachedTables;
    }

    public static function isTableAllowed(string $table): bool
    {
        return in_array(strtolower(trim($table)), self::getAllowedTables(), true);
    }

    public static function isColumnAllowed(string $table, string $column): bool
    {
        $table = strtolower(trim($table));
        $column = strtolower(trim($column));

        if ($column === '*') {
            return true;
        }

        if (!self::isTableAllowed($table)) {
            return false;
        }

        if (!isset(self::$cachedColumns[$table])) {
            try {
                $pdo = Database::getConnection();
                $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
                self::$cachedColumns[$table] = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));
            } catch (\Throwable $e) {
                self::$cachedColumns[$table] = [];
            }
        }

        return in_array($column, self::$cachedColumns[$table], true);
    }

    public static function isAggregationAllowed(?string $agg): bool
    {
        if ($agg === null) return true;
        return in_array(strtolower($agg), self::$allowedAggregations, true);
    }
}
