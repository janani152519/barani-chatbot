<?php
/**
 * DataContextService — Loads full live gri_db snapshot for offline AI context (RAG)
 * Provides compact JSON context of all employees, payroll, attendance, tasks,
 * leave records, departments, and users for intelligent offline answering.
 */

require_once __DIR__ . '/../config/database.php';

class DataContextService
{
    private static ?array $contextCache = null;

    /**
     * Load complete live database snapshot as structured context array.
     */
    public static function getContext(): array
    {
        if (self::$contextCache !== null) {
            return self::$contextCache;
        }

        try {
            $pdo = Database::getConnection();
            $ctx = [];

            // --- Departments ---
            $ctx['departments'] = $pdo->query("
                SELECT id, name, code, description FROM departments ORDER BY id
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Employees with Department Name ---
            $ctx['employees'] = $pdo->query("
                SELECT e.id, e.employee_code, e.first_name, e.last_name,
                       e.email, e.phone, e.designation, e.joining_date,
                       e.salary, e.status, e.manager_id,
                       d.name AS department_name, d.code AS department_code
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                ORDER BY e.id
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Users ---
            $ctx['users'] = $pdo->query("
                SELECT u.id, u.username, u.email, u.role, u.is_active, u.employee_id
                FROM users u ORDER BY u.id
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Attendance ---
            $ctx['attendance'] = $pdo->query("
                SELECT a.id, a.employee_id, a.date, a.status, a.check_in, a.check_out,
                       e.first_name, e.last_name
                FROM attendance a
                LEFT JOIN employees e ON a.employee_id = e.id
                ORDER BY a.date DESC
                LIMIT 200
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Leave Records ---
            $ctx['leave_records'] = $pdo->query("
                SELECT lr.id, lr.employee_id, lr.leave_type, lr.start_date, lr.end_date,
                       lr.days, lr.status, lr.reason,
                       e.first_name, e.last_name
                FROM leave_records lr
                LEFT JOIN employees e ON lr.employee_id = e.id
                ORDER BY lr.start_date DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Payroll ---
            $ctx['payroll'] = $pdo->query("
                SELECT p.id, p.employee_id, p.month, p.year, p.basic_salary,
                       p.allowances, p.deductions, p.net_salary, p.payment_status, p.payment_date,
                       e.first_name, e.last_name
                FROM payroll p
                LEFT JOIN employees e ON p.employee_id = e.id
                ORDER BY p.year DESC, p.month DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Tasks ---
            $ctx['tasks'] = $pdo->query("
                SELECT t.id, t.title, t.description, t.status, t.priority, t.due_date,
                       t.employee_id, t.assigned_by,
                       e.first_name AS assigned_to_first, e.last_name AS assigned_to_last,
                       m.first_name AS assigned_by_first, m.last_name AS assigned_by_last
                FROM tasks t
                LEFT JOIN employees e ON t.employee_id = e.id
                LEFT JOIN employees m ON t.assigned_by = m.id
                ORDER BY t.created_at DESC
            ")->fetchAll(PDO::FETCH_ASSOC);

            // --- Audit Logs (last 20) ---
            $ctx['audit_logs'] = $pdo->query("
                SELECT al.id, al.user_id, al.action, al.description, al.created_at,
                       u.username
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                ORDER BY al.created_at DESC
                LIMIT 20
            ")->fetchAll(PDO::FETCH_ASSOC);

            self::$contextCache = $ctx;
        } catch (\Throwable $e) {
            self::$contextCache = [];
        }

        return self::$contextCache;
    }

    /**
     * Get specific employee's full profile by name (for targeted queries).
     */
    public static function findEmployeeByName(string $name): ?array
    {
        $ctx = self::getContext();
        $nameLower = strtolower(trim($name));

        foreach ($ctx['employees'] ?? [] as $emp) {
            $fullName = strtolower($emp['first_name'] . ' ' . $emp['last_name']);
            $firstName = strtolower($emp['first_name']);
            if ($fullName === $nameLower || $firstName === $nameLower || str_contains($fullName, $nameLower)) {
                $emp['payroll'] = array_values(array_filter($ctx['payroll'] ?? [], fn($p) => $p['employee_id'] == $emp['id']));
                $emp['attendance'] = array_values(array_filter($ctx['attendance'] ?? [], fn($a) => $a['employee_id'] == $emp['id']));
                $emp['leave'] = array_values(array_filter($ctx['leave_records'] ?? [], fn($l) => $l['employee_id'] == $emp['id']));
                $emp['tasks'] = array_values(array_filter($ctx['tasks'] ?? [], fn($t) => $t['employee_id'] == $emp['id']));
                return $emp;
            }
        }

        return null;
    }

    /**
     * Get employees in a specific department.
     */
    public static function getEmployeesByDepartment(string $deptName): array
    {
        $ctx = self::getContext();
        $deptLower = strtolower(trim($deptName));

        return array_values(array_filter($ctx['employees'] ?? [], function($e) use ($deptLower) {
            return str_contains(strtolower($e['department_name'] ?? ''), $deptLower)
                || str_contains(strtolower($e['department_code'] ?? ''), $deptLower);
        }));
    }

    /**
     * Get attendance stats for a given month/year.
     */
    public static function getAttendanceStats(?int $month = null, ?int $year = null): array
    {
        $ctx = self::getContext();
        $records = $ctx['attendance'] ?? [];

        if ($month !== null) {
            $records = array_filter($records, fn($a) => (int)date('n', strtotime($a['date'])) === $month);
        }
        if ($year !== null) {
            $records = array_filter($records, fn($a) => (int)date('Y', strtotime($a['date'])) === $year);
        }

        $stats = ['present' => 0, 'absent' => 0, 'half_day' => 0, 'late' => 0, 'on_leave' => 0, 'total' => 0];
        foreach ($records as $a) {
            $key = $a['status'];
            $stats[$key] = ($stats[$key] ?? 0) + 1;
            $stats['total']++;
        }

        return $stats;
    }

    /**
     * Get payroll for a specific employee, optionally filtered by month/year.
     */
    public static function getPayrollForEmployee(int $employeeId, ?int $month = null, ?int $year = null): array
    {
        $ctx = self::getContext();
        $records = array_filter($ctx['payroll'] ?? [], fn($p) => $p['employee_id'] == $employeeId);

        if ($month !== null) {
            $records = array_filter($records, fn($p) => (int)$p['month'] === $month);
        }
        if ($year !== null) {
            $records = array_filter($records, fn($p) => (int)$p['year'] === $year);
        }

        return array_values($records);
    }

    /**
     * Get all employees list summary.
     */
    public static function getAllEmployeesSummary(): array
    {
        $ctx = self::getContext();
        return $ctx['employees'] ?? [];
    }
}
