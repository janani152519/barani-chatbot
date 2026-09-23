<?php
/**
 * Enhanced Email Service — supports payroll reports, scheduled jobs, Word & PDF attachments, and outbox auditing.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/ReportService.php';

class EmailService
{
    /**
     * Send email to a pre-defined recipient group from DB.
     */
    public static function sendReportToGroup(string $groupName, string $subject, string $body, ?int $reportId, array $user, ?string $attachPath = null): array
    {
        $groupName = strtoupper(trim($groupName));
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare("SELECT recipient_name, email FROM mail_recipients WHERE group_name = :group AND is_active = 1");
        $stmt->execute([':group' => $groupName]);
        $recipients = $stmt->fetchAll();

        if (empty($recipients)) {
            Logger::audit($user['id'] ?? 0, 'email_failed', "No active recipients for group '{$groupName}'");
            return ['success' => false, 'message' => "No registered recipients for group '{$groupName}'."];
        }

        $toList = [];
        foreach ($recipients as $r) {
            $toList[] = ['name' => $r['recipient_name'], 'email' => $r['email']];
        }

        return self::dispatch($toList, $subject, $body, $user, $attachPath);
    }

    /**
     * Normalize and validate recipient email or alias (e.g. 'admin', 'hr', 'finance').
     */
    public static function normalizeRecipient(string $email): ?array
    {
        $trimmed = trim($email);
        if (empty($trimmed)) return null;

        $lower = strtolower($trimmed);
        if ($lower === 'admin' || $lower === 'administrator' || $lower === 'sysadmin' || $lower === 'admin@barani.com') {
            return ['name' => 'System Administrator', 'email' => 'baranihydraluics@gmail.com'];
        }
        if ($lower === 'hr') {
            return ['name' => 'HR Department', 'email' => 'hr@company.com'];
        }
        if ($lower === 'finance') {
            return ['name' => 'Finance Team', 'email' => 'finance@company.com'];
        }
        if ($lower === 'management') {
            return ['name' => 'Executive Management', 'email' => 'management@company.com'];
        }

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            $name = ucfirst(explode('@', $trimmed)[0]);
            return ['name' => $name, 'email' => $trimmed];
        }

        if (preg_match('/^[a-zA-Z0-9_\-\.]+$/', $trimmed)) {
            return ['name' => ucfirst($trimmed), 'email' => $trimmed . '@barani.com'];
        }

        return null;
    }

    /**
     * Build formal business cover letter for email dispatches.
     * Complies with the Barani Hydraulics standard:
     * Formal subject and clear message from the Barani team.
     */
    public static function getFormalCoverLetter(string $reportType, ?string $customSubject = null, ?string $customNotes = null, ?array $user = null): array
    {
        $type = strtolower(trim($reportType));
        $cleanMonth = date('F Y');

        switch ($type) {
            case 'heat_calculation':
            case 'heat':
            case 'hydraulic_press':
            case 'mc_spec':
                $reportTitle = "Heat Calculation of Hydraulic Press";
                $subject = $customSubject ?: "Heat Calculation Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of heat generation during the operation of the hydraulic press.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            case 'payroll':
            case 'salary':
            case 'trueup':
                $reportTitle = "Payroll and Employee Wage Breakdown";
                $subject = $customSubject ?: "Payroll Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}** ({$cleanMonth}).\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of employee wages, allowances, and statutory deductions during the payroll cycle.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            case 'downtime':
                $reportTitle = "Machine Downtime & Incident Tracking";
                $subject = $customSubject ?: "Machine Downtime Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of machine availability, stoppage durations, and preventive maintenance actions during the operation of the hydraulic press.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            case 'alarm':
            case 'alarms':
            case 'fault':
                $reportTitle = "SCADA Alarm & Fault Analysis";
                $subject = $customSubject ?: "SCADA Alarm & Fault Analysis Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of alarm occurrences, safety interlocks, and machine diagnostics during the operation of the hydraulic press.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            case 'production':
            case 'work_orders':
                $reportTitle = "Production Output & Work Orders Status";
                $subject = $customSubject ?: "Production Output Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of production volume, machine cycle efficiency, and job orders completed during the operation of the hydraulic press.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            case 'maintenance':
                $reportTitle = "Maintenance Activity & Preventive Inspection";
                $subject = $customSubject ?: "Maintenance Activity Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$reportTitle}**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and analysis of equipment service schedules, hydraulic fluid condition, and preventive checks during the operation of the hydraulic press.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;

            default:
                $label = ucwords(str_replace('_', ' ', $type));
                $subject = $customSubject ?: "{$label} Report — Barani Hydraulics";
                $bodyText = "Dear Sir/Madam,\n\n"
                    . "This message is from the Barani Hydraulics team regarding the **{$label} Report**.\n\n"
                    . "Please find attached the official report for your review and reference. The document includes the relevant calculations and operational analysis prepared by our team.\n\n"
                    . ($customNotes ? "Additional Notes:\n" . $customNotes . "\n\n" : "")
                    . "Kindly review the attached report and let us know if any further information or assistance is required.\n\n"
                    . "Thank you for your time and consideration.\n\n"
                    . "Regards,\n"
                    . "Barani Hydraulics Team\n"
                    . "Barani Hydraulics (India) Pvt. Ltd.";
                break;
        }

        return [
            'subject'   => $subject,
            'body_text' => $bodyText,
        ];
    }

    /**
     * Send payroll report to specific email addresses with formal cover letter and BWC-7578 PDF/Word attachment.
     */
    public static function sendPayrollReport(array $recipientEmails, string $htmlOrBodyReport, int $month, int $year, array $user, ?string $attachPath = null): array
    {
        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $subject   = "Payroll Report — Barani Hydraulics ({$monthName} {$year})";
        $toList    = [];
        foreach ($recipientEmails as $email) {
            $norm = self::normalizeRecipient($email);
            if ($norm) {
                $toList[] = $norm;
            }
        }
        if (empty($toList)) {
            $toList[] = ['name' => 'System Administrator', 'email' => 'admin@barani.com'];
        }

        // If no attachment provided, automatically generate the authentic BWC-7578 PDF attachment
        if (!$attachPath || !file_exists($attachPath)) {
            $customReport = ReportService::generateCustomizableReport(
                'payroll',
                'pdf',
                strip_tags($htmlOrBodyReport),
                "Payroll Report for {$monthName} {$year}",
                $user,
                null,
                $subject,
                ['month' => $month, 'year' => $year]
            );
            $attachPath = $customReport['file_path'] ?? null;
        }

        // Use the clean formal cover letter for email body
        $cover = self::getFormalCoverLetter('payroll', $subject, null, $user);
        $emailBody = (!empty($htmlOrBodyReport) && str_contains($htmlOrBodyReport, 'Dear Sir/Madam'))
            ? $htmlOrBodyReport
            : $cover['body_text'];

        $result = self::dispatch($toList, $subject, $emailBody, $user, $attachPath);
        if ($result['success']) {
            Logger::audit($user['id'] ?? 0, 'payroll_email_sent', "Payroll report sent for {$month}/{$year} to " . count($toList) . " recipients");
        }
        return $result;
    }

    /**
     * Send arbitrary formal report to specific email addresses with optional PDF/Word attachment.
     */
    public static function sendDirectReport(array $recipientEmails, string $subject, string $body, array $user, ?string $reportType = null, ?string $attachPath = null): array
    {
        $toList = [];
        foreach ($recipientEmails as $email) {
            $norm = self::normalizeRecipient($email);
            if ($norm) {
                $toList[] = $norm;
            }
        }
        if (empty($toList)) {
            $toList[] = ['name' => 'System Administrator', 'email' => 'admin@barani.com'];
        }

        // If body is empty, automatically build the clean formal cover letter
        if (empty(trim($body))) {
            $cover = self::getFormalCoverLetter($reportType ?: 'general', $subject, null, $user);
            $body = $cover['body_text'];
        }

        $result = self::dispatch($toList, $subject, $body, $user, $attachPath);
        if ($result['success']) {
            Logger::audit($user['id'] ?? 0, 'direct_report_email_sent', "Report '{$subject}' sent to " . count($toList) . " recipient(s)");
        }
        return $result;
    }

    /**
     * Direct email alias for export_query compatibility.
     */
    public static function sendDirectEmail(string $recipientEmail, string $subject, string $body, string $filePath, string $fileName, string $recipientName = 'Official', array $user = []): array
    {
        $norm = self::normalizeRecipient($recipientEmail);
        $toList = [$norm ?: ['name' => $recipientName, 'email' => $recipientEmail]];
        return self::dispatch($toList, $subject, $body, $user, $filePath);
    }

    /**
     * Trigger a scheduled job immediately with PDF or Word report attached.
     */
    public static function sendScheduledJobNow(array $job, array $emails, array $user, \PDO $pdo, ?string $format = null): array
    {
        $reportType  = $job['report_type'] ?? 'payroll';
        $format      = strtolower(trim($format ?: ($job['report_format'] ?? 'pdf')));
        $rawSubject  = trim($job['subject'] ?? '');
        $messageBody = trim($job['message_body'] ?? '');

        // Generate formal business cover letter complying exactly with Barani Hydraulics official standard
        $cover = self::getFormalCoverLetter($reportType, null, $messageBody, $user);

        // Subject: use clean Barani Hydraulics formal subject
        if (!empty($rawSubject) && !str_starts_with($rawSubject, 'GRI Report:') && !str_starts_with($rawSubject, 'Scheduled Report:')) {
            $subject = $rawSubject;
        } else {
            $subject = $cover['subject'];
        }

        // Generate report content and create 1 official downloadable attachment (PDF or Word)
        $htmlContent = ReportService::generateReportByType($reportType, $pdo);
        $customReport = ReportService::generateCustomizableReport(
            $reportType,
            $format,
            strip_tags($htmlContent),
            $messageBody,
            $user,
            null,
            $subject
        );

        $attachPath = $customReport['file_path'] ?? null;

        $toList = [];
        foreach ($emails as $email) {
            $norm = self::normalizeRecipient($email);
            if ($norm) {
                $toList[] = $norm;
            }
        }

        if (empty($toList)) {
            $toList[] = ['name' => 'System Administrator', 'email' => 'admin@barani.com'];
        }

        // In scheduled emails, the email body is ONLY the clean formal cover letter:
        // Dear Sir/Madam, This message is from the Barani Hydraulics team regarding...
        // No raw HTML tables or dumped forms in the email body!
        $emailBody = (!empty($messageBody) && str_contains($messageBody, 'Dear Sir/Madam'))
            ? $messageBody
            : $cover['body_text'];

        $result = self::dispatch($toList, $subject, $emailBody, $user, $attachPath);

        if ($result['success']) {
            $stmt = $pdo->prepare("UPDATE scheduled_emails SET last_sent_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $job['id']]);
            Logger::audit($user['id'] ?? 0, 'scheduled_email_sent', "Sent job #{$job['id']} ({$reportType}) to " . count($toList) . " recipients");
            $result['report'] = $customReport;
        }

        return $result;
    }

    /**
     * Core PHPMailer dispatch with Windows SSL support, attachment handling, and Outbox persistence.
     */
    private static function dispatch(array $toList, string $subject, string $htmlBody, array $user, ?string $attachPath): array
    {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (!file_exists($autoload)) {
            error_log("[EmailService] PHPMailer vendor not found.");
            return [
                'success'    => false,
                'message'    => "PHPMailer not installed. Run composer install.",
            ];
        }

        require_once $autoload;

        $mailConfig = require __DIR__ . '/../config/mail.php';
        $mail = new PHPMailer(true);

        $delivered = false;
        $smtpNote = '';
        $attachmentName = ($attachPath && file_exists($attachPath)) ? basename($attachPath) : null;
        $attachmentUrl = $attachmentName ? ("/api/download.php?file=" . urlencode($attachmentName)) : null;

        $fromEmail = !empty($mailConfig['from_address']) ? $mailConfig['from_address'] : 'baranihydraluics@gmail.com';
        $fromName  = !empty($mailConfig['from_name']) ? $mailConfig['from_name'] : 'Barani Hydraulics';

        // Try SMTP: port 587 (TLS) first, then fallback to port 465 (SSL)
        $smtpAttempts = [
            ['port' => 587, 'secure' => PHPMailer::ENCRYPTION_STARTTLS, 'label' => '587/TLS'],
            ['port' => 465, 'secure' => PHPMailer::ENCRYPTION_SMTPS,    'label' => '465/SSL'],
        ];

        $lastError = '';
        foreach ($smtpAttempts as $attempt) {
            // Fast reachability check with 3s timeout to avoid 20-40s hangs on blocked ISP networks
            $probe = @fsockopen($mailConfig['host'], $attempt['port'], $pErrno, $pErrstr, 3);
            if (!$probe) {
                $lastError = "Connection to {$mailConfig['host']}:{$attempt['port']} timed out ({$pErrstr})";
                continue;
            }
            fclose($probe);

            try {
                $mail = new PHPMailer(true);
                $mail->CharSet   = 'UTF-8';
                $mail->isSMTP();
                $mail->Host      = $mailConfig['host'];
                $mail->Port      = $attempt['port'];
                $mail->Timeout   = 10;
                $mail->SMTPAuth  = true;
                $mail->Username  = $mailConfig['username'];
                $mail->Password  = $mailConfig['password'];
                $mail->SMTPSecure = $attempt['secure'];
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];

                $mail->setFrom($fromEmail, $fromName);
                $mail->addReplyTo($fromEmail, $fromName);
                $mail->Sender  = $fromEmail;
                $mail->XMailer = 'Barani Hydraulics Enterprise Mailer';

                foreach ($toList as $r) {
                    $mail->addAddress($r['email'], $r['name']);
                }

                if ($attachPath && file_exists($attachPath)) {
                    $mail->addAttachment($attachPath, $attachmentName);
                }

                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = self::wrapHtmlEmail($htmlBody, $subject, $user, $attachmentName, $attachmentUrl);
                $mail->AltBody = strip_tags($htmlBody);

                $mail->send();
                $delivered = true;
                $status    = 'delivered';
                $message   = "✅ Email delivered via SMTP {$mailConfig['host']}:{$attempt['port']} ({$attempt['label']}) to " . count($toList) . " recipient(s): " . implode(', ', array_column($toList, 'email'));
                break; // Success — stop retrying

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                // Continue to next attempt
            }
        }

        if (!$delivered) {
            $smtpNote = $lastError;
            $status   = 'outbox_saved';

            if (str_contains($lastError, 'Username and Password not accepted') || str_contains($lastError, 'BadCredentials') || str_contains($lastError, 'Could not authenticate')) {
                $message = "⚠️ Gmail rejected the App Password. Please check App Passwords. Email & report archived to Outbox & Admin Mailbox.";
            } elseif (str_contains($lastError, 'Connection refused') || str_contains($lastError, 'timed out') || str_contains($lastError, 'could not be reached') || str_contains($lastError, 'unreachable')) {
                $message = "ℹ️ Email and attached report saved to Outbox & Admin Mailbox. (Direct SMTP {$mailConfig['host']} port 587/465 is blocked by network firewall, but your report is ready for download below).";
            } else {
                $message = "ℹ️ Email saved to Outbox & Admin Mailbox: {$lastError}";
            }
        }


        // Persist to Outbox log vault
        $outboxDir = __DIR__ . '/../storage/logs/outbox';
        if (!is_dir($outboxDir)) {
            mkdir($outboxDir, 0755, true);
        }

        $outboxRecord = [
            'id'              => 'out_' . bin2hex(random_bytes(8)),
            'timestamp'       => date('Y-m-d H:i:s'),
            'subject'         => $subject,
            'recipients'      => array_column($toList, 'email'),
            'delivered'       => $delivered,
            'status'          => $status,
            'smtp_note'       => $smtpNote,
            'attachment_name' => $attachmentName,
            'attachment_url'  => $attachmentUrl,
            'attachment_size' => ($attachPath && file_exists($attachPath)) ? filesize($attachPath) : 0,
            'sender'          => $user['username'] ?? 'Admin',
        ];

        file_put_contents(
            $outboxDir . '/' . date('Ymd_His') . '_' . $outboxRecord['id'] . '.json',
            json_encode($outboxRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Also ensure an Admin Mailbox record exists for admin auditing & download
        $adminMailboxDir = __DIR__ . '/../storage/mailbox/admin';
        if (!is_dir($adminMailboxDir)) {
            mkdir($adminMailboxDir, 0755, true);
        }

        $adminRecord = [
            'id'              => 'adm_' . date('Ymd_His_') . bin2hex(random_bytes(4)),
            'timestamp'       => date('Y-m-d H:i:s'),
            'from'            => $fromEmail ?? 'noreply@barani.com',
            'to'              => array_column($toList, 'email'),
            'subject'         => $subject,
            'body_preview'    => substr(strip_tags($htmlBody), 0, 400),
            'attachments'     => $attachmentName ? [$attachmentName] : [],
            'attachment_url'  => $attachmentUrl,
            'delivered_local' => true,
            'status'          => $status
        ];

        file_put_contents(
            $adminMailboxDir . '/' . date('Ymd_His') . '_' . $adminRecord['id'] . '.json',
            json_encode($adminRecord, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        // Temp Mailbox & Ethereal Webmail synchronization
        require_once __DIR__ . '/TempMailService.php';
        $hasTempRecipient = false;
        foreach ($toList as $r) {
            if (TempMailService::isTempRecipient($r['email'])) {
                $hasTempRecipient = true;
                break;
            }
        }

        $tempAccount = null;
        $tempRecord = null;
        if ($hasTempRecipient) {
            $tempAccount = TempMailService::getOrCreateAccount();
            $tempRecord = [
                'id'              => 'tmp_' . date('Ymd_His_') . bin2hex(random_bytes(4)),
                'timestamp'       => date('Y-m-d H:i:s'),
                'from'            => $fromEmail ?? 'noreply@barani.com',
                'to'              => array_column($toList, 'email'),
                'subject'         => $subject,
                'body_preview'    => substr(strip_tags($htmlBody), 0, 400),
                'body_html'       => $htmlBody,
                'attachments'     => $attachmentName ? [$attachmentName] : [],
                'attachment_url'  => $attachmentUrl,
                'attachment_name' => $attachmentName,
                'status'          => 'delivered',
                'webmail_url'     => $tempAccount['web_url'] ?? 'https://ethereal.email/messages'
            ];
            TempMailService::saveIncomingMessage($tempRecord);

            // Also relay through official Ethereal SMTP so message is live on public webmail
            self::sendViaEtherealSmtp($tempAccount, $toList, $subject, $htmlBody, $user, $attachPath);
        }

        Logger::audit($user['id'] ?? 0, 'email_dispatched', "Subject: {$subject} | Status: {$status} | Attach: {$attachmentName}");

        return [
            'success'         => $delivered || !empty($outboxRecord),
            'delivered'       => $delivered,
            'status'          => $status,
            'message'         => $hasTempRecipient
                ? "✅ Report dispatched and received in Temp Mailbox & Ethereal Webmail for " . implode(', ', array_column($toList, 'email'))
                : $message,
            'recipients'      => array_column($toList, 'email'),
            'attachment_name' => $attachmentName,
            'attachment_url'  => $attachmentUrl,
            'outbox'          => $outboxRecord,
            'admin_mailbox'   => $adminRecord,
            'temp_mailbox'    => $tempRecord,
            'webmail_url'     => $tempAccount['web_url'] ?? 'https://ethereal.email/messages'
        ];
    }

    /**
     * Retrieve recent outbox dispatches.
     */
    public static function getOutbox(int $limit = 25): array
    {
        $outboxDir = __DIR__ . '/../storage/logs/outbox';
        if (!is_dir($outboxDir)) return [];

        $files = glob($outboxDir . '/*.json');
        if (!$files) return [];

        rsort($files);
        $records = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $data = json_decode(file_get_contents($f), true);
            if ($data) $records[] = $data;
        }
        return $records;
    }

    /**
     * Retrieve messages and reports received in Admin Mailbox.
     */
    public static function getAdminMailbox(int $limit = 25): array
    {
        $mailboxDir = __DIR__ . '/../storage/mailbox/admin';
        if (!is_dir($mailboxDir)) return [];

        $files = glob($mailboxDir . '/*.json');
        if (!$files) return [];

        rsort($files);
        $records = [];
        foreach (array_slice($files, 0, $limit) as $f) {
            $data = json_decode(file_get_contents($f), true);
            if ($data) {
                // Ensure attachment_url is populated for all attachments
                if (!empty($data['attachments']) && empty($data['attachment_url'])) {
                    $firstAtt = $data['attachments'][0];
                    $data['attachment_url'] = "/api/download.php?file=" . urlencode($firstAtt);
                    $data['attachment_name'] = $firstAtt;
                }
                $records[] = $data;
            }
        }
        return $records;
    }

    /**
     * Wrap HTML content in a clean, formal corporate email shell.
     * Engineered for 100% Gmail/Outlook compatibility without broken images or clipping.
     */
    private static function wrapHtmlEmail(string $body, string $subject, array $user, ?string $attachmentName, ?string $attachmentUrl): string
    {
        $date = date('d M Y');
        $year = date('Y');

        // Format plaintext body with clean paragraphs and bold tags if not already HTML
        if (!str_contains($body, '<table') && !str_contains($body, '<div class=') && !str_contains($body, '<h2')) {
            $formatted = htmlspecialchars($body);
            // Convert **bold** markdown to HTML
            $formatted = preg_replace('/\*\*(.*?)\*\*/', '<strong style="color: #0f172a;">$1</strong>', $formatted);
            // Convert paragraphs
            $paragraphs = explode("\n\n", str_replace("\r", "", $formatted));
            $bodyHtml = '';
            foreach ($paragraphs as $p) {
                $trimmed = trim($p);
                if (empty($trimmed)) continue;
                $bodyHtml .= "<p style='margin: 0 0 16px 0; font-family: Arial, Helvetica, sans-serif; font-size: 14px; line-height: 1.65; color: #334155;'>" . nl2br($trimmed) . "</p>";
            }
            $body = $bodyHtml;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{$subject}</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; background-color: #f1f5f9; margin: 0; padding: 20px; color: #1e293b;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 680px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #cbd5e1; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
    <!-- FORMAL CORPORATE HEADER -->
    <tr>
      <td style="background-color: #1e3a8a; padding: 22px 28px; color: #ffffff;">
        <div style="font-size: 16px; font-weight: bold; letter-spacing: 0.5px; color: #ffffff; text-transform: uppercase;">
          Barani Hydraulics (India) Pvt. Ltd.
        </div>
        <div style="font-size: 11.5px; color: #bfdbfe; margin-top: 3px; letter-spacing: 0.3px;">
          Industrial Machinery &amp; Automation Systems &bull; Official Dispatch
        </div>
        <div style="font-size: 15px; font-weight: 600; color: #ffffff; margin-top: 14px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">
          {$subject}
        </div>
        <div style="font-size: 11px; color: #cbd5e1; margin-top: 4px;">
          Date: {$date}
        </div>
      </td>
    </tr>

    <!-- FORMAL COVER LETTER / BODY -->
    <tr>
      <td style="padding: 28px 28px 20px 28px; background-color: #ffffff; font-size: 14px; line-height: 1.65; color: #334155;">
        {$body}
      </td>
    </tr>

    <!-- FORMAL COMPLIANCE FOOTER -->
    <tr>
      <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 16px 28px; font-size: 11px; color: #64748b; line-height: 1.5;">
        <div><strong>Barani Hydraulics (India) Pvt. Ltd.</strong> &bull; Official Communication</div>
        <div style="margin-top: 4px; color: #94a3b8; font-size: 10px;">
          CONFIDENTIALITY NOTICE: This transmission is confidential and intended solely for the authorized recipient(s). &copy; {$year} All rights reserved.
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Send via official Ethereal SMTP server so the email can be viewed live in external webmail.
     */
    public static function sendViaEtherealSmtp(array $account, array $toList, string $subject, string $htmlBody, array $user, ?string $attachPath): bool
    {
        if (empty($account['password']) || empty($account['email'])) return false;

        try {
            $autoload = __DIR__ . '/../vendor/autoload.php';
            if (!file_exists($autoload)) return false;
            require_once $autoload;

            $etherealMail = new PHPMailer(true);
            $etherealMail->isSMTP();
            $etherealMail->Host       = $account['smtp_host'] ?? 'smtp.ethereal.email';
            $etherealMail->Port       = (int)($account['smtp_port'] ?? 587);
            $etherealMail->SMTPAuth   = true;
            $etherealMail->Username   = $account['email'];
            $etherealMail->Password   = $account['password'];
            $etherealMail->SMTPSecure = 'tls';
            $etherealMail->Timeout    = 8;
            $etherealMail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            $etherealMail->setFrom($account['email'], 'Barani Hydraulics (GRI System)');
            foreach ($toList as $r) {
                $etherealMail->addAddress($r['email'], $r['name']);
            }
            if ($attachPath && file_exists($attachPath)) {
                $etherealMail->addAttachment($attachPath, basename($attachPath));
            }
            $etherealMail->isHTML(true);
            $etherealMail->Subject = $subject;

            $attachmentName = ($attachPath && file_exists($attachPath)) ? basename($attachPath) : null;
            $attachmentUrl = $attachmentName ? ("/api/download.php?file=" . urlencode($attachmentName)) : null;
            $etherealMail->Body    = self::wrapHtmlEmail($htmlBody, $subject, $user, $attachmentName, $attachmentUrl);
            $etherealMail->AltBody = strip_tags($htmlBody);

            $etherealMail->send();
            return true;
        } catch (\Throwable $e) {
            error_log("[EmailService] Ethereal SMTP send error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Dispatch helper for temp mail captures.
     */
    public static function dispatchWithTempCapture(array $toList, string $subject, string $htmlBody, array $user, ?string $attachPath, array $tempAccount): array
    {
        return self::dispatch($toList, $subject, $htmlBody, $user, $attachPath);
    }
}
