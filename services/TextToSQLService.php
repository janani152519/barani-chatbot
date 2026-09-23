<?php
/**
 * TextToSQLService — Full LLM-powered Text-to-SQL Pipeline
 *
 * Pipeline:
 *  User message
 *    → Tanglish + spell normalise
 *    → GeminiService::generateSQL()      ← LLM generates SQL
 *    → AstGuardrailFirewall::validate()  ← Security check
 *    → Execute on MySQL
 *    → GeminiService::formatResult()     ← LLM formats answer
 *    → Return structured response
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/GeminiService.php';
require_once __DIR__ . '/AstGuardrailFirewall.php';
require_once __DIR__ . '/SchemaProvider.php';

class TextToSQLService
{
    // Max rows to return to avoid memory issues
    private const MAX_ROWS = 200;

    // Tanglish + common typo normaliser (shared with chat.php)
    public static function normalise(string $q): string
    {
        $map = [
            'kudu'        => 'show me',   'kodu'       => 'show me',
            'sollu'       => 'tell me',   'solu'       => 'tell me',
            'yaaru'       => 'who is',    'yaru'       => 'who is',
            'enna'        => 'what is',   'eppo'       => 'when',
            'enge'        => 'where',     'evlo'       => 'how many',
            'romba'       => 'very',      'illai'      => 'no',
            'iruku'       => 'is there',  'patharu'    => 'check',
            'paar'        => 'show',
            // Typos
            'attndance'   => 'attendance','atendance'  => 'attendance',
            'sallary'     => 'salary',    'salery'     => 'salary',    'slary'     => 'salary',
            'deparment'   => 'department','departmnt'  => 'department',
            'machien'     => 'machine',   'mahcine'    => 'machine',
            'prodution'   => 'production','producton'  => 'production',
            'mantenance'  => 'maintenance','maintenace'=> 'maintenance',
            'reprot'      => 'report',    'reort'      => 'report',
            'payrool'     => 'payroll',   'payrol'     => 'payroll',
            'emplyee'     => 'employee',  'employe'    => 'employee',  'employes' => 'employees',
            'wht'         => 'what',      'waht'       => 'what',      'hwo'      => 'how',
            'alram'       => 'alarm',     'receipes'   => 'recipes',   'recipies' => 'recipes',
            'breakdwon'   => 'breakdown', 'brekdown'   => 'breakdown',
            'workorder'   => 'work order','workorders' => 'work orders',
            'sparparts'   => 'spare parts','sparepart'  => 'spare part',
            'downtme'     => 'downtime',  'down time'  => 'downtime',
        ];

        $lower = strtolower($q);
        foreach ($map as $wrong => $right) {
            $lower = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/', $right, $lower);
        }
        return $lower;
    }

    // ── Build compact schema string for LLM prompt ───────────────────────────
    public static function buildSchemaContext(): string
    {
        // Use hand-crafted schema for the key tables (faster + cheaper than full SHOW COLUMNS)
        return <<<SCHEMA
TABLE: employees
  id INT PK | employee_code VARCHAR | first_name VARCHAR | last_name VARCHAR
  email VARCHAR | phone VARCHAR | department_id INT FK(departments.id)
  designation VARCHAR | joining_date DATE | salary DECIMAL | manager_id INT
  status ENUM('active','inactive','on_leave') | created_at TIMESTAMP

TABLE: departments
  id INT PK | name VARCHAR | code VARCHAR | description TEXT

TABLE: users
  id INT PK | username VARCHAR | email VARCHAR | role ENUM('admin','hr','manager','finance','employee')
  employee_id INT FK(employees.id) | is_active TINYINT

TABLE: attendance
  id INT PK | employee_id INT FK(employees.id) | date DATE
  status ENUM('present','absent','half_day','late','on_leave')
  check_in TIME | check_out TIME | notes VARCHAR

TABLE: leave_records
  id INT PK | employee_id INT FK(employees.id) | leave_type ENUM('sick','casual','annual','unpaid')
  start_date DATE | end_date DATE | days INT | reason TEXT | status ENUM('approved','pending','rejected')

TABLE: payroll
  id INT PK | employee_id INT FK(employees.id) | month INT | year INT
  basic_salary DECIMAL | allowances DECIMAL | deductions DECIMAL | net_salary DECIMAL
  payment_status ENUM('paid','pending','on_hold') | payment_date DATE

TABLE: down_time
  id INT PK | start_time VARCHAR | end_time VARCHAR | duration DOUBLE (hours)
  reason TEXT | operator TEXT | category TEXT

TABLE: machinedowntime
  id INT PK | MachineName VARCHAR | DowntimeMinutes INT | LogDate DATETIME
  Reason VARCHAR | Shift VARCHAR | RootCause VARCHAR

TABLE: mc_spec
  id INT PK | machine_name VARCHAR | description TEXT | specifications TEXT

TABLE: work_orders
  id INT PK | work_order_no VARCHAR | status ENUM('open','in_progress','completed','cancelled')
  total INT | actual INT | created_at DATETIME

TABLE: alarm_mappings
  id INT PK | alarm_text VARCHAR | severity ENUM('LOW','MEDIUM','HIGH','CRITICAL')
  byte_addr INT | bit_addr INT | description VARCHAR

TABLE: parameter_limits
  id INT PK | parameter_key VARCHAR | min_val DECIMAL | max_val DECIMAL | unit VARCHAR

TABLE: recipes
  id INT PK | name VARCHAR | product_no VARCHAR | plc_record_name VARCHAR
  fast_app_position DECIMAL | fast_app_speed DECIMAL | first_pressing_position DECIMAL
  first_pressing_pressure DECIMAL | first_pressing_speed DECIMAL | curing_time_sec INT

TABLE: critical_spares
  id INT PK | part_name VARCHAR | part_description TEXT | category VARCHAR | uom VARCHAR | quantity INT

TABLE: knowledge_base
  id INT PK | screen_name VARCHAR | feature_name VARCHAR | keyword VARCHAR
  description TEXT | how_to_use TEXT | solution TEXT

TABLE: toolmaster
  id INT PK | Tool_Name VARCHAR | Tool_ID VARCHAR | Tool_Type VARCHAR
  Drawing_No VARCHAR | Cum_Qty INT | Target INT | Diff_Qty INT

TABLE: scheduled_emails
  id INT PK | job_name VARCHAR | report_type VARCHAR | recipients TEXT
  schedule_time VARCHAR | frequency ENUM('once','daily','weekly','monthly')
  last_sent_at DATETIME | is_active TINYINT

TABLE: chat_sessions
  id INT PK | session_uuid VARCHAR | user_id INT FK(users.id) | title VARCHAR | created_at TIMESTAMP

TABLE: chat_messages
  id INT PK | session_id INT FK(chat_sessions.id) | sender ENUM('user','assistant')
  message TEXT | intent VARCHAR | created_at TIMESTAMP

TABLE: audit_logs
  id INT PK | user_id INT | action VARCHAR | description TEXT | created_at TIMESTAMP

JOINS TO USE:
  employees JOIN departments ON employees.department_id = departments.id
  attendance JOIN employees ON attendance.employee_id = employees.id
  payroll JOIN employees ON payroll.employee_id = employees.id
  leave_records JOIN employees ON leave_records.employee_id = employees.id

USEFUL QUERIES & TABLE HINTS:
  Full employee name: CONCAT(first_name,' ',last_name) AS full_name
  Current month: MONTH(CURDATE()), YEAR(CURDATE())
  Net salary total: SUM(net_salary)
  Machine downtime: SELECT MachineName, DowntimeMinutes, Reason FROM machinedowntime
  Alarms: SELECT alarm_text, severity, description FROM alarm_mappings
  Machine specs: SELECT machine_name, plc_model, status FROM mc_spec
SCHEMA;
    }

    public static function isOutOfScope(string $q): bool
    {
        $patterns = [
            '/\b(chief minister|cm of|prime minister|pm of|president of|governor of|mla|mp of)\b/i',
            '/\b(weather|temperature|forecast|rain in|climate)\b/i',
            '/\b(cricket|football|ipl|world cup|score of|match score)\b/i',
            '/\b(tell me a joke|tell a joke|tell me a story|sing a song|write a poem)\b/i',
            '/\b(capital of|largest country|tallest mountain|population of)\b/i',
            '/\b(who is (the )?(actor|actress|singer|player|director|hero|heroine|celebrity|politician))\b/i',
            '/\b(how to cook|recipe for (biryani|cake|chicken|tea|coffee|food))\b/i',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $q)) return true;
        }
        return false;
    }

    // ── Main pipeline entry ───────────────────────────────────────────────────
    public static function process(string $rawQuestion, array $user, array $context = []): array
    {
        // ── Offline Local LLM Pipeline (Zero API Key) ────────────────────────
        if (!GeminiService::isEnabled()) {
            require_once __DIR__ . '/LocalLLMService.php';
            return LocalLLMService::process($rawQuestion, $user, $context);
        }

        // ── Fast Intercept: Block external / general knowledge questions ──────
        if (self::isOutOfScope($rawQuestion)) {
            $conversational = GeminiService::answerConversational($rawQuestion);
            return [
                'type'   => 'out_of_scope',
                'answer' => $conversational,
                'sql'    => null
            ];
        }

        $normalised = self::normalise($rawQuestion);
        $schema     = self::buildSchemaContext();
        $role       = $user['role'] ?? 'employee';

        // ── Step 1: LLM generates SQL ─────────────────────────────────────────
        $sqlResult = GeminiService::generateSQL($normalised, $schema, $role);

        if (!$sqlResult['success']) {
            // Graceful zero-downtime fallback to local IntentService engine!
            require_once __DIR__ . '/IntentService.php';
            try {
                $intentRes = IntentService::parseIntent($normalised, []);
                $ans = $intentRes['direct_answer'] ?? $intentRes['answer'] ?? null;
                if ($ans) {
                    return [
                        'type'    => $intentRes['intent'] ?? 'answer',
                        'answer'  => $ans,
                        'visual'  => $intentRes['visual'] ?? null,
                        'chart'   => $intentRes['chart_data'] ?? null,
                        'records' => $intentRes['records'] ?? null,
                        'fallback'=> true
                    ];
                }
            } catch (\Throwable $e) {
                // proceed to clean user-friendly notice
            }

            return [
                'type'    => 'llm_error',
                'answer'  => "I'm temporarily reconnecting to the database engine. Please try asking again in a moment, or ask about payroll, employees, attendance, machine status, or alarms.",
                'fallback'=> true
            ];
        }

        $generatedSQL = $sqlResult['sql'];

        // ── Step 2: Handle conversational non-SQL responses ───────────────────
        if ($generatedSQL === 'CANNOT_GENERATE' || empty($generatedSQL)) {
            $conversational = GeminiService::answerConversational($rawQuestion);
            return [
                'type'   => 'conversational',
                'answer' => $conversational,
                'sql'    => null
            ];
        }

        // ── Step 3: Security validation ───────────────────────────────────────
        $validation = AstGuardrailFirewall::validateAndEnforce($generatedSQL, $user['id'] ?? null);
        $isPermitted = $validation['permitted'] ?? $validation['safe'] ?? false;

        if (!$isPermitted) {
            Logger::audit($user['id'] ?? 0, 'llm_sql_blocked',
                "Unsafe SQL generated by LLM blocked: $generatedSQL");
            return [
                'type'    => 'security_blocked',
                'answer'  => "🚫 That request was blocked by the security firewall. Only read operations are allowed.",
                'sql'     => $generatedSQL,
                'blocked' => true
            ];
        }

        // ── Step 4: Add safety LIMIT if missing ───────────────────────────────
        if (!preg_match('/\bLIMIT\b/i', $generatedSQL)) {
            $generatedSQL = rtrim($generatedSQL, ';') . ' LIMIT ' . self::MAX_ROWS;
        }

        // ── Step 5: Execute SQL ───────────────────────────────────────────────
        try {
            $pdo  = Database::getConnection();
            $stmt = $pdo->prepare($generatedSQL);
            $stmt->execute();
            $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $total = count($rows);

            // Get full count if large result
            if ($total >= self::MAX_ROWS) {
                try {
                    $countSQL = preg_replace('/SELECT\s+.+?\s+FROM/is', 'SELECT COUNT(*) FROM', $generatedSQL);
                    $countSQL = preg_replace('/\s+LIMIT\s+\d+/i', '', $countSQL);
                    $countSQL = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $countSQL);
                    $cStmt = $pdo->query($countSQL);
                    $total = (int)$cStmt->fetchColumn();
                } catch (\Throwable $e) {
                    // Keep $total = count($rows)
                }
            }

        } catch (\Throwable $e) {
            Logger::audit($user['id'] ?? 0, 'sql_execution_error', $e->getMessage() . " | SQL: $generatedSQL");

            // Try to fix the SQL and retry once
            $fixed = self::fixSQL($generatedSQL, $e->getMessage());
            if ($fixed) {
                try {
                    $stmt  = $pdo->prepare($fixed);
                    $stmt->execute();
                    $rows  = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $total = count($rows);
                    $generatedSQL = $fixed;
                } catch (\Throwable $e2) {
                    return [
                        'type'   => 'sql_error',
                        'answer' => "I couldn't retrieve that data. Try rephrasing — e.g. *\"show all employees\"* or *\"payroll September 2026\"*.",
                        'sql'    => $generatedSQL,
                        'error'  => $e->getMessage()
                    ];
                }
            } else {
                return [
                    'type'   => 'sql_error',
                    'answer' => "I couldn't retrieve that data. Try rephrasing — e.g. *\"show all employees\"* or *\"payroll September 2026\"*.",
                    'sql'    => $generatedSQL,
                    'error'  => $e->getMessage()
                ];
            }
        }

        // ── Step 6: LLM formats the result into natural language ──────────────
        $formattedAnswer = GeminiService::formatResult($rawQuestion, $rows, $generatedSQL, $total);

        // ── Step 7: Check if chart was requested ──────────────────────────────
        $chartData = GeminiService::suggestChart($rawQuestion, $rows);

        // ── Step 8: Log ───────────────────────────────────────────────────────
        Logger::audit($user['id'] ?? 0, 'llm_chat_query',
            "Q: \"$rawQuestion\" | SQL: " . substr($generatedSQL, 0, 120) . " | Rows: $total");

        return [
            'type'     => 'answer',
            'answer'   => $formattedAnswer,
            'sql'      => $generatedSQL,
            'rows'     => $rows,
            'total'    => $total,
            'chart'    => $chartData,
        ];
    }

    // ── Simple SQL fixer for common LLM mistakes ──────────────────────────────
    private static function fixSQL(string $sql, string $error): ?string
    {
        // Fix: unknown column — try to remove GROUP BY or ORDER BY
        if (str_contains($error, 'Unknown column')) {
            $sql = preg_replace('/\s+ORDER\s+BY\s+[^\n]+/i', '', $sql);
            return trim($sql);
        }
        // Fix: Table doesn't exist — common LLM hallucination
        if (str_contains($error, "doesn't exist")) {
            return null; // Can't fix table names
        }
        return null;
    }
}
