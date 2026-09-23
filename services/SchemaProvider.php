<?php
/**
 * Dynamic Database Schema Metadata Provider for MySQL gri_db
 */

require_once __DIR__ . '/../config/database.php';

class SchemaProvider
{
    private static ?array $schemaCache = null;

    /**
     * Get complete metadata of all database tables and fields in gri_db.
     */
    public static function getSchemaMetadata(): array
    {
        if (self::$schemaCache !== null) {
            return self::$schemaCache;
        }

        $schema = [];
        try {
            $pdo = Database::getConnection();
            $tablesStmt = $pdo->query("SHOW TABLES");
            $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $t) {
                $colsStmt = $pdo->query("SHOW COLUMNS FROM `{$t}`");
                $cols = $colsStmt->fetchAll(PDO::FETCH_ASSOC);

                $fields = [];
                foreach ($cols as $c) {
                    $fields[$c['Field']] = [
                        'type' => $c['Type'],
                        'null' => $c['Null'],
                        'key' => $c['Key']
                    ];
                }

                $schema[strtolower($t)] = [
                    'description' => "Table {$t} in gri_db database",
                    'fields' => $fields
                ];
            }

            self::$schemaCache = $schema;
        } catch (\Throwable $e) {
            self::$schemaCache = [];
        }

        return self::$schemaCache;
    }
}
