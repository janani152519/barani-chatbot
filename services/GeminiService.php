<?php
/**
 * GeminiService — Google Gemini 1.5 Flash LLM Client
 * Handles: Text-to-SQL generation, natural language result formatting,
 *          Tanglish understanding, spelling correction, conversational chat
 *
 * Free API: https://ai.google.dev  (set GEMINI_API_KEY in .env)
 */

require_once __DIR__ . '/../config/database.php';

class GeminiService
{
    private static ?string $apiKey   = null;
    private static ?string $model    = null;
    private static bool    $enabled  = false;

    // ── Boot: read config from .env ──────────────────────────────────────────
    private static function boot(): void
    {
        if (self::$apiKey !== null) return;

        $env = [];
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                $env[trim($k)] = trim($v);
            }
        }

        self::$apiKey  = $env['GEMINI_API_KEY']  ?? '';
        self::$model   = $env['GEMINI_MODEL']    ?? 'gemini-1.5-flash';
        self::$enabled = ($env['GEMINI_ENABLED'] ?? 'false') === 'true'
                      && !empty(self::$apiKey)
                      && self::$apiKey !== 'YOUR_GEMINI_API_KEY_HERE';
    }

    public static function isEnabled(): bool
    {
        self::boot();
        return self::$enabled;
    }

    // ── Core API call with multi-model fallback chain ───────────────────────
    private static function call(string $systemPrompt, string $userPrompt, float $temperature = 0.1): string
    {
        self::boot();

        if (!self::$enabled) {
            throw new \RuntimeException('Gemini API not configured. Set GEMINI_API_KEY in .env and GEMINI_ENABLED=true');
        }

        // Resilient model priority chain: ultra-fast flash-lite models first, then flash-latest, then 3.6-flash
        $modelsToTry = array_unique([
            self::$model ?: 'gemini-3.5-flash-lite',
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-flash-latest',
            'gemini-3.6-flash',
        ]);

        $lastError = 'Unknown error';

        foreach ($modelsToTry as $m) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key=" . urlencode(self::$apiKey);

            $payload = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $systemPrompt . "\n\n" . $userPrompt]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature'     => $temperature,
                    'maxOutputTokens' => 2048,
                    'topP'            => 0.8,
                ],
                'safetySettings' => [
                    ['category' => 'HARM_CATEGORY_HARASSMENT',        'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_HATE_SPEECH',       'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_NONE'],
                    ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_NONE'],
                ]
            ], JSON_UNESCAPED_UNICODE);

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 20,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $raw = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr) {
                $lastError = "cURL error ($m): $curlErr";
                continue;
            }

            if ($httpCode === 200) {
                $data = json_decode($raw, true);
                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                $text = '';
                foreach ($parts as $p) {
                    if (isset($p['text'])) {
                        $text .= $p['text'];
                    }
                }
                if (!empty(trim($text))) {
                    return trim($text);
                }
            }

            // High demand (429/503/etc.): catch and try next model in chain immediately
            $errData = json_decode($raw, true);
            $errMsg  = $errData['error']['message'] ?? "HTTP $httpCode";
            $lastError = "Model $m ($httpCode): $errMsg";
        }

        throw new \RuntimeException("Gemini API error: $lastError");
    }

    // ── 1. Generate SQL from natural language ─────────────────────────────────
    public static function generateSQL(string $userQuestion, string $schemaContext, ?string $role = 'admin'): array
    {
        $systemPrompt = <<<SYS
You are an expert MySQL query generator for the Barani Hydraulics GRI SCADA system.
Your job is to convert natural language questions (including Tanglish and spelling mistakes) into safe MySQL SELECT queries.

STRICT RULES:
1. Output ONLY a valid MySQL SELECT statement. No explanation, no markdown, no backticks around the whole response.
2. Never generate UPDATE, DELETE, DROP, INSERT, ALTER, TRUNCATE, CREATE, GRANT.
3. Always add LIMIT (default LIMIT 50, max LIMIT 200).
4. Use proper JOINs when needed.
5. Handle Tanglish: "kudu"/"kodu" = show/give, "yaaru" = who, "enna" = what, "eppo" = when, "evlo" = how many, "sollu" = tell.
6. Correct common typos automatically.
7. For date filtering: use CURDATE(), MONTH(), YEAR() functions.
8. If the question is NOT directly related to the Barani Hydraulics factory, machines, SCADA, employees, payroll, attendance, work orders, alarms, recipes, downtime, or database records (e.g. general knowledge, politics, Chief Minister, weather, sports, celebrities, movies, non-factory chat), output EXACTLY: CANNOT_GENERATE

DATABASE SCHEMA (gri_db):
$schemaContext

USER ROLE: $role (admin has access to all tables)
SYS;

        $userPrompt = "Convert this question to MySQL SELECT: \"$userQuestion\"";

        try {
            $sql = self::call($systemPrompt, $userPrompt, 0.05);

            // Clean up: strip markdown code fences if model adds them
            $sql = preg_replace('/^```(?:sql)?\s*/i', '', $sql);
            $sql = preg_replace('/```\s*$/', '', $sql);
            $sql = trim($sql);

            return ['success' => true, 'sql' => $sql, 'model' => self::$model];

        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'sql' => null];
        }
    }

    // ── 2. Format SQL result into natural language ────────────────────────────
    public static function formatResult(string $userQuestion, array $rows, string $sql, int $totalRows): string
    {
        if (empty($rows)) {
            return self::formatEmpty($userQuestion);
        }

        // For very large result sets, summarise instead of listing all
        $preview = array_slice($rows, 0, 20);

        $systemPrompt = <<<SYS
You are a friendly, intelligent AI assistant for Barani Hydraulics (India) Pvt. Ltd., a hydraulic press manufacturing factory in Coimbatore.

You answer questions about the company's employees, payroll, attendance, machines, production, alarms, and SCADA system.

FORMATTING RULES:
1. Answer in a conversational, clear, professional tone.
2. Use markdown: **bold** for important values, tables where multiple rows, bullet lists.
3. Use Indian Rupee symbol ₹ for monetary values.
4. Include relevant emojis (👤 for employees, 💰 for salary, 🏭 for machines, ⚠️ for alarms).
5. Be concise but complete. Don't repeat the raw SQL.
6. If results contain employee data, summarise key points (count, totals, notable entries).
7. Always end with a helpful follow-up suggestion.
8. If question was in Tanglish or had spelling mistakes, answer in clean English.

COMPANY CONTEXT: Barani Hydraulics (India) Pvt. Ltd., SF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402. GRI SCADA Machine Intelligence System.
SYS;

        $rowsJson  = json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        $userPrompt = "User asked: \"$userQuestion\"\n\nSQL executed: $sql\n\nTotal rows found: $totalRows\nData (first " . count($preview) . " rows):\n$rowsJson\n\nNow write a clear, natural answer for the user:";

        try {
            return self::call($systemPrompt, $userPrompt, 0.3);
        } catch (\Throwable $e) {
            // Fallback: basic formatting
            return self::fallbackFormat($userQuestion, $rows, $totalRows);
        }
    }

    // ── 3. Handle conversational queries and enforce database-only domain ─────
    public static function answerConversational(string $userQuestion, string $conversationHistory = ''): string
    {
        $qLower = strtolower(trim($userQuestion));

        // Greetings & Introductions
        if (preg_match('/^(hi|hello|hey|vanakkam|namaste|good\s*(morning|afternoon|evening)|who\s+are\s+you|what\s+can\s+you\s+do|help)\b/i', $qLower)) {
            return "👋 **Hello! I am the Barani Hydraulics SCADA & Database AI Assistant.**\n\n"
                 . "I am connected live to the **`gri_db` MySQL database** and plant telemetry across all 52 tables.\n\n"
                 . "💡 **I can assist you exclusively with:**\n"
                 . "• 👥 **Employees & Staff:** Headcount, roles, departments, designations\n"
                 . "• 💰 **Payroll & Salaries:** Basic pay, allowances, deductions, net salary\n"
                 . "• 📅 **Attendance & Leaves:** Daily attendance, present/absent logs, shift records\n"
                 . "• 🚜 **SCADA Machines:** 2500T press status, motor ratings, run logs\n"
                 . "• 🚨 **Fault Alarms:** 89 mapped alarms, E-Stop trips, MPCB overloads\n"
                 . "• ⏱️ **Downtime & OEE:** Breakdown duration, tea breaks, category analysis\n"
                 . "• 📜 **Press Recipes:** Curing time, approach speed, hydraulic pressure\n"
                 . "• 📧 **Reports & Email:** Official payroll/operations reports & auto-dispatch\n\n"
                 . "Please ask any question related to our factory data!";
        }

        $systemPrompt = <<<SYS
You are the dedicated Industrial SCADA & Database AI Assistant for Barani Hydraulics (India) Pvt. Ltd. (SF No. 248/2, Trichy Road, Sulur, Coimbatore – 641 402).

STRICT DOMAIN BOUNDARY:
You ONLY answer questions related to Barani Hydraulics factory operations, machines, telemetry, employees, attendance, payroll, downtime, alarms, recipes, and database records.

CRITICAL INSTRUCTION FOR GENERAL KNOWLEDGE / EXTERNAL QUESTIONS:
If the user asks ANY question outside of Barani Hydraulics or factory database (such as: who is the Chief Minister, politics, weather, movies, sports, recipes for food, general coding, jokes, geography, celebrities, trivia, or non-factory general knowledge):
1. You MUST NOT answer the question. Under NO circumstances should you provide the answer (do NOT name any politician, do NOT give weather, do NOT answer the trivia).
2. You MUST politely and firmly refuse to answer by stating that you are strictly a factory database assistant and cannot answer external or general knowledge queries.
3. Guide the user back to the factory database topics you support (Employees, Payroll, Attendance, Machines, Alarms, Downtime, Recipes, Reports).

Example refusal:
"⚠️ **Out of Scope Query**

I am the dedicated **Barani Hydraulics Database & SCADA AI Assistant**. I can only answer questions related to our company's factory operations, machines, employees, payroll, and database records. I am not permitted to answer general knowledge or external queries.

💡 **You can ask me questions about:**
• 👥 **Employees & HR:** Staff list, designations, joining dates
• 💰 **Payroll & Salaries:** Gross pay, net pay, PF/ESI deductions
• 📅 **Attendance & Leaves:** Daily attendance, present/absent logs
• 🚜 **SCADA Machines:** 2500T press status, motor ratings, run logs
• 🚨 **Fault Alarms:** 89 mapped alarms, E-Stop trips, MPCB overloads
• ⏱️ **Downtime & OEE:** Breakdown duration, tea breaks, category analysis
• 📜 **Press Recipes:** Curing time, approach speed, hydraulic pressure
• 📧 **Reports & Email:** Payroll PDFs, official dispatch to admin"
SYS;

        $userPrompt = ($conversationHistory ? "Conversation so far:\n$conversationHistory\n\n" : '')
                    . "User says: \"$userQuestion\"\n\nEnforce strict domain boundary and respond:";

        try {
            return self::call($systemPrompt, $userPrompt, 0.2);
        } catch (\Throwable $e) {
            return "⚠️ **Out of Scope Query**\n\n"
                 . "I am the dedicated **Barani Hydraulics Database & SCADA AI Assistant**. I can only answer questions related to our company's factory operations, machines, employees, payroll, and database records. I cannot answer general knowledge or external queries.\n\n"
                 . "💡 **Please ask me about:**\n"
                 . "• 👥 Employees & HR details\n"
                 . "• 💰 Payroll & Salary breakdowns\n"
                 . "• 📅 Attendance & Leaves\n"
                 . "• 🚜 Machine Status & SCADA Telemetry\n"
                 . "• 🚨 89 Mapped Alarms & Safety Interlocks\n"
                 . "• ⏱️ Machine Downtime & Production Run Logs\n"
                 . "• 📧 PDF/Word Reports & Email Dispatch";
        }
    }

    // ── 4. Generate chart data from question ─────────────────────────────────
    public static function suggestChart(string $userQuestion, array $rows): ?array
    {
        if (empty($rows) || count($rows) < 2) return null;

        // Detect if a chart is explicitly requested
        if (!preg_match('/chart|graph|plot|visual|trend|bar|pie|line/i', $userQuestion)) {
            return null;
        }

        $sample    = array_slice($rows, 0, 15);
        $keys      = array_keys($sample[0] ?? []);
        $chartType = preg_match('/pie/i', $userQuestion) ? 'pie'
                   : (preg_match('/line|trend/i', $userQuestion) ? 'line' : 'bar');

        // Auto-detect label and value columns
        $labelCol = null;
        $valueCol = null;
        foreach ($keys as $k) {
            if (preg_match('/name|code|type|department|status|label/i', $k) && !$labelCol) $labelCol = $k;
            if (preg_match('/salary|total|count|amount|qty|quantity|duration|net/i', $k) && !$valueCol) $valueCol = $k;
        }
        if (!$labelCol) $labelCol = $keys[0] ?? 'label';
        if (!$valueCol) $valueCol = $keys[1] ?? 'value';

        $chartData = [];
        foreach ($sample as $row) {
            $chartData[] = [
                'name'  => (string)($row[$labelCol] ?? ''),
                'value' => (float)($row[$valueCol]  ?? 0),
            ];
        }

        return [
            'type'   => $chartType,
            'title'  => ucwords(str_replace(['_', '-'], ' ', $valueCol)) . ' by ' . ucwords(str_replace(['_', '-'], ' ', $labelCol)),
            'data'   => $chartData,
            'xKey'   => 'name',
            'yKey'   => 'value',
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────
    private static function formatEmpty(string $question): string
    {
        return "🔍 No records found for your query.\n\n"
             . "This could mean the data doesn't exist yet or the filter is too specific.\n\n"
             . "💡 Try asking: *\"show all employees\"* or *\"show payroll September 2026\"*";
    }

    private static function fallbackFormat(string $question, array $rows, int $total): string
    {
        $count   = count($rows);
        $headers = array_keys($rows[0] ?? []);
        $lines   = ["Found **{$total} record(s)**:\n"];

        if ($count <= 5) {
            foreach ($rows as $i => $row) {
                $lines[] = "**" . ($i + 1) . ".** " . implode(' | ', array_map(
                    fn($k, $v) => "**$k:** $v",
                    $headers, array_values($row)
                ));
            }
        } else {
            // Table format for larger results
            $lines[] = "| " . implode(' | ', $headers) . " |";
            $lines[] = "| " . implode(' | ', array_fill(0, count($headers), '---')) . " |";
            foreach (array_slice($rows, 0, 10) as $row) {
                $lines[] = "| " . implode(' | ', array_values($row)) . " |";
            }
            if ($total > 10) $lines[] = "\n*...and " . ($total - 10) . " more records*";
        }

        return implode("\n", $lines);
    }
}
