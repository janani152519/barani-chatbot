<?php
/**
 * GRI SCADA AI Chat API — Full LLM Pipeline
 *
 * When Gemini API key is configured:
 *   User → LLM understands → generates SQL → MySQL → LLM formats → natural answer
 *
 * When Gemini not configured (fallback):
 *   User → IntentService (30+ NLP patterns) → DB → formatted answer
 *
 * Supports: Tanglish, spelling mistakes, report generation, email dispatch
 * POST /api/chat.php
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../core/helpers.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/LocalLLMService.php';
require_once __DIR__ . '/../services/GeminiService.php';
require_once __DIR__ . '/../services/TextToSQLService.php';
require_once __DIR__ . '/../services/IntentService.php';
require_once __DIR__ . '/../services/ReportService.php';
require_once __DIR__ . '/../services/EmailService.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── 1. Auth ──────────────────────────────────────────────────────────────────
$user = Auth::requireAuth();

// ── 2. Input ─────────────────────────────────────────────────────────────────
$input   = Validator::getJsonInput();
Validator::requireFields($input, ['message']);
$message     = trim($input['message']);
$sessionUuid = $input['session_uuid'] ?? null;
$pdo         = Database::getConnection();

// ── 3. Session ───────────────────────────────────────────────────────────────
if ($sessionUuid) {
    $s = $pdo->prepare("SELECT id, session_uuid FROM chat_sessions WHERE session_uuid=:u AND user_id=:uid LIMIT 1");
    $s->execute([':u' => $sessionUuid, ':uid' => $user['id']]);
    $session = $s->fetch();
} else { $session = null; }

if (!$session) {
    $sessionUuid = 'sess_' . bin2hex(random_bytes(16));
    $s = $pdo->prepare("INSERT INTO chat_sessions (session_uuid, user_id, title) VALUES (:u, :uid, :t)");
    $s->execute([':u' => $sessionUuid, ':uid' => $user['id'], ':t' => substr($message, 0, 60)]);
    $sessionId = (int)$pdo->lastInsertId();
} else {
    $sessionId = (int)$session['id'];
}

// ── 4. Conversation history for context ──────────────────────────────────────
$histStmt = $pdo->prepare("SELECT sender, message FROM chat_messages WHERE session_id=:sid ORDER BY id DESC LIMIT 6");
$histStmt->execute([':sid' => $sessionId]);
$history  = array_reverse($histStmt->fetchAll(PDO::FETCH_ASSOC));
$historyText = implode("\n", array_map(fn($h) => strtoupper($h['sender']) . ": " . $h['message'], $history));

// ── 5. Detect high-level action intents before LLM ───────────────────────────
$norm = TextToSQLService::normalise($message);

// A. Generate report from chat
if (preg_match('/\b(generate|create|make|prepare)\b/i', $norm) &&
    preg_match('/\b(report|pdf|word|excel|docx)\b/i', $norm)) {

    $rType  = preg_match('/payroll|salary/i', $norm) ? 'payroll'
            : (preg_match('/attendance/i', $norm) ? 'attendance'
            : (preg_match('/production/i', $norm) ? 'production'
            : (preg_match('/alarm/i',      $norm) ? 'alarm'      : 'payroll')));
    $fmt    = preg_match('/word|docx/i', $norm) ? 'docx'
            : (preg_match('/excel|xlsx/i', $norm) ? 'excel' : 'pdf');

    try {
        $html    = ReportService::generateReportByType($rType, $pdo);
        $repMeta = ReportService::generateCustomizableReport(
            $rType, $fmt, strip_tags($html),
            "AI-generated on " . date('d M Y H:i'), $user, null,
            ucwords($rType) . " Report — " . date('F Y')
        );
        $answer = "✅ **" . ucwords($rType) . " Report Generated!**\n\n"
                . "📄 **Format:** " . strtoupper($fmt) . "\n"
                . "📁 **File:** `{$repMeta['file_name']}`\n"
                . "📏 **Size:** " . number_format($repMeta['file_size']) . " bytes\n\n"
                . "📥 **[Click here to download]({$repMeta['download_url']})**\n\n"
                . "💡 *Want me to email this report to admin? Just say \"send this to admin\"*";
        $formatted = ['type' => 'report_generated', 'answer' => $answer, 'report' => $repMeta];
    } catch (\Throwable $ex) {
        $formatted = ['type' => 'answer', 'answer' => "⚠️ Report error: " . $ex->getMessage()];
    }

// B. Send email from chat
} elseif (preg_match('/\b(send|mail|email|dispatch)\b/i', $norm) &&
          preg_match('/\b(report|payroll|attendance|to\s+admin)\b/i', $norm)) {

    preg_match('/([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})/', $message, $em);
    $recipient = $em[1] ?? 'baranihydraluics@gmail.com';
    $rType     = preg_match('/attendance/i', $norm) ? 'attendance' : 'payroll';

    try {
        $html      = ($rType === 'payroll')
                   ? ReportService::generatePayrollHtmlReport((int)date('n'), (int)date('Y'), $pdo)
                   : ReportService::generateReportByType($rType, $pdo);
        $subject   = ucwords($rType) . " Report — Barani Hydraulics (" . date('F Y') . ")";
        $rep       = ReportService::generateCustomizableReport($rType, 'pdf', strip_tags($html),
                     "AI-chat dispatch " . date('d M Y H:i'), $user, null, $subject);
        $eResult   = EmailService::sendDirectReport([$recipient], $subject, $html, $user, $rType, $rep['file_path'] ?? null);

        $ok     = $eResult['delivered'] ?? false;
        $answer = $ok
            ? "✅ **Email sent!**\n\n📧 **To:** `$recipient`\n📋 **Subject:** $subject\n📎 **Attachment:** `{$rep['file_name']}`"
            : "📬 **Report saved to Outbox** (SMTP blocked on this network).\n\n📧 **To:** `$recipient`\n📋 **Subject:** $subject\n📎 **PDF:** `{$rep['file_name']}`\n\n📥 Use the **Download** button in the Reports tab to save the file.";
        $formatted = ['type' => 'email_sent', 'answer' => $answer, 'email_status' => $eResult];
    } catch (\Throwable $ex) {
        $formatted = ['type' => 'answer', 'answer' => "⚠️ Email error: " . $ex->getMessage()];
    }

// C. All other questions → LLM pipeline (if Gemini enabled) OR IntentService fallback
} else {
    // ════ 100% OFFLINE LOCAL LLM PIPELINE ══════════════════════════════════
    $result = LocalLLMService::process($message, $user, ['history' => $historyText]);

    $formatted = array_merge($result, [
        'type'   => $result['type'] ?? $result['intent'] ?? 'answer',
        'answer' => $result['answer'] ?? $result['direct_answer'] ?? 'Done.',
    ]);

    if (!empty($result['sql']) && empty($formatted['generated_sql'])) $formatted['generated_sql'] = $result['sql'];
    if (!empty($result['rows']) && empty($formatted['records']))      $formatted['records']       = array_slice($result['rows'], 0, 50);
    if (!empty($result['total']) && empty($formatted['total_rows']))  $formatted['total_rows']    = $result['total'];
    if (!empty($result['visual']) && empty($formatted['chart_data'])) $formatted['chart_data']    = $result['visual'];
    if (!empty($result['chart_data']) && empty($formatted['visual'])) $formatted['visual']        = $result['chart_data'];
}

// ── 6. Persist to DB ─────────────────────────────────────────────────────────
$botAnswer = $formatted['answer'] ?? 'Done.';
$intent    = $formatted['type']   ?? 'answer';

$ins = $pdo->prepare("INSERT INTO chat_messages (session_id, sender, message, intent, structured_plan) VALUES (:s,'user',:m,:i,:p)");
$ins->execute([':s' => $sessionId, ':m' => $message, ':i' => $intent, ':p' => json_encode(['normalized' => $norm])]);

$ins = $pdo->prepare("INSERT INTO chat_messages (session_id, sender, message, intent, structured_plan) VALUES (:s,'assistant',:m,:i,:p)");
$ins->execute([':s' => $sessionId, ':m' => $botAnswer, ':i' => $intent, ':p' => json_encode($formatted)]);

// ── 7. Audit ─────────────────────────────────────────────────────────────────
$llmMode = 'local_offline_llm';
Logger::audit($user['id'], 'chat_query', "[$llmMode] Q: \"$message\" | Intent: $intent");

// ── 8. Respond ───────────────────────────────────────────────────────────────
Response::success(array_merge([
    'session_uuid' => $sessionUuid,
    'intent'       => $intent,
    'mode'         => $llmMode,
    'normalized'   => $norm,
], $formatted), 'chat_response', 200);
