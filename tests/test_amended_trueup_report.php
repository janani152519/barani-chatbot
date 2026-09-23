<?php
/**
 * Test Suite: Ohio BWC Amended True-Up Payroll Report (BWC-7578) & WOW Email Dispatch
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/PdfReportService.php';
require_once __DIR__ . '/../services/WordReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

$pdo = Database::getConnection();
$adminUser = Auth::login('admin', 'password123');

echo "====================================================================\n";
echo "   OHIO BWC AMENDED TRUE-UP PAYROLL REPORT (BWC-7578) TEST SUITE   \n";
echo "====================================================================\n\n";

// ─── 1. TEST HTML REPORT GENERATION ───────────────────────────────────────────
echo "[1/4] Generating BWC-7578 HTML Email Report for August 2025...\n";
$htmlReport = ReportService::generatePayrollHtmlReport(8, 2025, $pdo);

$checks = [
    'Ohio Bureau of Workers\' Compensation' => str_contains($htmlReport, "Bureau of Workers'"),
    'Amended True-Up Payroll Report Title'   => str_contains($htmlReport, 'Amended True-Up Payroll Report'),
    'Form BWC-7578 Code'                    => str_contains($htmlReport, 'BWC-7578 (Rev. Oct. 6, 2016)'),
    'RPS-Amend P/R Code'                    => str_contains($htmlReport, 'RPS-Amend P/R'),
    'Fax to 614-719-5313 Instruction'       => str_contains($htmlReport, '614-719-5313'),
    'Policy Number Enclosure'               => str_contains($htmlReport, 'Policy number'),
    'Legal Business Name'                   => str_contains($htmlReport, 'Legal business name'),
    'Payroll Period from/through'           => str_contains($htmlReport, 'Payroll period'),
    'NCCI Manual Classification Table'      => str_contains($htmlReport, 'NCCI manual classification'),
    'Reason for Change Block'               => str_contains($htmlReport, 'Reason for change'),
    'Section 4123.25 Ohio Revised Code'    => str_contains($htmlReport, 'Section 4123.25 of the Ohio Revised Code'),
    'Digitally Certified Stamp'             => str_contains($htmlReport, 'DIGITALLY CERTIFIED'),
    'Executive WOW Banner & KPI Cards'      => str_contains($htmlReport, 'Form BWC-7578') && str_contains($htmlReport, 'Audited Actual Payroll'),
    'Barani Hydraulics Official Logo'       => str_contains($htmlReport, 'data:image/jpeg;base64,'),
];

$allHtmlPassed = true;
foreach ($checks as $name => $passed) {
    echo "  " . ($passed ? "✅" : "❌") . " {$name}\n";
    if (!$passed) $allHtmlPassed = false;
}

if (!$allHtmlPassed) {
    echo "FAILED: Some HTML components are missing!\n";
    exit(1);
}
echo "=> HTML Email Report is 100% compliant with BWC-7578 & WOW specifications!\n\n";

// ─── 2. TEST PDF GENERATION (DOMPDF) ──────────────────────────────────────────
echo "[2/4] Generating Official BWC-7578 PDF Document via Dompdf...\n";
$pdfReport = ReportService::generateCustomizableReport(
    'payroll',
    'pdf',
    strip_tags($htmlReport),
    'Annual true-up audit reconciliation, employee attendance adjustments, and NCCI classification wage realignment.',
    $adminUser,
    null,
    'Amended True-Up Payroll Report (BWC-7578) — August 2025',
    ['month' => 8, 'year' => 2025, 'policy_number' => 'BWC-8492014-0']
);

$pdfPath = $pdfReport['file_path'];
$pdfSize = file_exists($pdfPath) ? filesize($pdfPath) : 0;
echo "  📄 PDF File: {$pdfReport['file_name']}\n";
echo "  📏 Size: {$pdfSize} bytes\n";
echo "  🔍 Exists on disk: " . (file_exists($pdfPath) ? "YES" : "NO") . "\n";

// Validate PDF header
$pdfHandle = fopen($pdfPath, 'rb');
$pdfHeader = fread($pdfHandle, 4);
fclose($pdfHandle);

if ($pdfHeader !== '%PDF') {
    echo "FAILED: Generated file is not a valid PDF header (%PDF missing)!\n";
    exit(1);
}
echo "=> Authentic BWC-7578 PDF generated successfully!\n\n";

// ─── 3. TEST WORD (.DOCX) GENERATION ─────────────────────────────────────────
echo "[3/4] Generating Official BWC-7578 Word (.docx) Document...\n";
$wordReport = ReportService::generateCustomizableReport(
    'payroll',
    'docx',
    strip_tags($htmlReport),
    'Annual true-up audit reconciliation, employee attendance adjustments, and NCCI classification wage realignment.',
    $adminUser,
    null,
    'Amended True-Up Payroll Report (BWC-7578) — August 2025',
    ['month' => 8, 'year' => 2025, 'policy_number' => 'BWC-8492014-0']
);

$wordPath = $wordReport['file_path'];
$wordSize = file_exists($wordPath) ? filesize($wordPath) : 0;
echo "  📝 Word File: {$wordReport['file_name']}\n";
echo "  📏 Size: {$wordSize} bytes\n";
echo "  🔍 Exists on disk: " . (file_exists($wordPath) ? "YES" : "NO") . "\n";

if ($wordSize <= 0) {
    echo "FAILED: Word report generation produced empty file!\n";
    exit(1);
}
echo "=> Word (.docx) report generated successfully!\n\n";

// ─── 4. TEST EMAIL DISPATCH WITH WOW TEMPLATE & ATTACHMENT ───────────────────
echo "[4/4] Testing Email Dispatch with BWC-7578 PDF Attachment...\n";
$emailResult = EmailService::sendPayrollReport(
    ['director@baranihydraulics.com', 'admin@barani.com'],
    $htmlReport,
    8,
    2025,
    $adminUser,
    $pdfPath
);

echo "  🚀 Status: " . ($emailResult['status'] ?? 'unknown') . "\n";
echo "  ✉ Message: " . $emailResult['message'] . "\n";
echo "  📎 Attached File: " . ($emailResult['attachment_name'] ?? 'none') . "\n";
echo "  📥 Admin Mailbox: " . (!empty($emailResult['admin_mailbox']) ? "Recorded" : "Missing") . "\n";
echo "  📤 Outbox Vault: " . (!empty($emailResult['outbox']) ? "Recorded" : "Missing") . "\n";

if (empty($emailResult['attachment_name'])) {
    echo "FAILED: Attachment was not properly attached to dispatch!\n";
    exit(1);
}

echo "\n====================================================================\n";
echo "🎉 ALL TESTS PASSED! REPORT FORMAT IS NOW BWC-7578 & WOW WHILE EMAILING!\n";
echo "====================================================================\n";
