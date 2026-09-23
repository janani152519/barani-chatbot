<?php
require_once __DIR__ . '/../services/WordReportService.php';

$p = [
    'title' => 'Official Payroll & Salary Report',
    'report_type' => 'PAYROLL',
    'date' => date('d F Y'),
    'time' => date('H:i'),
    'content' => "EXECUTIVE SUMMARY\nTotal Employees: 7\nTotal Net Salary: ₹2,45,000\nEmployees Paid: 7/7\nAll salary disbursements verified and approved.",
    'notes' => 'Authorized for direct transmission to Directors and Finance Department.',
    'records' => [
        ['employee_code' => 'EMP001', 'name' => 'Janani Prakash', 'department' => 'Engineering', 'net_salary' => '₹45,000', 'status' => 'PAID'],
        ['employee_code' => 'EMP002', 'name' => 'Imran K', 'department' => 'Operations', 'net_salary' => '₹38,000', 'status' => 'PAID']
    ]
];

$outputPath = __DIR__ . '/../storage/reports/test_payroll.docx';
$result = WordReportService::generate($p, $outputPath);

echo "DOCX Generation Result: " . $result . PHP_EOL;
echo "File Exists: " . (file_exists($result) ? "YES (" . filesize($result) . " bytes)" : "NO") . PHP_EOL;
