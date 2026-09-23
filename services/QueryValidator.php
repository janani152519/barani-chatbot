<?php
/**
 * Query Validation & Security Engine
 */

require_once __DIR__ . '/FieldRegistry.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/logger.php';

class QueryValidator
{
    /**
     * Forbidden DDL/DML SQL keywords.
     */
    private static array $forbiddenKeywords = [
        'DELETE', 'UPDATE', 'INSERT', 'DROP', 'ALTER', 'TRUNCATE', 'RENAME',
        'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'UNION', 'SHUTDOWN'
    ];

    /**
     * Validate query plan for security compliance before execution.
     */
    public static function validate(array $plan, array $user): void
    {
        $table = $plan['table'] ?? '';
        $fields = $plan['fields'] ?? [];
        $agg = $plan['aggregation'] ?? null;

        // 1. Table Allowlist Check
        if (!FieldRegistry::isTableAllowed($table)) {
            Logger::audit($user['id'], 'security_violation', "Query attempt on non-allowlisted table '{$table}'");
            Response::error("Invalid or prohibited database table requested.", 'security_error', 400);
        }

        // 2. Fields Allowlist Check
        foreach ($fields as $field) {
            if ($field !== '*' && strtolower($field) !== 'department_name' && !FieldRegistry::isColumnAllowed($table, $field)) {
                Logger::audit($user['id'], 'security_violation', "Query attempt on prohibited column '{$field}' on table '{$table}'");
                Response::error("Prohibited or invalid database column requested: '{$field}'.", 'security_error', 400);
            }
        }

        // 3. Aggregation Check
        if (!FieldRegistry::isAggregationAllowed($agg)) {
            Response::error("Unsupported aggregate function requested.", 'security_error', 400);
        }

        // 4. Forbidden Keyword Inspection in Filter Strings
        $filterJson = json_encode($plan['filters'] ?? []);
        foreach (self::$forbiddenKeywords as $keyword) {
            if (preg_match("/\b" . preg_quote($keyword, '/') . "\b/i", $filterJson)) {
                Logger::audit($user['id'], 'sql_injection_attempt', "Forbidden SQL keyword '{$keyword}' detected in query plan");
                Response::error("Forbidden SQL command or keyword detected.", 'security_violation', 400);
            }
        }
    }
}
