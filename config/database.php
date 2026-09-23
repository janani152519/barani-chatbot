<?php
/**
 * Database Connection Configurator using PDO
 */

require_once __DIR__ . '/../core/helpers.php';

class Database
{
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO database connection instance.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host = env('DB_HOST', '127.0.0.1');
            $port = env('DB_PORT', '3306');
            $dbname = env('DB_DATABASE', 'gri_db');
            $username = env('DB_USERNAME', 'root');
            $password = env('DB_PASSWORD', '');

            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '+05:30'"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                // Log actual exception internally, throw generic exception to prevent credential leakage
                error_log("Database Connection Error: " . $e->getMessage());
                throw new Exception("Database connection failed. Please check server logs.", 500);
            }
        }

        return self::$instance;
    }
}
