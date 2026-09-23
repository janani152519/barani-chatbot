<?php
/**
 * Audit Logger Service targeting gri_db
 */

require_once __DIR__ . '/../config/database.php';

class Logger
{
    /**
     * Log an action to the audit_logs table and storage log file.
     */
    public static function audit(?int $userId, string $action, string $description = '', ?string $operator = null): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Determine human-readable operator name
        if ($operator === null) {
            if ($userId === 1 || (isset($_SESSION['user']['username']) && $_SESSION['user']['username'] === 'admin')) {
                $operator = 'admin';
            } elseif (!empty($_SESSION['user']['username'])) {
                $operator = $_SESSION['user']['username'];
            } elseif ($userId !== null && $userId > 0) {
                $operator = "User#{$userId}";
            } else {
                $operator = "System";
            }
        }

        // 1. Write to database audit_logs table
        try {
            $pdo = Database::getConnection();
            $colsStmt = $pdo->query("SHOW COLUMNS FROM `audit_logs`");
            $cols = array_map('strtolower', $colsStmt->fetchAll(PDO::FETCH_COLUMN));

            if (in_array('user_id', $cols, true)) {
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (user_id, action, description, ip_address, user_agent)
                    VALUES (:user_id, :action, :description, :ip, :user_agent)
                ");
                $stmt->execute([
                    ':user_id' => $userId,
                    ':action' => $action,
                    ':description' => $description,
                    ':ip' => substr($ip, 0, 45),
                    ':user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'API Client', 0, 255)
                ]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO audit_logs (operator_name, action_type, target, remarks)
                    VALUES (:operator, :action, 'system', :remarks)
                ");
                $stmt->execute([
                    ':operator' => $operator,
                    ':action' => substr($action, 0, 50),
                    ':remarks' => $description // longtext in schema, no truncation
                ]);
            }
        } catch (\Throwable $e) {
            // Log file fallback
        }

        // 2. Write to storage file
        try {
            $logFile = storage_path('logs/audit.log');
            $timestamp = date('Y-m-d H:i:s');
            $entry = "[{$timestamp}] [{$operator}] Action: {$action} | Details: {$description} | IP: {$ip}\n";
            file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Silent fallback
        }
    }

    /**
     * Dedicated audit helper for logging details retrieved by admin/user.
     */
    public static function auditRetrieval(?int $userId, string $detailsSummary, string $action = 'details_retrieved', ?string $operator = 'admin'): void
    {
        $logMessage = "admin logged in and these details were retrieved: {$detailsSummary}";
        self::audit($userId, $action, $logMessage, $operator);
    }
}

