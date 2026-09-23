<?php
/**
 * Cron-compatible Scheduled Email Runner
 * Call via: http://127.0.0.1:8000/api/run_scheduler.php
 * Or automated background daemon: scheduler_daemon.py
 */

// Allow CLI, localhost, or any authenticated/local proxy caller
$isCli   = (php_sapi_name() === 'cli');
$isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', 'localhost']) || empty($_SERVER['REMOTE_ADDR']);

if (!$isCli && !$isLocal) {
    http_response_code(403);
    die(json_encode(['error' => 'Access restricted to localhost or CLI.']));
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

date_default_timezone_set('Asia/Kolkata');

$pdo     = Database::getConnection();
$today   = date('Y-m-d');
$nowTime = date('H:i');


$stmt = $pdo->query("
    SELECT * FROM scheduled_emails
    WHERE is_active = 1
    AND (last_sent_at IS NULL OR DATE(last_sent_at) < CURDATE())
    ORDER BY id
");
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fired = 0;
$firedJobs = [];

foreach ($jobs as $job) {
    $scheduledDates = json_decode($job['scheduled_dates'], true) ?? [];
    $sendTime       = substr($job['send_time'], 0, 5); // HH:MM
    $isEveryday     = in_array('everyday', $scheduledDates) || in_array('daily', $scheduledDates) || in_array('*', $scheduledDates);

    // Check if everyday or today is in the scheduled dates and time has arrived
    if (!$isEveryday && !in_array($today, $scheduledDates)) continue;
    if ($sendTime > $nowTime) continue;

    $emails = json_decode($job['recipient_emails'], true) ?? [];
    $systemUser = ['id' => 0, 'username' => 'scheduler', 'role' => 'System Scheduler'];
    $result = EmailService::sendScheduledJobNow($job, $emails, $systemUser, $pdo);

    if ($result['success']) {
        $fired++;
        $firedJobs[] = [
            'id'          => $job['id'],
            'report_type' => $job['report_type'],
            'format'      => $job['report_format'] ?? 'pdf',
            'recipients'  => $emails,
            'result'      => $result['message'] ?? 'Delivered',
            'attachment'  => $result['attachment_name'] ?? null
        ];
        Logger::audit(0, 'scheduler_sent', "Job #{$job['id']}: {$job['report_type']} → " . implode(', ', $emails));
    }
}

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json') ||
          (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status'            => 'ok',
        'timestamp'         => date('Y-m-d H:i:s'),
        'total_active_jobs' => count($jobs),
        'fired_count'       => $fired,
        'fired_jobs'        => $firedJobs
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Content-Type: text/plain; charset=utf-8');
echo "[" . date('Y-m-d H:i:s') . "] GRI Email Scheduler finished.\n";
echo "Fired: {$fired} / " . count($jobs) . " active jobs checked.\n";
foreach ($firedJobs as $fj) {
    echo "  ✅ Job #{$fj['id']} ({$fj['report_type']}, {$fj['format']}) sent to: " . implode(', ', $fj['recipients']) . "\n";
}
