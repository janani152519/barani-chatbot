<?php
/**
 * Report Generation Endpoint API
 * GET / POST /api/report.php
 * Supports standard reports and rich customizable PDF & Word (.docx) reports
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../services/ReportService.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 1. Authenticate user
$user = Auth::requireAuth();

$input = Validator::getJsonInput();
$type   = strtolower(trim($input['report_type'] ?? $input['type'] ?? $_GET['type'] ?? 'attendance'));
$format = strtolower(trim($input['format'] ?? $_GET['format'] ?? 'pdf'));
if ($format === 'word' || $format === 'doc') $format = 'docx';
if (!in_array($format, ['pdf', 'docx', 'excel', 'csv', 'xlsx'], true)) $format = 'pdf';

$subject = trim($input['subject'] ?? $_GET['subject'] ?? '');
$content = trim($input['report_body'] ?? $input['content'] ?? $input['message'] ?? '');
$notes   = trim($input['notes'] ?? '');

try {
    // 2. If format is docx or customized content/notes/subject provided, use generateCustomizableReport
    if ($format === 'docx' || $format === 'pdf') {
        $customReport = ReportService::generateCustomizableReport(
            $type,
            $format,
            $content,
            $notes,
            $user,
            null,
            $subject ?: null,
            $input
        );

        $downloadUrl = "/api/download.php?file=" . urlencode($customReport['file_name']);

        Logger::auditRetrieval(
            $user['id'],
            "Generated {$type} report in {$format} format ({$customReport['file_name']}) with verified factory telemetry records",
            'report_generated',
            $user['username'] ?? 'admin'
        );

        Response::success([
            'success'         => true,
            'report'          => $customReport,
            'attachment_url'  => $downloadUrl,
            'download_url'    => $downloadUrl,
            'attachment_name' => $customReport['file_name'],
            'file_name'       => $customReport['file_name'],
            'message'         => "Report generated successfully ({$format})"
        ], 'report_generated', 200);
        exit;
    }

    // Standard report fallback (excel/csv)
    $reportMeta = ReportService::generateReport($type, $format, $user);

    Logger::auditRetrieval(
        $user['id'],
        "Generated {$type} ledger in {$format} format ({$reportMeta['file_name']})",
        'report_generated',
        $user['username'] ?? 'admin'
    );

    Response::success([
        'success'         => true,
        'report'          => $reportMeta,
        'download_url'    => "/api/download.php?file=" . urlencode($reportMeta['file_name']),
        'attachment_url'  => "/api/download.php?file=" . urlencode($reportMeta['file_name']),
        'attachment_name' => $reportMeta['file_name'],
        'message'         => 'Report generated successfully'
    ], 'report_generated', 200);

} catch (\Throwable $e) {
    error_log("Report API Error: " . $e->getMessage());
    Response::serverError("Failed to generate requested report: " . $e->getMessage());
}
