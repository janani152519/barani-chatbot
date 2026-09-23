<?php
/**
 * Temp Mail API Endpoint
 * Provides temp mail accounts, live inbox feeds, and test dispatchers.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../services/TempMailService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../services/ReportService.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Authenticate user
$user = Auth::requireAuth();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($method === 'POST' ? (Validator::getJsonInput()['action'] ?? '') : '');

if ($method === 'GET') {
    if ($action === 'inbox') {
        $inbox = TempMailService::getInbox(40);
        $account = TempMailService::getOrCreateAccount();
        Response::success([
            'account'  => $account,
            'inbox'    => $inbox,
            'count'    => count($inbox),
        ], 'temp_inbox', 200);
        exit;
    }

    // Default GET: get or provision active account
    $account = TempMailService::getOrCreateAccount();
    $inbox = TempMailService::getInbox(10);
    Response::success([
        'account'     => $account,
        'inbox_count' => count($inbox),
        'latest'      => $inbox[0] ?? null
    ], 'temp_account', 200);
    exit;
}

if ($method === 'POST') {
    $input = Validator::getJsonInput();
    $action = $input['action'] ?? $_GET['action'] ?? '';

    if ($action === 'new_account') {
        $account = TempMailService::createNewAccount();
        TempMailService::clearInbox();
        Response::success([
            'account' => $account,
            'message' => "🎲 Fresh temp email generated: {$account['email']}"
        ], 'temp_account_created', 200);
        exit;
    }

    if ($action === 'clear_inbox') {
        TempMailService::clearInbox();
        Response::success([
            'message' => "Temp mailbox cleared."
        ], 'temp_inbox_cleared', 200);
        exit;
    }

    if ($action === 'send_test' || $action === 'send_to_temp') {
        $account = TempMailService::getOrCreateAccount();
        $targetEmail = $account['email'];
        $reportType  = $input['report_type'] ?? 'payroll';
        $reportFormat = strtolower($input['format'] ?? 'docx');
        if (!in_array($reportFormat, ['pdf', 'docx'], true)) $reportFormat = 'docx';

        $pdo = Database::getConnection();
        $htmlContent = ReportService::generateReportByType($reportType, $pdo);

        $customReport = ReportService::generateCustomizableReport(
            $reportType,
            $reportFormat,
            strip_tags($htmlContent),
            "Automated test report dispatched to temporary address: {$targetEmail}",
            $user,
            null,
            "Temp Mail Verification: " . ucwords(str_replace('_', ' ', $reportType))
        );

        $subject = "GRI Report ({$reportFormat}) for " . $targetEmail;
        $body = "<p>Greetings! Here is your requested <strong>" . strtoupper($reportType) . "</strong> report generated in <strong>" . strtoupper($reportFormat) . "</strong> format.</p><hr>" . $htmlContent;

        $toList = [
            ['name' => 'Temp Mail Recipient', 'email' => $targetEmail]
        ];

        $res = EmailService::dispatchWithTempCapture($toList, $subject, $body, $user, $customReport['file_path'] ?? null, $account);

        $res['temp_account'] = $account;
        $res['download_url'] = $customReport['download_url'] ?? null;
        $res['file_name']    = $customReport['file_name'] ?? null;

        Response::success($res, 'temp_mail_sent', 200);
        exit;
    }

    Response::error("Unknown action: {$action}", 'invalid_action', 400);
    exit;
}

Response::error('Method not allowed', 'method_not_allowed', 405);
