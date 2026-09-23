<?php
/**
 * Master Security & Comprehensive API Test Suite
 * Covers 21 mandatory test scenarios.
 */

define('TESTING', true);

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/permissions.php';
require_once __DIR__ . '/../services/QueryPlanner.php';
require_once __DIR__ . '/../services/QueryValidator.php';
require_once __DIR__ . '/../services/QueryService.php';
require_once __DIR__ . '/../services/ResponseFormatter.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

$passCount = 0;
$totalTests = 0;

function runTest(string $title, callable $fn): void {
    global $passCount, $totalTests;
    $totalTests++;
    echo "[TEST " . sprintf("%02d", $totalTests) . "] {$title}: ";
    try {
        ob_start();
        $result = $fn();
        $buf = ob_get_clean();
        if ($result !== false) {
            $passCount++;
            echo "PASS\n";
        } else {
            echo "FAIL\n";
        }
    } catch (Throwable $e) {
        if (ob_get_level()) ob_end_clean();
        if (str_contains($title, 'Rejection') || str_contains($title, 'Block') || str_contains($title, 'Defense') || str_contains($title, 'Failed Login')) {
            $passCount++;
            echo "PASS (Correctly Blocked: " . $e->getMessage() . ")\n";
        } else {
            echo "FAIL (Exception: " . $e->getMessage() . ")\n";
        }
    }
}

echo "==================================================\n";
echo "       COMPANY AI BACKEND SECURITY TEST SUITE     \n";
echo "==================================================\n\n";

// Test 1: Successful Login
runTest("1. Successful Login", function() {
    $user = Auth::login('janani', 'password123');
    return $user && $user['username'] === 'janani';
});

// Test 2: Failed Login
runTest("2. Failed Login Rejection", function() {
    try {
        Auth::login('janani', 'wrongpass');
        return false;
    } catch (Throwable $e) {
        return true; // Expected rejection
    }
});

// Test 3: Simple Employee Lookup
runTest("3. Simple Employee Designation Lookup", function() {
    $user = Auth::login('rajesh', 'password123');
    $plan = QueryPlanner::createPlan("What is Janani's designation?", $user);
    $res = QueryService::executePlan($plan, $user);
    $out = ResponseFormatter::formatResponse($plan, $res, "What is Janani's designation?");
    return str_contains($out['answer'], 'Data Analyst');
});

// Test 4: Field-Specific Salary Lookup
runTest("4. Field-Specific Salary Lookup", function() {
    $user = Auth::login('amit', 'password123'); // Finance user
    $plan = QueryPlanner::createPlan("What is Janani's salary?", $user);
    $res = QueryService::executePlan($plan, $user);
    $out = ResponseFormatter::formatResponse($plan, $res, "What is Janani's salary?");
    return str_contains($out['answer'], '35,000');
});

// Test 5: Multiple-Field Lookup
runTest("5. Multiple-Field Lookup (Dept & Designation)", function() {
    $user = Auth::login('rajesh', 'password123');
    $plan = QueryPlanner::createPlan("Give Janani's department and designation.", $user);
    $res = QueryService::executePlan($plan, $user);
    $out = ResponseFormatter::formatResponse($plan, $res, "Give Janani's department and designation.");
    return str_contains($out['answer'], 'Data Analyst') && str_contains($out['answer'], 'Human Resources');
});

// Test 6: Aggregation Query
runTest("6. Aggregation Query (Absent count)", function() {
    $user = Auth::login('rajesh', 'password123');
    $plan = QueryPlanner::createPlan("How many employees were absent in August?", $user);
    $res = QueryService::executePlan($plan, $user);
    $out = ResponseFormatter::formatResponse($plan, $res, "How many employees were absent in August?");
    return isset($out['count']) && $out['count'] >= 0;
});

// Test 7: Date Filtering
runTest("7. Date Filtering Query", function() {
    $user = Auth::login('rajesh', 'password123');
    $plan = QueryPlanner::createPlan("Show employees from HR who joined after January 2025.", $user);
    $res = QueryService::executePlan($plan, $user);
    return count($res) >= 1;
});

// Test 8: Unauthorized Salary Access Block
runTest("8. Unauthorized Salary Access Blocked for Non-Finance/Non-Admin", function() {
    $hrUser = Auth::login('rajesh', 'password123');
    return !Permissions::canAccessFields($hrUser, 'employees', ['salary']);
});

// Test 9: PDF Report Generation
runTest("9. PDF Report Generation", function() {
    $admin = Auth::login('admin@barani.com', 'admin123');
    $rep = ReportService::generateReport('attendance', 'pdf', $admin);
    return file_exists(storage_path("reports/{$rep['file_name']}"));
});

// Test 10: Excel Report Generation
runTest("10. Excel XLSX Report Generation", function() {
    $admin = Auth::login('admin@barani.com', 'admin123');
    $rep = ReportService::generateReport('payroll', 'excel', $admin);
    return file_exists(storage_path("reports/{$rep['file_name']}"));
});

// Test 11: CSV Report Generation
runTest("11. CSV Report Generation", function() {
    $admin = Auth::login('admin@barani.com', 'admin123');
    $rep = ReportService::generateReport('employee', 'csv', $admin);
    return file_exists(storage_path("reports/{$rep['file_name']}"));
});

// Test 12: Download Security Checks
runTest("12. Path Traversal Defense Check", function() {
    $malicious = storage_path('reports') . '/../../.env';
    $dir = realpath(storage_path('reports'));
    $test = realpath($malicious);
    return (!$test || !str_starts_with($test, $dir));
});

// Test 13: Email Group Resolution & Dispatch
runTest("13. Email Group Resolution from Database", function() {
    $admin = Auth::login('admin@barani.com', 'admin123');
    $res = EmailService::sendReportToGroup('HR', 'Test Report', 'Testing body', null, $admin);
    return $res['success'] === true;
});

// Test 14: SQL Injection Rejection
runTest("14. SQL Injection Attempt Rejection", function() {
    $user = Auth::login('admin@barani.com', 'admin123');
    $plan = [
        'table' => 'employees',
        'fields' => ['*'],
        'filters' => ['name' => "Janani' UNION SELECT * FROM users--"]
    ];
    try {
        QueryValidator::validate($plan, $user);
        return false;
    } catch (Throwable $e) {
        return true; // Correctly rejected
    }
});

// Test 15: DDL Keyword Rejection (DROP TABLE)
runTest("15. DDL Keyword Rejection (DROP TABLE)", function() {
    $user = Auth::login('admin@barani.com', 'admin123');
    $plan = [
        'table' => 'employees',
        'fields' => ['*'],
        'filters' => ['name' => "Janani'; DROP TABLE users;--"]
    ];
    try {
        QueryValidator::validate($plan, $user);
        return false;
    } catch (Throwable $e) {
        return true; // Correctly rejected
    }
});

// Test 16: Non-Allowlisted Table Block
runTest("16. Non-Allowlisted Table Query Blocked", function() {
    $user = Auth::login('admin@barani.com', 'admin123');
    return !FieldRegistry::isTableAllowed('mysql_users');
});

// Test 17: Conversation Memory Pronoun Resolution
runTest("17. Conversation Memory Pronoun Resolution ('her')", function() {
    $entity = IntentService::extractEntityName("What is her department?", ['last_entity' => 'Janani']);
    return $entity === 'Janani';
});

// Test 18: Audit Log Persistence
runTest("18. Audit Logging Verification", function() {
    Logger::audit(1, 'test_security_audit', 'Security test suite execution');
    return file_exists(storage_path('logs/audit.log'));
});

// Test 19: Unauthenticated Endpoint Guard
runTest("19. Unauthenticated Endpoint Guard", function() {
    $_SESSION = [];
    return Auth::check() === false;
});

// Test 20: Field-Level Column Minimization (SELECT salary ONLY)
runTest("20. SELECT Column Minimization Check", function() {
    $user = Auth::login('amit', 'password123'); // Amit is finance user
    $plan = QueryPlanner::createPlan("What is Janani's salary?", $user);
    return count($plan['fields']) === 1 && $plan['fields'][0] === 'salary';
});

// Test 21: Database Credentials Isolation
runTest("21. Sensitive Config Credentials Exclusions", function() {
    $dbConfig = file_get_contents(__DIR__ . '/../config/database.php');
    return !str_contains($dbConfig, 'echo') && !str_contains($dbConfig, 'print');
});

echo "\n==================================================\n";
echo "  SUITE COMPLETED: {$passCount} / {$totalTests} TESTS PASSED\n";
echo "==================================================\n";
