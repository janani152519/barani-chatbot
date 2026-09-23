<?php
require_once __DIR__ . '/../core/permissions.php';

$employeeUser = ['id' => 2, 'role' => 'employee', 'employee_id' => 1, 'department_id' => 1];
$hrUser = ['id' => 3, 'role' => 'hr', 'employee_id' => 2, 'department_id' => 1];
$financeUser = ['id' => 5, 'role' => 'finance', 'employee_id' => 4, 'department_id' => 2];

echo "--- Testing Table Access ---\n";
echo "Employee access to 'employees': " . (Permissions::canAccessTable($employeeUser, 'employees') ? 'YES' : 'NO') . "\n";
echo "Employee access to 'audit_logs': " . (Permissions::canAccessTable($employeeUser, 'audit_logs') ? 'YES' : 'NO') . "\n";
echo "HR access to 'audit_logs': " . (Permissions::canAccessTable($hrUser, 'audit_logs') ? 'YES' : 'NO') . "\n";
echo "Finance access to 'payroll': " . (Permissions::canAccessTable($financeUser, 'payroll') ? 'YES' : 'NO') . "\n\n";

echo "--- Testing Field-level Permissions ---\n";
echo "HR access to 'salary' on 'employees': " . (Permissions::canAccessFields($hrUser, 'employees', ['salary']) ? 'YES' : 'NO') . "\n";
echo "Finance access to 'salary' on 'employees': " . (Permissions::canAccessFields($financeUser, 'employees', ['salary']) ? 'YES' : 'NO') . "\n";

echo "--- Testing Row-Level Scope ---\n";
$empScope = Permissions::getRowLevelScope($employeeUser, 'employees');
echo "Employee row scope: " . $empScope['clause'] . "\n";

$hrScope = Permissions::getRowLevelScope($hrUser, 'employees');
echo "HR row scope: " . ($hrScope['clause'] ?: 'GLOBAL VIEW') . "\n";
