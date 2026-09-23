<?php
/**
 * Scheduled Emails API
 * GET    /api/scheduled_emails.php        → list all scheduled jobs
 * POST   /api/scheduled_emails.php        → create new scheduled job
 * DELETE /api/scheduled_emails.php?id=N   → delete job
 * PATCH  /api/scheduled_emails.php        → toggle active/send now
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../config/database.php';

date_default_timezone_set('Asia/Kolkata');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, PATCH, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$user = Auth::requireAuth();
$pdo  = Database::getConnection();


// ─── GET: List all scheduled email jobs ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("
        SELECT se.*, u.username as created_by_name
        FROM scheduled_emails se
        LEFT JOIN users u ON se.created_by = u.id
        ORDER BY se.created_at DESC
    ");
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON arrays
    foreach ($jobs as &$j) {
        $j['recipient_emails'] = json_decode($j['recipient_emails'], true) ?? [];
        $j['scheduled_dates']  = json_decode($j['scheduled_dates'],  true) ?? [];
    }

    Response::success(['jobs' => $jobs, 'count' => count($jobs)], 'scheduled_emails_list', 200);
    exit;
}

// ─── POST: Create new scheduled email job ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = Validator::getJsonInput();

    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        Validator::requireFields($input, ['report_type', 'recipient_emails', 'scheduled_dates', 'send_time']);

        $reportType   = trim($input['report_type'] ?? 'payroll');
        $emails       = (array)($input['recipient_emails'] ?? []);
        $dates        = (array)($input['scheduled_dates'] ?? []);
        $sendTime     = trim($input['send_time'] ?? '08:00');
        $body         = trim($input['message_body'] ?? '');
        $scheduleMode = strtolower(trim($input['schedule_mode'] ?? ''));

        // Normalize and validate email addresses
        require_once __DIR__ . '/../services/EmailService.php';
        $cover = EmailService::getFormalCoverLetter($reportType, null, $body, $user);
        $rawSub = trim($input['subject'] ?? '');
        $subject = (!empty($rawSub) && !str_starts_with($rawSub, 'GRI Report:')) ? $rawSub : $cover['subject'];
        $validEmails = [];
        foreach ($emails as $e) {
            $norm = EmailService::normalizeRecipient((string)$e);
            if ($norm) {
                $validEmails[] = $norm['email'];
            }
        }
        if (empty($validEmails)) {
            $validEmails = ['admin@barani.com'];
        } else {
            $validEmails = array_values(array_unique($validEmails));
        }

        // Validate send_time format HH:MM
        if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $sendTime)) {
            $sendTime = '08:00';
        }
        if (strlen($sendTime) === 4) {
            $sendTime = '0' . $sendTime;
        }

        // Handle Everyday / Daily Mode vs Specific Dates
        $isEveryday = ($scheduleMode === 'everyday' || $scheduleMode === 'daily')
                   || in_array('everyday', array_map('strtolower', $dates), true)
                   || in_array('daily', array_map('strtolower', $dates), true)
                   || in_array('*', $dates, true)
                   || empty($dates);

        if ($isEveryday) {
            $validDates = ['everyday'];
        } else {
            $validDates = [];
            foreach ($dates as $d) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($d))) {
                    $validDates[] = trim($d);
                }
            }
            if (empty($validDates)) {
                $validDates = ['everyday'];
                $isEveryday = true;
            }
        }

        $reportFormat = strtolower(trim($input['report_format'] ?? 'pdf'));
        if ($reportFormat === 'word' || $reportFormat === 'doc') $reportFormat = 'docx';
        if (!in_array($reportFormat, ['pdf', 'docx'], true)) $reportFormat = 'pdf';

        $stmt = $pdo->prepare("
            INSERT INTO scheduled_emails
                (report_type, report_format, recipient_emails, scheduled_dates, send_time, subject, message_body, created_by)
            VALUES
                (:rtype, :rformat, :emails, :dates, :stime, :subject, :body, :uid)
        ");
        $stmt->execute([
            ':rtype'   => $reportType,
            ':rformat' => $reportFormat,
            ':emails'  => json_encode($validEmails),
            ':dates'   => json_encode($validDates),
            ':stime'   => $sendTime,
            ':subject' => $subject,
            ':body'    => $body,
            ':uid'     => $user['id'],
        ]);

        $newId = $pdo->lastInsertId();
        Logger::audit($user['id'], 'scheduled_email_created', "Created job #{$newId} for {$reportType} ({$reportFormat})");

        $scheduleDesc = $isEveryday ? "Every day at {$sendTime}" : count($validDates) . " date(s) at {$sendTime}";
        Response::success([
            'id'      => $newId,
            'message' => "✅ Email schedule created! Will send '{$reportType}' ({$reportFormat}) report to " . implode(', ', $validEmails) . " ({$scheduleDesc}).",
        ], 'scheduled_email_created', 201);

    } elseif ($action === 'send_now') {
        // Trigger immediate send
        $jobId = (int)($input['id'] ?? 0);
        if (!$jobId) {
            Response::error('Job ID required.', 'missing_id', 422);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM scheduled_emails WHERE id = :id");
        $stmt->execute([':id' => $jobId]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            Response::error('Job not found.', 'not_found', 404);
            exit;
        }

        require_once __DIR__ . '/../services/ReportService.php';
        require_once __DIR__ . '/../services/EmailService.php';

        $emails = json_decode($job['recipient_emails'], true) ?? [];
        $overrideFormat = $input['format'] ?? null;
        $result = EmailService::sendScheduledJobNow($job, $emails, $user, $pdo, $overrideFormat);

        Response::success($result, 'scheduled_email_sent', 200);

    } elseif ($action === 'check_scheduler' || $action === 'run_due') {
        // Run scheduler check for due jobs
        require_once __DIR__ . '/../services/ReportService.php';
        require_once __DIR__ . '/../services/EmailService.php';

        $today = date('Y-m-d');
        $nowTime = date('H:i');

        $stmt = $pdo->query("
            SELECT * FROM scheduled_emails
            WHERE is_active = 1
            AND (last_sent_at IS NULL OR DATE(last_sent_at) < CURDATE())
            ORDER BY id
        ");
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $firedJobs = [];
        foreach ($jobs as $j) {
            $dates = json_decode($j['scheduled_dates'], true) ?? [];
            $stime = substr($j['send_time'], 0, 5);
            $isEveryday = in_array('everyday', $dates) || in_array('daily', $dates) || in_array('*', $dates);

            // If scheduled for everyday OR today, and send_time has reached
            if (($isEveryday || in_array($today, $dates)) && $stime <= $nowTime) {
                $emails = json_decode($j['recipient_emails'], true) ?? [];
                $res = EmailService::sendScheduledJobNow($j, $emails, $user, $pdo);
                $firedJobs[] = [
                    'job_id'      => $j['id'],
                    'report_type' => $j['report_type'],
                    'format'      => $j['report_format'] ?? 'pdf',
                    'recipients'  => $emails,
                    'result'      => $res['message'] ?? 'Executed'
                ];
            }
        }

        Response::success([
            'checked_at' => date('Y-m-d H:i:s'),
            'total_active_jobs' => count($jobs),
            'jobs_fired_count'  => count($firedJobs),
            'fired_jobs'        => $firedJobs,
            'message'           => count($firedJobs) > 0 
                ? "Scheduler executed " . count($firedJobs) . " due job(s)!" 
                : "Scheduler checked. No jobs due at this minute (" . date('H:i') . ")."
        ], 'scheduler_check_complete', 200);
    }

    exit;
}

// ─── DELETE: Remove scheduled job ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        Response::error('Job ID required', 'missing_id', 422);
        exit;
    }
    $stmt = $pdo->prepare("DELETE FROM scheduled_emails WHERE id = :id");
    $stmt->execute([':id' => $id]);
    Logger::audit($user['id'], 'scheduled_email_deleted', "Deleted job #{$id}");
    Response::success(['message' => "Scheduled email job #{$id} deleted."], 'deleted', 200);
    exit;
}

// ─── PATCH: Toggle active state ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = Validator::getJsonInput();
    $id    = (int)($input['id'] ?? 0);
    $state = (int)($input['is_active'] ?? 1);
    if (!$id) {
        Response::error('Job ID required', 'missing_id', 422);
        exit;
    }
    $stmt = $pdo->prepare("UPDATE scheduled_emails SET is_active = :state WHERE id = :id");
    $stmt->execute([':state' => $state, ':id' => $id]);
    Response::success(['message' => "Job #$id " . ($state ? 'enabled' : 'disabled') . "."], 'toggled', 200);
    exit;
}

Response::error('Method not allowed', 'method_not_allowed', 405);
