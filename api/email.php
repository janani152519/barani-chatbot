<?php
/**
 * Universal Email Report Endpoint API — with customizable PDF and Word (.docx) attachments and Outbox API
 * GET  /api/email.php              → Get sent/outbox emails
 * POST /api/email.php              → Send customized report email with PDF or Word attachment
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../config/database.php';

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
$pdo  = Database::getConnection();

// ─── GET: Fetch Outbox & Admin Mailbox ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $outbox = EmailService::getOutbox(30);
    $adminMailbox = EmailService::getAdminMailbox(30);
    Response::success([
        'outbox' => $outbox,
        'admin_mailbox' => $adminMailbox,
        'count' => count($outbox),
        'admin_count' => count($adminMailbox)
    ], 'mail_data', 200);
    exit;
}

// ─── POST: Dispatch Email with PDF/Word Attachment ──────────────────────────
$input = Validator::getJsonInput();

// Handle recipients: array or comma-separated string
$recipients = [];
if (!empty($input['recipients'])) {
    if (is_array($input['recipients'])) {
        $recipients = $input['recipients'];
    } else {
        $recipients = array_map('trim', explode(',', (string)$input['recipients']));
    }
} elseif (!empty($input['recipient_email'])) {
    $recipients = [trim($input['recipient_email'])];
} elseif (!empty($input['email'])) {
    $recipients = [trim($input['email'])];
}

// Check if user specifically requested admin
$sendToAdmin = !empty($input['send_to_admin']) || !empty($input['admin_copy']);
if ($sendToAdmin && !in_array('admin@barani.com', $recipients, true)) {
    $recipients[] = 'admin@barani.com';
}
if (empty($recipients)) {
    $recipients = ['admin@barani.com'];
}

$reportType = strtolower(trim($input['report_type'] ?? 'general'));
$format     = strtolower(trim($input['format'] ?? 'pdf'));
if ($format === 'word' || $format === 'doc') $format = 'docx';
if (!in_array($format, ['pdf', 'docx'], true)) $format = 'pdf';

$cleanDate = date('F Y');
$cover = EmailService::getFormalCoverLetter($reportType, null, $input['notes'] ?? null, $user);

$subject = trim($input['subject'] ?? '');
if (empty($subject) || str_contains($subject, '(PDF') || str_contains($subject, 'Official SCADA Intelligence') || str_contains($subject, 'Official Payroll & Salary Report')) {
    $subject = $cover['subject'];
}

$body  = trim($input['message'] ?? $input['report_body'] ?? $input['content'] ?? '');
$notes = trim($input['notes'] ?? '');

// If no custom body provided or default ASCII/table boilerplate, use the clean formal cover letter
$isBoilerplate = empty($body) 
    || str_contains($body, '━━━━━━━━') 
    || str_contains($body, 'OFFICIAL SCADA REPORT') 
    || str_contains($body, 'GRI SCADA MACHINE INTELLIGENCE')
    || str_contains($body, 'OHIO BUREAU OF WORKERS');

if ($isBoilerplate) {
    $body = $cover['body_text'];
}

// 2. Generate customized PDF or Word (.docx) attachment with full calculations/tables
$customReport = ReportService::generateCustomizableReport(
    $reportType,
    $format,
    $body,
    $notes ?: ($input['notes'] ?? ''),
    $user,
    null,
    $subject,
    $input
);

$attachPath = $customReport['file_path'] ?? null;

// 3. Dispatch Email with Attachment (only the formal cover letter in body, full data in attached PDF/Word)
$result = EmailService::sendDirectReport($recipients, $subject, $body, $user, $reportType, $attachPath);

// Attach generated report metadata
$result['report'] = $customReport;

if ($result['success']) {
    Response::success($result, 'email_sent', 200);
} else {
    Response::error($result['message'], 'email_error', 400, $result);
}
