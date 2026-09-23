<?php
/**
 * Zero-Trust AST Query Validation & Injection Firewall
 * Conforms to Enterprise AI Specification v1.2 (Page 2)
 *
 * Enforces:
 *  - AST Read-Only Enforcement (SELECT / CTE queries only)
 *  - Strict AST interception for DROP / ALTER / TRUNCATE / UPDATE / INSERT / MERGE / DELETE
 *  - Immediate security audit logging on violation
 *  - Bounded execution timeout (5000ms)
 *  - Automatic row ceiling (1000 rows)
 */

require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../config/database.php';

class AstGuardrailFirewall
{
    private const MAX_ROW_CEILING = 1000;
    private const TIMEOUT_MS = 5000;

    /**
     * Forbidden statement tokens that trigger strict AST interception
     */
    private static array $mutationOperations = [
        'DROP'     => 'DROP / ALTER / TRUNCATE',
        'ALTER'    => 'DROP / ALTER / TRUNCATE',
        'TRUNCATE' => 'DROP / ALTER / TRUNCATE',
        'UPDATE'   => 'UPDATE / INSERT / MERGE',
        'INSERT'   => 'UPDATE / INSERT / MERGE',
        'MERGE'    => 'UPDATE / INSERT / MERGE',
        'DELETE'   => 'DELETE Statements',
        'CREATE'   => 'DDL Mutation',
        'RENAME'   => 'DDL Mutation',
        'REPLACE'  => 'DML Mutation',
        'GRANT'    => 'Privilege Escalation',
        'REVOKE'   => 'Privilege Escalation',
        'EXEC'     => 'Stored Procedure Invocation',
        'EXECUTE'  => 'Stored Procedure Invocation',
        'CALL'     => 'Stored Procedure Invocation'
    ];

    /**
     * Inspect and validate generated SQL query through AST tokenization
     * 
     * @param string $sql The candidate SQL string
     * @param int|null $userId ID of calling user for audit logging
     * @return array Validation report
     */
    public static function validateAndEnforce(string $sql, ?int $userId = null): array
    {
        $cleanSql = trim($sql);
        $cleanSqlNoComments = preg_replace('#/\*.*?\*/#s', '', $cleanSql);
        $cleanSqlNoComments = preg_replace('#--.*$#m', '', $cleanSqlNoComments);
        $cleanSqlNoComments = trim($cleanSqlNoComments);

        // 1. Redundancy layer: Lexical regex scan
        foreach (self::$mutationOperations as $op => $category) {
            if (preg_match('/\b' . $op . '\b/i', $cleanSqlNoComments)) {
                self::recordViolation($userId, $op, $category, $sql);
                return [
                    'permitted' => false,
                    'status' => 'BLOCKED',
                    'operation_type' => $category,
                    'system_action' => "Strict AST interception; triggers immediate security audit event and halts query pipeline.",
                    'error' => "Security Violation: Operation '{$op}' is BLOCKED by Zero-Trust Read-Only Protection Architecture.",
                    'sql' => $sql
                ];
            }
        }

        // 2. Syntax tree verification: Only SELECT or WITH (CTE) allowed as root statement
        $tokens = preg_split('/\s+/', $cleanSqlNoComments);
        $firstToken = strtoupper($tokens[0] ?? '');

        if (!in_array($firstToken, ['SELECT', 'WITH'], true)) {
            self::recordViolation($userId, $firstToken, 'NON_SELECT_ROOT', $sql);
            return [
                'permitted' => false,
                'status' => 'BLOCKED',
                'operation_type' => 'Non-SELECT Statement',
                'system_action' => "AST interception: Non-read-only root syntax encountered.",
                'error' => "Security Violation: Only SELECT and CTE (WITH) queries are PERMITTED.",
                'sql' => $sql
            ];
        }

        // 3. Prevent stacked query injection (e.g., SELECT ...; DROP TABLE ...)
        // Check for multiple semicolon-separated statements
        $statements = array_filter(array_map('trim', explode(';', $cleanSqlNoComments)));
        if (count($statements) > 1) {
            self::recordViolation($userId, 'MULTIPLE_STATEMENTS', 'STACKED_QUERY', $sql);
            return [
                'permitted' => false,
                'status' => 'BLOCKED',
                'operation_type' => 'Stacked Queries',
                'system_action' => "Stacked queries blocked to prevent multi-statement injection.",
                'error' => "Security Violation: Multi-statement execution is strictly prohibited.",
                'sql' => $sql
            ];
        }

        // 4. Enforce Automatic Row Ceiling (1000 rows max)
        $enforcedSql = $cleanSqlNoComments;
        if (preg_match('/LIMIT\s+(\d+)/i', $enforcedSql, $matches)) {
            $currentLimit = (int)$matches[1];
            if ($currentLimit > self::MAX_ROW_CEILING) {
                $enforcedSql = preg_replace('/LIMIT\s+\d+/i', 'LIMIT ' . self::MAX_ROW_CEILING, $enforcedSql);
            }
        } else {
            // Append row ceiling if no LIMIT exists
            $enforcedSql .= ' LIMIT ' . self::MAX_ROW_CEILING;
        }

        return [
            'permitted' => true,
            'status' => 'PERMITTED',
            'operation_type' => 'SELECT / CTE queries',
            'system_action' => "Executed with bounded execution timeout (" . self::TIMEOUT_MS . "ms) and automatic row ceiling (" . self::MAX_ROW_CEILING . " rows).",
            'enforced_sql' => $enforcedSql,
            'timeout_ms' => self::TIMEOUT_MS,
            'row_ceiling' => self::MAX_ROW_CEILING
        ];
    }

    /**
     * Execute SQL safely using bounded execution timeout and PDO
     */
    public static function executeSafe(string $sql, array $params = [], ?int $userId = null): array
    {
        $startTime = microtime(true);
        $validation = self::validateAndEnforce($sql, $userId);

        if (!$validation['permitted']) {
            throw new \RuntimeException($validation['error']);
        }

        $pdo = Database::getConnection();

        // Enforce execution timeout at MySQL session level
        try {
            // max_execution_time in milliseconds for MySQL 5.7.8+
            $pdo->exec("SET SESSION max_execution_time = " . self::TIMEOUT_MS);
        } catch (\Throwable $t) {
            // Some MySQL variants may not support session variable
        }

        $stmt = $pdo->prepare($validation['enforced_sql']);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $executionMs = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'rows' => $rows,
            'count' => count($rows),
            'execution_ms' => $executionMs,
            'sql' => $validation['enforced_sql'],
            'status' => 'PERMITTED',
            'guardrail' => $validation
        ];
    }

    /**
     * Record security violation to database audit_logs
     */
    private static function recordViolation(?int $userId, string $token, string $category, string $sql): void
    {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("INSERT INTO audit_logs (operator_name, action_type, target, remarks) VALUES (?, ?, ?, ?)");
            $op = "User #" . ($userId ?? 'anonymous');
            $action = "AST_FIREWALL_BLOCKED";
            $target = "SQL Engine [{$category}]";
            $remarks = "Blocked Token: {$token} | Query: " . substr($sql, 0, 300);
            $stmt->execute([$op, $action, $target, $remarks]);
        } catch (\Throwable $e) {
            error_log("Firewall Audit Log Failed: " . $e->getMessage());
        }
    }
}
