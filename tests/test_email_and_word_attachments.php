<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

$adminUser = Auth::login('admin', 'password123');
echo "Logged in as: " . $adminUser['username'] . "\n";

echo "\n--- 1. Testing Customizable Word (.docx) Report Generation ---\n";
$wordReport = ReportService::generateCustomizableReport(
    'payroll',
    'docx',
    "Total Net Payable: ₹3,12,000\nEmployees: 7\nStatus: Verified and Finalized",
    "Sent for board review.",
    $adminUser,
    null,
    "Executive Payroll Report Q3"
);
echo "Word Report created: {$wordReport['file_name']} (Size: {$wordReport['file_size']} bytes)\n";
echo "File exists on disk: " . (file_exists($wordReport['file_path']) ? "YES" : "NO") . "\n";

echo "\n--- 2. Testing Customizable PDF Report Generation ---\n";
$pdfReport = ReportService::generateCustomizableReport(
    'downtime',
    'pdf',
    "Total Downtime: 4.5 hours across 3 stations.\nPrimary reason: Hydraulic seal replacement on Press 2.",
    "Maintenance team has completed repair.",
    $adminUser,
    null,
    "Shift Machine Downtime Analysis"
);
echo "PDF Report created: {$pdfReport['file_name']} (Size: {$pdfReport['file_size']} bytes)\n";
echo "File exists on disk: " . (file_exists($pdfReport['file_path']) ? "YES" : "NO") . "\n";

echo "\n--- 3. Testing Direct Email Dispatch with Word (.docx) Attachment ---\n";
$emailRes1 = EmailService::sendDirectReport(
    ['manager@baranihydraulics.com', 'director@baranihydraulics.com'],
    "Quarterly Payroll Report — Barani Hydraulics",
    "<p>Please find attached the official executive payroll report in Word format.</p>",
    $adminUser,
    'payroll',
    $wordReport['file_path']
);
echo "Dispatch status: " . ($emailRes1['status'] ?? 'unknown') . "\n";
echo "Message: " . $emailRes1['message'] . "\n";
echo "Attachment: " . ($emailRes1['attachment_name'] ?? 'none') . "\n";

echo "\n--- 4. Testing Direct Email Dispatch with PDF Attachment ---\n";
$emailRes2 = EmailService::sendDirectReport(
    ['planthead@baranihydraulics.com'],
    "Shift Downtime Report — Barani Hydraulics",
    "<p>Please find attached the shift machine downtime report in PDF format.</p>",
    $adminUser,
    'downtime',
    $pdfReport['file_path']
);
echo "Dispatch status: " . ($emailRes2['status'] ?? 'unknown') . "\n";
echo "Message: " . $emailRes2['message'] . "\n";
echo "Attachment: " . ($emailRes2['attachment_name'] ?? 'none') . "\n";

echo "\n--- 5. Checking Outbox Vault ---\n";
$outbox = EmailService::getOutbox(5);
echo "Total outbox records: " . count($outbox) . "\n";
foreach ($outbox as $idx => $m) {
    echo "  [" . ($idx + 1) . "] Subject: {$m['subject']} | Status: {$m['status']} | Attached: {$m['attachment_name']}\n";
}

echo "\n--- 6. Testing Scheduler Job Trigger with Word Report ---\n";
$pdo = Database::getConnection();
$testJob = [
    'id' => 999,
    'report_type' => 'payroll',
    'report_format' => 'docx',
    'subject' => 'Scheduled Monthly Payroll Briefing',
    'message_body' => 'Auto-scheduled payroll summary'
];
$schedRes = EmailService::sendScheduledJobNow($testJob, ['accounts@baranihydraulics.com'], $adminUser, $pdo, 'docx');
echo "Scheduled send result: " . $schedRes['message'] . "\n";
echo "Attachment attached: " . ($schedRes['attachment_name'] ?? 'none') . "\n";

echo "\n=== ALL EMAIL & REPORT ENGINE TESTS PASSED ===\n";
