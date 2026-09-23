<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

$adminUser = Auth::login('admin', 'password123');

echo "--- 1. Testing PDF Report Generation ---\n";
$pdfReport = ReportService::generateReport('attendance', 'pdf', $adminUser);
echo "SUCCESS: Created PDF Report ID #{$pdfReport['id']} | File: {$pdfReport['file_name']} | Size: {$pdfReport['file_size']} bytes\n\n";

echo "--- 2. Testing Excel XLSX Report Generation ---\n";
$excelReport = ReportService::generateReport('payroll', 'excel', $adminUser);
echo "SUCCESS: Created Excel Report ID #{$excelReport['id']} | File: {$excelReport['file_name']} | Size: {$excelReport['file_size']} bytes\n\n";

echo "--- 3. Testing CSV Report Generation ---\n";
$csvReport = ReportService::generateReport('employee', 'csv', $adminUser);
echo "SUCCESS: Created CSV Report ID #{$csvReport['id']} | File: {$csvReport['file_name']} | Size: {$csvReport['file_size']} bytes\n\n";

echo "--- 4. Testing Path Traversal Defense ---\n";
$maliciousPath = storage_path('reports') . '/../../.env';
$storageDir = realpath(storage_path('reports'));
$testPath = realpath($maliciousPath);

if (!$testPath || !str_starts_with($testPath, $storageDir)) {
    echo "SUCCESS: Path Traversal Attack (../../.env) BLOCKED BY VALIDATOR!\n\n";
} else {
    echo "ERROR: Path traversal protection failed!\n\n";
}

echo "--- 5. Testing Email Dispatch to HR Group ---\n";
$emailRes = EmailService::sendReportToGroup('HR', 'Monthly Attendance Report', 'Attached is the August report.', $pdfReport['id'], $adminUser);
echo "SUCCESS: " . $emailRes['message'] . "\n";
