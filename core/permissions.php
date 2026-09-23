<?php
/**
 * Role-Based Access Control (RBAC) & Field-Level Authorization Engine
 */

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/../services/FieldRegistry.php';

class Permissions
{
    /**
     * Allowed tables matrix per role.
     */
    private static array $roleTableMatrix = [
        'admin' => ['*'],
        'hr' => ['employees', 'departments', 'attendance', 'leave_records', 'tasks', 'users'],
        'manager' => ['employees', 'departments', 'attendance', 'leave_records', 'tasks', 'users', 'recipes', 'work_orders'],
        'finance' => ['employees', 'departments', 'payroll', 'reports', 'users'],
        'operator' => ['recipes', 'work_orders', 'down_time', 'parameter_limits', 'tool_master', 'users'],
        'worker' => ['recipes', 'work_orders', 'users'],
        'employee' => ['employees', 'attendance', 'leave_records', 'payroll', 'tasks', 'users']
    ];

    /**
     * Check if a user role can access a specific table.
     */
    public static function canAccessTable(array $user, string $table): bool
    {
        $role = strtolower($user['role'] ?? 'employee');

        if ($role === 'admin') {
            return true;
        }

        $allowedTables = self::$roleTableMatrix[$role] ?? [];
        if (in_array('*', $allowedTables, true)) {
            return true;
        }

        return in_array(strtolower($table), $allowedTables, true) || FieldRegistry::isTableAllowed($table);
    }

    /**
     * Check if a user can access specific requested fields in a table.
     */
    public static function canAccessFields(array $user, string $table, array $fields): bool
    {
        $role = strtolower($user['role'] ?? 'employee');
        if ($role === 'admin' || $role === 'finance') {
            return true;
        }

        foreach ($fields as $f) {
            if (in_array(strtolower($f), ['salary', 'basic_salary', 'net_salary'], true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Enforce row-level data scoping filter based on role.
     */
    public static function getRowLevelScope(array $user, string $table): array
    {
        return ['clause' => '', 'params' => []];
    }

    /**
     * Authorize a query plan before execution.
     */
    public static function authorizeQueryPlan(array $user, array $plan): void
    {
        $table = $plan['table'] ?? '';
        $fields = $plan['fields'] ?? [];

        if (empty($table)) {
            Response::error('Invalid query target.', 'permission_error', 400);
        }

        if (!self::canAccessTable($user, $table)) {
            Logger::audit($user['id'] ?? null, 'permission_denied', "User role '{$user['role']}' attempted to access unauthorized table '{$table}'");
            Response::unauthorized("You are not authorized to access information from table '{$table}'.");
        }

        if (!self::canAccessFields($user, $table, $fields)) {
            Logger::audit($user['id'] ?? null, 'permission_denied', "User role '{$user['role']}' attempted to access restricted fields on '{$table}'");
            Response::unauthorized("You are not authorized to access restricted fields on '{$table}'.");
        }
    }
}
