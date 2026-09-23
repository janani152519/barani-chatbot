<?php
/**
 * Dynamic Safe Parameterized Database Query Execution Service for gri_db
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/QueryValidator.php';
require_once __DIR__ . '/FieldRegistry.php';

class QueryService
{
    /**
     * Build parameterized SQL and execute query using PDO.
     */
    public static function executePlan(array $plan, array $user): array
    {
        // 1. Authorize plan permissions
        Permissions::authorizeQueryPlan($user, $plan);

        // 2. Validate plan security allowlists
        QueryValidator::validate($plan, $user);

        $pdo = Database::getConnection();
        $table = $plan['table'];
        $fields = $plan['fields'] ?? ['*'];
        $filters = $plan['filters'] ?? [];
        $agg = $plan['aggregation'] ?? null;
        $join = $plan['join'] ?? null;
        $limit = max(1, min((int)($plan['limit'] ?? 20), 100));

        // Construct SELECT fields
        $selectCols = [];
        if ($agg === 'count') {
            $selectCols[] = "COUNT(*) as aggregate_count";
        } elseif ($agg === 'sum' && !empty($fields[0]) && $fields[0] !== '*') {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $fields[0]);
            $selectCols[] = "SUM(t.`{$col}`) as aggregate_sum";
        } elseif ($agg === 'avg' && !empty($fields[0]) && $fields[0] !== '*') {
            $col = preg_replace('/[^a-zA-Z0-9_]/', '', $fields[0]);
            $selectCols[] = "AVG(t.`{$col}`) as aggregate_avg";
        } else {
            if (empty($fields) || in_array('*', $fields, true)) {
                $selectCols[] = "t.*";
            } else {
                foreach ($fields as $f) {
                    $cleanF = preg_replace('/[^a-zA-Z0-9_]/', '', $f);
                    if ($cleanF === 'department_name' && $join === 'departments') {
                        $selectCols[] = "d.name as department_name";
                    } elseif ($cleanF) {
                        $selectCols[] = "t.`{$cleanF}`";
                    }
                }
            }
        }

        if (empty($selectCols)) {
            $selectCols[] = "t.*";
        }

        $sql = "SELECT " . implode(', ', $selectCols) . " FROM `{$table}` t";
        $params = [];

        // Handle JOINs if departments table exists
        if ($join === 'departments' && FieldRegistry::isTableAllowed('departments')) {
            $sql .= " LEFT JOIN departments d ON t.department_id = d.id";
        }

        $whereClauses = [];

        // Apply RBAC Row-Level Scope if applicable
        $rbacScope = Permissions::getRowLevelScope($user, $table);
        if (!empty($rbacScope['clause'])) {
            $whereClauses[] = "(" . $rbacScope['clause'] . ")";
            $params = array_merge($params, $rbacScope['params']);
        }

        // Apply Filters dynamically
        if (!empty($filters['name'])) {
            $nameVal = trim($filters['name']);
            if (FieldRegistry::isColumnAllowed($table, 'first_name')) {
                $whereClauses[] = "(t.first_name LIKE :fname OR t.last_name LIKE :lname)";
                $params[':fname'] = "%{$nameVal}%";
                $params[':lname'] = "%{$nameVal}%";
            } elseif (FieldRegistry::isColumnAllowed($table, 'username')) {
                $whereClauses[] = "t.username LIKE :uname";
                $params[':uname'] = "%{$nameVal}%";
            }
        }

        if (!empty($filters['username'])) {
            $whereClauses[] = "t.username = :filter_username";
            $params[':filter_username'] = $filters['username'];
        }

        if (!empty($filters['first_name'])) {
            $whereClauses[] = "t.first_name = :filter_fname";
            $params[':filter_fname'] = $filters['first_name'];
        }

        if (!empty($filters['status'])) {
            $whereClauses[] = "t.status = :filter_status";
            $params[':filter_status'] = $filters['status'];
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        $sql .= " LIMIT {$limit}";

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Query execution error: " . $e->getMessage() . " | SQL: " . $sql);
            return [];
        }
    }
}
