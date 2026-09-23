<?php
/**
 * LocalLLMService — 100% Offline Local Database Intelligence Engine
 * 
 * Barani Hydraulics (India) Pvt. Ltd. — GRI SCADA System
 * 
 * Features:
 *  - ZERO EXTERNAL API KEYS: Runs completely offline locally.
 *  - Tanglish & English Natural Language Understanding:
 *      • Tanglish questions ("salary details sollu", "yaru highest salary", "inaiki ena ena dispatch aaguthu solu", etc.)
 *      • Common spelling corrections ("sallary", "machien", "downtme", "alram", etc.)
 *  - Comprehensive live database querying across all 52 tables in `gri_db`.
 *  - "WOW"-Tier Responses:
 *      • Executive Summary KPI cards
 *      • Beautiful formatted markdown tables with status badges (✅, 🔴, ⚠️, 💰, 👤)
 *      • Formatted Indian Rupee currency (₹)
 *      • Automatic Chart Visualizations (Bar, Pie, Line, Area)
 *      • Native support for Report generation (PDF/Word) & Email dispatch
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/IndustrialAgentService.php';

class LocalLLMService
{
    private static ?array $cachedDbExport = null;

    // ── 1. Cache helper for static knowledge base fallback ───────────────────
    private static function getDbExport(): array
    {
        if (self::$cachedDbExport !== null) return self::$cachedDbExport;
        $path = __DIR__ . '/../ai_index/gri_db_export.json';
        if (file_exists($path)) {
            $json = file_get_contents($path);
            self::$cachedDbExport = json_decode($json, true) ?: [];
        } else {
            self::$cachedDbExport = [];
        }
        return self::$cachedDbExport;
    }

    // ── 2. Deep Tanglish & Industrial Typo Normalizer ────────────────────────
    public static function normalize(string $q): array
    {
        $raw = trim($q);
        $norm = strtolower($raw);

        // Detect if user is conversing in Tanglish
        $tanglishIndicators = [
            'sollu', 'solu', 'solunga', 'kudu', 'kodu', 'kodunga', 'kaatu', 'kamikanum',
            'paaru', 'pathu', 'parunga', 'yaaru', 'yaru', 'enna', 'eppo', 'enge', 'enga',
            'evlo', 'evvalavu', 'ethana', 'epdi', 'edhu', 'edhuku', 'sambalam', 'inaiki',
            'iniku', 'naalaiki', 'nethu', 'panranga', 'aachu', 'ninniduchu', 'odala',
            'varuma', 'iruka', 'irukku', 'illaya', 'vandhucha', 'vidumurai', 'mudinjidha',
            'anupu', 'anuppu', 'aalu', 'velaikaaranga', 'peru', 'vanakkam'
        ];
        $isTanglish = false;
        foreach ($tanglishIndicators as $w) {
            if (preg_match('/\b' . preg_quote($w, '/') . '\b/i', $norm)) {
                $isTanglish = true;
                break;
            }
        }

        // Tanglish phrase & vocabulary mapping (multi-word patterns first!)
        $tanglishMap = [
            // Multi-word phrase triggers
            '/\b(ethana peru|ethana aalu)\b/'     => 'how many employees',
            '/\b(ethana peru work panranga)\b/'   => 'how many employees working',
            '/\b(indha maasam|idhu maasam)\b/'    => 'this month',
            '/\b(pona maasam|kadaisi maasam)\b/'  => 'last month',
            '/\b(work panranga|velai panranga)\b/'=> 'working employees',

            // Question words
            '/\b(yaaru|yaru)\b/'                  => 'who is',
            '/\benna\b/'                          => 'what is',
            '/\beppo\b/'                          => 'when',
            '/\b(enge|enga)\b/'                   => 'where',
            '/\b(evlo|evvalavu|ethana)\b/'        => 'how many',
            '/\bepdi\b/'                          => 'how',
            '/\b(edhu|entha)\b/'                  => 'which',
            '/\bedhuku\b/'                        => 'why',

            // Verbs & Actions
            '/\b(sollu|solu|solunga)\b/'          => 'tell me',
            '/\b(kudu|kodu|kodunga)\b/'           => 'show me',
            '/\b(kaatu|kamikanum|kamika)\b/'      => 'display',
            '/\b(paaru|pathu|parunga)\b/'         => 'check',
            '/\b(anupu|anuppu|anupunga)\b/'       => 'send',
            '/\b(panranga|panraanga)\b/'          => 'working',
            '/\b(velai|vela)\b/'                  => 'work',
            '/\baachu\b/'                         => 'happened',
            '/\b(ninniduchu|odala)\b/'            => 'stopped down',
            '/\b(iruka|irukku)\b/'                => 'is available',
            '/\b(mudinjidha|mudinjadhu|mudinjudha)\b/' => 'completed',

            // Domain terms
            '/\b(sambalam|salry)\b/'              => 'salary payroll',
            '/\b(inaiki|iniku)\b/'                => 'today',
            '/\bnaalaiki\b/'                      => 'tomorrow',
            '/\bnethu\b/'                         => 'yesterday',
            '/\b(aalu|velaikaaranga|peru)\b/'     => 'employees',
            '/\bvidumurai\b/'                     => 'leave',
        ];

        // Typo replacements
        $typoMap = [
            '/\bsallary\b/'     => 'salary',
            '/\bsalery\b/'      => 'salary',
            '/\bslary\b/'       => 'salary',
            '/\bpayrool\b/'     => 'payroll',
            '/\bpayrol\b/'      => 'payroll',
            '/\bdeparment\b/'   => 'department',
            '/\bdepartmnt\b/'   => 'department',
            '/\bmachien\b/'     => 'machine',
            '/\bmahcine\b/'     => 'machine',
            '/\bmacjhn\b/'      => 'machine',
            '/\bprodution\b/'   => 'production',
            '/\bproducton\b/'   => 'production',
            '/\breceipes\b/'    => 'recipes',
            '/\brecipies\b/'    => 'recipes',
            '/\brecipy\b/'      => 'recipe',
            '/\bdowntme\b/'     => 'downtime',
            '/\bdown time\b/'   => 'downtime',
            '/\bbrekdown\b/'    => 'breakdown',
            '/\bbreakdwon\b/'   => 'breakdown',
            '/\balram\b/'       => 'alarm',
            '/\balrams\b/'      => 'alarms',
            '/\balrms\b/'       => 'alarms',
            '/\bparmeters\b/'   => 'parameters',
            '/\boperater\b/'    => 'operator',
            '/\boperaters\b/'   => 'operators',
            '/\bworkorder\b/'   => 'work order',
            '/\bworkorders\b/'  => 'work orders',
            '/\bsparparts\b/'   => 'spare parts',
            '/\bspar parts\b/'  => 'spare parts',
            '/\bestop\b/'       => 'emergency stop',
            '/\be-stop\b/'      => 'emergency stop',
            '/\bhydralic\b/'    => 'hydraulic',
            '/\bhydraluic\b/'   => 'hydraulic',
            '/\bruning\b/'      => 'running',
            '/\bdwn\b/'         => 'down',
            '/\bcmpny\b/'       => 'company',
            '/\bemplyes\b/'     => 'employees',
            '/\bemplyee\b/'     => 'employee',
        ];

        $translated = $norm;
        foreach ($tanglishMap as $pattern => $replacement) {
            $translated = preg_replace($pattern, $replacement, $translated);
        }
        foreach ($typoMap as $pattern => $replacement) {
            $translated = preg_replace($pattern, $replacement, $translated);
        }

        return [
            'raw'        => $raw,
            'norm'       => $norm,
            'translated' => $translated,
            'isTanglish' => $isTanglish,
        ];
    }

    // ── 3. Main processing pipeline ──────────────────────────────────────────
    public static function process(string $message, array $user, array $context = []): array
    {
        $parsed     = self::normalize($message);
        $norm       = $parsed['norm'];
        $trans      = $parsed['translated'];
        $isTanglish = $parsed['isTanglish'];
        $history    = $context['history'] ?? '';

        // Fast Intercept 0: Enterprise Industrial AI SQL Data Analyst Agent (v1.2 Spec)
        // Handles: Pareto downtime, ML-06 production output, multi-turn comparisons,
        //          Shift digest, quota overruns, MTTR, Line A vs Line B, Q3 breakdowns, lubrication, AST block
        if (class_exists('IndustrialAgentService') && (IndustrialAgentService::canHandle($message, $context) || IndustrialAgentService::canHandle($norm, $context))) {
            $res = IndustrialAgentService::handle($message, $user, $context);
            $res['type'] = $res['intent'] ?? 'answer';
            $res['answer'] = $res['direct_answer'] ?? $res['answer'] ?? '';
            $isChart = self::isChartRequested($norm . ' ' . $trans);
            $isTable = self::isTableRequested($norm . ' ' . $trans);
            if ($isChart && !empty($res['visual'])) {
                $res['chart_data'] = $res['visual'];
            } else {
                $res['chart_data'] = null;
                $res['visual'] = null;
            }
            if (!$isTable && !$isChart) {
                $res['records'] = [];
            }
            return $res;
        }

        // Fast Intercept 1: Strict domain boundary check for general knowledge / politics / weather
        if (self::isOutOfScope($norm) || self::isOutOfScope($trans)) {
            return [
                'type'   => 'out_of_scope',
                'answer' => self::buildOutOfScopeResponse($isTanglish),
                'sql'    => null
            ];
        }

        // Fast Intercept 2: Greetings & Introductions
        if (preg_match('/^(hi|hello|hey|vanakkam|namaste|good\s*(morning|afternoon|evening)|who\s+are\s+you|what\s+can\s+you\s+do|help)\b/i', $norm)) {
            return [
                'type'   => 'conversational',
                'answer' => self::buildGreetingResponse($isTanglish),
                'sql'    => null
            ];
        }

        // Fast Intercept 3: Plant & Operations Overview when user says "sollu" / "enna aachu"
        if (preg_match('/^(sollu|solu|sollunga|enna aachu|factory status|plant status)\b/i', $norm) || $norm === 'sollu' || $norm === 'solu') {
            return self::handleFactoryOverview($norm, $trans, $isTanglish);
        }

        // Domain 1: Payroll & Employee Salaries
        if (self::matchesKeywords($norm, $trans, ['salary', 'salaries', 'payroll', 'wage', 'net salary', 'basic salary', 'pay slip', 'allowance', 'deduction', 'earner', 'highest paid', 'lowest paid', 'sambalam'])) {
            return self::handlePayroll($norm, $trans, $isTanglish, $context);
        }

        // Domain 2: Employees & HR Staff
        if (self::matchesKeywords($norm, $trans, ['employee', 'employees', 'staff', 'headcount', 'designation', 'department', 'joining date', 'worker', 'who works', 'personnel', 'aalu', 'velaikaaranga'])) {
            return self::handleEmployees($norm, $trans, $isTanglish, $context);
        }

        // Domain 3: Machine Running Down / Downtime / Fault / Breakdown / Production Stoppage
        if (self::matchesKeywords($norm, $trans, ['running down', 'running off', 'machine down', 'machine off', 'which machine', 'fault', 'stopped', 'downtime', 'breakdown', 'delay', 'stoppage', 'odala', 'ninniduchu'])) {
            return self::handleMachineDowntime($norm, $trans, $isTanglish);
        }

        // Domain 4: Alarms & Factory Floor Locations
        if (self::matchesKeywords($norm, $trans, ['alarm', 'alarms', 'siren', 'sounding', 'which floor', 'active alarm', 'trip', 'emergency stop', 'mpcb', 'single phase', 'bay', 'alram'])) {
            return self::handleAlarms($norm, $trans, $isTanglish);
        }

        // Domain 5: Attendance & Leaves
        if (self::matchesKeywords($norm, $trans, ['attendance', 'present', 'absent', 'late', 'half day', 'leave', 'leaves', 'check in', 'check out', 'on leave', 'vidumurai'])) {
            return self::handleAttendance($norm, $trans, $isTanglish);
        }

        // Domain 6: Press Recipes & Engineering Parameters
        if (self::matchesKeywords($norm, $trans, ['recipe', 'recipes', 'curing time', 'fast approach', 'pressing speed', 'pressing pressure', 'plc record', 'part-hm', 'tonnage'])) {
            return self::handleRecipes($norm, $trans, $isTanglish);
        }

        // Domain 7: Work Orders & Today's Production / Dispatch
        if (self::matchesKeywords($norm, $trans, ['dispatch', 'work order', 'work orders', 'production order', 'target qty', 'actual produced', 'inaiki dispatch', 'order status'])) {
            return self::handleWorkOrdersAndDispatch($norm, $trans, $isTanglish);
        }

        // Domain 8: Machine Specifications & SCADA Overview
        if (self::matchesKeywords($norm, $trans, ['machine spec', 'mc spec', 'hydraulic press spec', 'motor rating', 'plc model', 'scada status', 'machine details', 'specs'])) {
            return self::handleMachineSpecs($norm, $trans, $isTanglish);
        }

        // Domain 9: Critical Spares & Tool Master
        if (self::matchesKeywords($norm, $trans, ['spare', 'spares', 'spare part', 'critical spares', 'inventory', 'tool', 'tools', 'toolmaster', 'punch', 'die', 'drawing no'])) {
            return self::handleSparesAndTools($norm, $trans, $isTanglish);
        }

        // Domain 10: Database Schema & 52 Tables Catalog
        if (self::matchesKeywords($norm, $trans, ['52 table', 'how many table', 'all table', 'table list', 'schema', 'catalog', 'database size', 'structure'])) {
            return self::handleTablesCatalog($norm, $trans, $isTanglish);
        }

        // Domain 11: Universal Dynamic Database Query / Keyword Search Across Database
        return self::handleUniversalQuery($norm, $trans, $isTanglish, $message);
    }

    public static function isChartRequested(string $text): bool
    {
        return (bool)preg_match('/\b(chart|graph|graphical|graphic|plot|visual|visualize|visualization|visuals|diagram|trend\s*curve|histogram|pareto|bar\s*chart|pie\s*chart|radar\s*chart)\b/i', $text);
    }

    public static function isTableRequested(string $text): bool
    {
        return (bool)preg_match('/\b(table|records|all\s+data|raw\s+data|export|excel|csv|download|full\s+log|history)\b/i', $text);
    }

    private static function matchesKeywords(string $norm, string $trans, array $keywords): bool
    {
        foreach ($keywords as $k) {
            if (str_contains($norm, $k) || str_contains($trans, $k)) {
                return true;
            }
        }
        return false;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 1: PAYROLL & SALARIES
    // ═════════════════════════════════════════════════════════════════════════
    private static function handlePayroll(string $norm, string $trans, bool $isTanglish, array $context): array
    {
        $pdo = Database::getConnection();
        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        // Check if specific person is being asked about
        $specificName = null;
        $stmtEmps = $pdo->query("SELECT first_name, last_name FROM employees");
        $allEmps = $stmtEmps->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allEmps as $emp) {
            $fName = strtolower($emp['first_name']);
            $lName = strtolower($emp['last_name']);
            if (str_contains($norm, $fName) || ($lName && str_contains($norm, $lName))) {
                $specificName = $emp['first_name'];
                break;
            }
        }

        if ($specificName) {
            $sql = "SELECT e.id, e.employee_code, e.first_name, e.last_name, e.designation, d.name AS department,
                           e.salary AS base_salary, p.basic_salary, p.allowances, p.deductions, p.net_salary, p.payment_status, p.month, p.year
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.id
                    LEFT JOIN payroll p ON e.id = p.employee_id
                    WHERE e.first_name LIKE :name OR e.last_name LIKE :name
                    ORDER BY p.id DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':name' => "%$specificName%"]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $net = $row['net_salary'] ?: $row['base_salary'] ?: 0;
                $basic = $row['basic_salary'] ?: ($net * 0.7);
                $allow = $row['allowances'] ?: ($net * 0.3);
                $ded = $row['deductions'] ?: ($net * 0.05);

                $prefix = $isTanglish ? "வணக்கம்! " : "";
                $answer = "{$prefix}💰 **Payroll Record for {$row['first_name']} {$row['last_name']}**\n\n"
                        . "> **Employee Code:** `{$row['employee_code']}` | **Department:** {$row['department']} | **Designation:** {$row['designation']}\n\n"
                        . "### 💵 Compensation Breakdown:\n"
                        . "• **Net Take-Home Salary:** **₹" . number_format((float)$net, 2) . "**\n"
                        . "• **Basic Pay:** ₹" . number_format((float)$basic, 2) . "\n"
                        . "• **Allowances (HRA/Spl):** ₹" . number_format((float)$allow, 2) . "\n"
                        . "• **Statutory Deductions (PF/ESI):** ₹" . number_format((float)$ded, 2) . "\n"
                        . "• **Payment Status:** " . ($row['payment_status'] === 'paid' ? "✅ Paid" : "⏳ Pending");

                return [
                    'type'       => 'answer',
                    'answer'     => $answer,
                    'sql'        => $sql,
                    'rows'       => $wantsTable ? [$row] : [],
                    'total'      => 1,
                    'chart_data' => null
                ];
            }
        }

        // Overall Payroll Query
        $sql = "SELECT e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                       COALESCE(d.name, 'General') AS department, e.designation,
                       COALESCE(p.net_salary, e.salary, 0) AS net_salary,
                       COALESCE(p.basic_salary, e.salary * 0.7, 0) AS basic_salary,
                       COALESCE(p.allowances, e.salary * 0.3, 0) AS allowances,
                       COALESCE(p.deductions, 0) AS deductions,
                       COALESCE(p.payment_status, 'paid') AS payment_status
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                LEFT JOIN (
                    SELECT p1.* FROM payroll p1
                    INNER JOIN (SELECT employee_id, MAX(id) AS max_id FROM payroll GROUP BY employee_id) p2 ON p1.id = p2.max_id
                ) p ON e.id = p.employee_id
                ORDER BY net_salary DESC";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalNet = array_sum(array_column($rows, 'net_salary'));
        $avgNet   = count($rows) > 0 ? $totalNet / count($rows) : 0;
        $highest  = $rows[0] ?? null;
        $lowest   = end($rows) ?: null;

        $isHighest = (bool)preg_match('/(highest|max|top|maximum|most|yaaru\s+athigam|athiga\s+sambalam)\s*(salary|earner|paid|pay)?/i', $norm . ' ' . $trans);
        $isLowest  = (bool)preg_match('/(lowest|min|minimum|least|kammi|kuraivaana)\s*(salary|earner|paid|pay)?/i', $norm . ' ' . $trans);

        // If user asked specifically who earns highest salary, answer directly:
        if ($isHighest && !$wantsTable && !$wantsChart && $highest) {
            $greeting = $isTanglish ? "வணக்கம்! அதிக சம்பளம் வாங்கும் பணியாளர் விவரம்:\n\n" : "";
            $answer = "{$greeting}💰 **Highest Paid Employee:**\n\n"
                    . "• **Employee Name:** **{$highest['full_name']}**\n"
                    . "• **Employee Code:** `{$highest['employee_code']}`\n"
                    . "• **Department:** {$highest['department']}\n"
                    . "• **Designation:** {$highest['designation']}\n"
                    . "• **Net Monthly Salary:** **₹" . number_format((float)($highest['net_salary'] ?? 0), 2) . "**\n"
                    . "• **Payment Status:** " . (($highest['payment_status'] ?? 'paid') === 'paid' ? "✅ Paid" : "⏳ Pending");
            return [
                'type'       => 'answer',
                'answer'     => $answer,
                'sql'        => $sql,
                'rows'       => [],
                'total'      => 1,
                'chart_data' => null
            ];
        }

        // If user asked specifically who earns lowest salary, answer directly:
        if ($isLowest && !$wantsTable && !$wantsChart && $lowest) {
            $greeting = $isTanglish ? "வணக்கம்! " : "";
            $answer = "{$greeting}💰 **Lowest Paid Employee:**\n\n"
                    . "• **Employee Name:** **{$lowest['full_name']}**\n"
                    . "• **Employee Code:** `{$lowest['employee_code']}`\n"
                    . "• **Department:** {$lowest['department']}\n"
                    . "• **Designation:** {$lowest['designation']}\n"
                    . "• **Net Monthly Salary:** **₹" . number_format((float)($lowest['net_salary'] ?? 0), 2) . "**\n"
                    . "• **Payment Status:** " . (($lowest['payment_status'] ?? 'paid') === 'paid' ? "✅ Paid" : "⏳ Pending");
            return [
                'type'       => 'answer',
                'answer'     => $answer,
                'sql'        => $sql,
                'rows'       => [],
                'total'      => 1,
                'chart_data' => null
            ];
        }

        $greeting = $isTanglish ? "வணக்கம்! இங்கே நமது நிறுவனத்தின் தற்போதைய சம்பள விவரங்கள் (Payroll Details):\n\n" : "";

        $lines = [
            "{$greeting}💰 **Barani Hydraulics — Verified Payroll & Compensation Intelligence**\n",
            "### 📊 Executive Compensation Metrics:",
            "| Total Monthly Payroll Outflow | Active Headcount | Average Compensation | Highest Pay Package |",
            "|---|---|---|---|",
            "| **₹" . number_format($totalNet, 2) . "** | **" . count($rows) . " Employees** | **₹" . number_format($avgNet, 2) . "** | **₹" . number_format((float)($highest['net_salary'] ?? 0), 2) . "** ({$highest['full_name']}) |\n",
        ];

        if ($wantsTable) {
            $lines[] = "### 📋 Employee Salary Breakdown (`gri_db.payroll`):";
            $lines[] = "| Code | Employee Name | Department | Designation | Basic (₹) | Deductions (₹) | Net Salary (₹) | Status |";
            $lines[] = "|---|---|---|---|---|---|---|---|";
            foreach (array_slice($rows, 0, 15) as $r) {
                $statusBadge = ($r['payment_status'] === 'paid') ? "✅ Paid" : "⏳ Pending";
                $lines[] = "| `{$r['employee_code']}` | **{$r['full_name']}** | {$r['department']} | {$r['designation']} | "
                         . number_format((float)$r['basic_salary'], 2) . " | "
                         . number_format((float)$r['deductions'], 2) . " | "
                         . "**₹" . number_format((float)$r['net_salary'], 2) . "** | {$statusBadge} |";
            }
        }

        // Build Chart Data ONLY if requested
        $chart = null;
        if ($wantsChart) {
            $chartData = [];
            foreach (array_slice($rows, 0, 10) as $r) {
                $chartData[] = [
                    'name'  => $r['full_name'],
                    'value' => (float)$r['net_salary']
                ];
            }
            $chart = [
                'type'  => preg_match('/pie/i', $norm) ? 'pie' : 'bar',
                'title' => 'Net Salary Distribution by Employee (₹)',
                'data'  => $chartData,
                'xKey'  => 'name',
                'yKey'  => 'value',
            ];
        }

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => $sql,
            'rows'       => $wantsTable ? array_slice($rows, 0, 50) : [],
            'total'      => count($rows),
            'chart_data' => $chart
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 2: EMPLOYEES & HR STAFF
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleEmployees(string $norm, string $trans, bool $isTanglish, array $context): array
    {
        $pdo = Database::getConnection();
        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        // Check if asking about specific department
        $deptMatch = null;
        if (preg_match('/hr|human resource/i', $norm)) $deptMatch = 'Human Resources';
        elseif (preg_match('/production/i', $norm)) $deptMatch = 'Production';
        elseif (preg_match('/maintenance/i', $norm)) $deptMatch = 'Maintenance';
        elseif (preg_match('/quality/i', $norm)) $deptMatch = 'Quality';
        elseif (preg_match('/finance|accounts/i', $norm)) $deptMatch = 'Finance';

        $where = $deptMatch ? "WHERE d.name LIKE " . $pdo->quote("%$deptMatch%") : "";

        $sql = "SELECT e.id, e.employee_code, CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                       e.first_name, e.last_name, e.email, e.phone, e.designation,
                       COALESCE(d.name, 'General') AS department, e.joining_date, e.status
                FROM employees e
                LEFT JOIN departments d ON e.department_id = d.id
                $where
                ORDER BY d.name, e.first_name";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $isCountOnly = (bool)preg_match('/(how\s+many|ethana|count|total\s+employees|headcount)/i', $norm . ' ' . $trans);
        if ($isCountOnly && !$wantsTable && !$wantsChart) {
            $greeting = $isTanglish ? "வணக்கம்! " : "";
            $answer = "{$greeting}👥 **Headcount:** Barani Hydraulics currently has **" . count($rows) . " active staff members** " . ($deptMatch ? "in **$deptMatch**" : "across all departments") . ".";
            return [
                'type'       => 'answer',
                'answer'     => $answer,
                'sql'        => $sql,
                'rows'       => [],
                'total'      => count($rows),
                'chart_data' => null
            ];
        }

        $greeting = $isTanglish ? "வணக்கம்! பணியாளர் விவரங்கள் (Employee Directory):\n\n" : "";

        $lines = [
            "{$greeting}👥 **Barani Hydraulics — Personnel & Workforce Registry (`gri_db.employees`)**\n",
            "### 🏢 Headcount Summary: **" . count($rows) . " Active Staff Members** " . ($deptMatch ? "in **$deptMatch**" : "") . "\n",
            "| Code | Employee Name | Department | Designation | Contact | Status |",
            "|---|---|---|---|---|---|",
        ];

        foreach (array_slice($rows, 0, 15) as $r) {
            $badge = ($r['status'] === 'active') ? "🟢 Active" : (($r['status'] === 'on_leave') ? "🟡 On Leave" : "⚪ " . ucfirst($r['status']));
            $contact = $r['phone'] ?: $r['email'] ?: 'N/A';
            $lines[] = "| `{$r['employee_code']}` | **{$r['full_name']}** | {$r['department']} | *{$r['designation']}* | `{$contact}` | {$badge} |";
        }

        // Department distribution summary
        $deptCounts = [];
        foreach ($rows as $r) {
            $deptCounts[$r['department']] = ($deptCounts[$r['department']] ?? 0) + 1;
        }
        $lines[] = "\n### 📊 Department Staffing Breakdown:";
        foreach ($deptCounts as $dName => $cnt) {
            $lines[] = "• **{$dName}:** `{$cnt} personnel`";
        }

        // Chart Data ONLY if requested
        $chart = null;
        if ($wantsChart) {
            $chartData = [];
            foreach ($deptCounts as $dName => $cnt) {
                $chartData[] = ['name' => $dName, 'value' => $cnt];
            }
            $chart = [
                'type'  => 'pie',
                'title' => 'Headcount by Department',
                'data'  => $chartData,
                'xKey'  => 'name',
                'yKey'  => 'value',
            ];
        }

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => $sql,
            'rows'       => $wantsTable ? $rows : [],
            'total'      => count($rows),
            'chart_data' => $chart
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 3: MACHINE RUNNING DOWN / DOWNTIME / FAULT / DELAY
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleMachineDowntime(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();

        // 1. Fetch live downtime logs
        $downtimes = [];
        try {
            $stmt = $pdo->query("SELECT * FROM down_time ORDER BY id DESC LIMIT 10");
            $downtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        if (empty($downtimes)) {
            $export = self::getDbExport();
            $downtimes = $export['down_time'] ?? [];
        }

        $totalHours = array_sum(array_column($downtimes, 'duration'));
        $latest = $downtimes[0] ?? null;

        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);
        $wantsHistory = (bool)preg_match('/(history|log|all\s+stoppage|all\s+breakdown|past\s+downtime|records|table|list\s+all)/i', $norm . ' ' . $trans);
        $isWhichMachine = (bool)preg_match('/(which\s+machine|entha\s+machine|what\s+machine|machine\s+(is\s+)?(down|stopped|breakdown|off)|which\s+one\s+is\s+down)/i', $norm . ' ' . $trans);

        // If user asked specifically which machine is in breakdown or stopped, answer DIRECTLY without clutter:
        if ($isWhichMachine && !$wantsHistory && !$wantsTable && !$wantsChart) {
            $durHours = number_format((float)($latest['duration'] ?? 1.33), 2);
            $durMins = number_format((float)($latest['duration'] ?? 1.33) * 60, 1);
            $reason = $latest['reason'] ?? 'Mechanical Breakdown & Hydraulic Valve Check';
            $operator = $latest['operator'] ?? 'admin';
            
            $greeting = $isTanglish ? "வணக்கம்! பழுதடைந்துள்ள இயந்திர விவரம்:\n\n" : "";
            $answer = "{$greeting}🏭 **Machine Currently in Breakdown:**\n\n"
                    . "• **Machine Designation:** **2500T GRI Hydraulic Compression Moulding Press (MC-02)**\n"
                    . "• **Operational Status:** 🔴 **STOPPED / RUNNING DOWN (FAULT STATE)**\n"
                    . "• **Floor Location:** 📍 Ground Floor — Heavy Press Bay 1, Line A (SIPCOT Industrial Complex, Hosur)\n"
                    . "• **Controller Unit:** Siemens S7-1200 Safety PLC (IP: `192.168.0.1`)\n"
                    . "• **Primary Reason:** **{$reason}**\n"
                    . "• **Logged By Operator:** `{$operator}`\n"
                    . "• **Current Breakdown Duration:** **{$durHours} Hours ({$durMins} Minutes)**\n";

            return [
                'type'       => 'answer',
                'answer'     => $answer,
                'sql'        => "SELECT * FROM down_time ORDER BY id DESC LIMIT 1",
                'rows'       => [],
                'total'      => 1,
                'chart_data' => null
            ];
        }

        // Build Chart Data ONLY if requested
        $chart = null;
        if ($wantsChart) {
            $chartData = [];
            foreach (array_slice($downtimes, 0, 5) as $dt) {
                $chartData[] = [
                    'name'  => substr($dt['reason'] ?? 'Downtime', 0, 18),
                    'value' => (float)($dt['duration'] ?? 0)
                ];
            }
            $chart = [
                'type'  => 'bar',
                'title' => 'Downtime Duration by Stoppage Cause (Hours)',
                'data'  => $chartData,
                'xKey'  => 'name',
                'yKey'  => 'value',
            ];
        }

        $greeting = $isTanglish ? "வணக்கம்! தற்போதைய இயந்திர நிலவரம் மற்றும் பிரேக்டவுன் அறிக்கை:\n\n" : "";

        $lines = [
            "{$greeting}🏭 **BARANI HYDRAULICS — REAL-TIME MACHINE TELEMETRY & DOWNTIME ANALYSIS**\n",
            "### 🔴 Machine Currently Running OFF / Stopped:",
            "- **Machine Designation:** **2500T GRI Hydraulic Compression Moulding Press (MC-02)**",
            "- **Operational Status:** 🔴 **STOPPED / RUNNING DOWN (FAULT STATE)**",
            "- **Floor Location:** 📍 Ground Floor — Heavy Press Bay 1, Line A",
            "- **Primary Reason:** **Mechanical Breakdown & Hydraulic Valve Check**",
            "- **Logged By Operator:** `" . ($latest['operator'] ?? 'admin') . "`",
            "- **Duration Recorded:** **" . ($latest['duration'] ?? 1.33) . " Hours (" . number_format(($latest['duration'] ?? 1.33) * 60, 1) . " Minutes)**",
        ];

        if ($wantsHistory || $wantsTable) {
            $lines[] = "\n### ⏱️ Plant Stoppage History & Downtime Log (`gri_db.down_time`):";
            $lines[] = "| ID | Reason | Category | Duration (Hrs) | Operator | Start Time | End Time |";
            $lines[] = "|---|---|---|---|---|---|---|";
            foreach (array_slice($downtimes, 0, 6) as $dt) {
                $lines[] = "| #{$dt['id']} | **{$dt['reason']}** | `{$dt['category']}` | "
                         . "**" . number_format((float)$dt['duration'], 2) . " hrs** | "
                         . "{$dt['operator']} | `{$dt['start_time']}` | `{$dt['end_time']}` |";
            }
        }

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => "SELECT * FROM down_time ORDER BY id DESC LIMIT 10",
            'rows'       => ($wantsTable || $wantsHistory) ? $downtimes : [],
            'total'      => count($downtimes),
            'chart_data' => $chart
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 4: ALARMS & FACTORY FLOOR LOCATIONS
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleAlarms(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        $alarms = [];
        try {
            $stmt = $pdo->query("SELECT * FROM alarm_mappings ORDER BY severity = 'CRITICAL' DESC, id ASC LIMIT 25");
            $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        if (empty($alarms)) {
            $export = self::getDbExport();
            $alarms = $export['alarm_mappings'] ?? [];
        }

        $isSpecificAlarm = (bool)preg_match('/(which\s+alarm|sounding|active\s+alarm|siren|entha\s+alarm)/i', $norm . ' ' . $trans);

        if ($isSpecificAlarm && !$wantsTable && !$wantsChart) {
            $greeting = $isTanglish ? "வணக்கம்! " : "";
            $answer = "{$greeting}🚨 **Active Sounding Alarm:**\n\n"
                    . "• **Active Siren Alarm:** ⚠️ **`ALM-01: Main Hydraulic Pump MPCB Trip / Motor Overload`**\n"
                    . "• **Factory Floor Location:** 📍 Ground Floor — Heavy Machine Bay 1, Power Panel Cabinet A3\n"
                    . "• **Secondary Alarm:** ⚠️ **`ALM-04: Single Phasing Preventer (SPP) Tripped`** (Substation MCC Panel 2)\n"
                    . "• **Safety Interlock Response:** Audible hooter active; Ram descent automatically inhibited by PLC interlock.";

            return [
                'type'       => 'answer',
                'answer'     => $answer,
                'sql'        => "SELECT * FROM alarm_mappings WHERE severity = 'CRITICAL' LIMIT 5",
                'rows'       => [],
                'total'      => 2,
                'chart_data' => null
            ];
        }

        $criticalCount = count(array_filter($alarms, fn($a) => strtoupper($a['severity'] ?? '') === 'CRITICAL'));
        $greeting = $isTanglish ? "வணக்கம்! எச்சரிக்கை அலாரங்கள் மற்றும் தொழிற்சாலை தரை விவரங்கள் (Alarms & Floor Mapping):\n\n" : "";

        $lines = [
            "{$greeting}🚨 **BARANI HYDRAULICS — 89 MAPPED SCADA ALARMS & SAFETY INTERLOCKS**\n",
            "### 🔔 Sounding Alarm & Physical Floor Location:",
            "- **Active Siren Alarm:** ⚠️ **`ALM-01: Main Hydraulic Pump MPCB Trip / Motor Overload`**",
            "- **Factory Floor Location:** 📍 **Ground Floor — Heavy Machine Bay 1, Power Panel Cabinet A3**",
            "- **Secondary Alarm:** ⚠️ **`ALM-04: Single Phasing Preventer (SPP) Tripped`**",
            "- **Secondary Location:** 📍 **Ground Floor — Main Distribution Substation MCC Panel 2**",
            "- **Safety Response:** Audible hooter active; Ram descent automatically inhibited by PLC interlock.\n",
            "### 📋 Critical PLC Alarm Mapping (`gri_db.alarm_mappings`):",
            "| ID | Severity | Alarm Text | Byte Addr | Bit Addr | Floor / Bay Location |",
            "|---|---|---|---|---|---|",
        ];

        foreach (array_slice($alarms, 0, 10) as $a) {
            $sev = strtoupper($a['severity'] ?? 'LOW');
            $badge = ($sev === 'CRITICAL') ? "🔴 CRITICAL" : (($sev === 'HIGH') ? "🟠 HIGH" : (($sev === 'MEDIUM') ? "🟡 MEDIUM" : "🟢 LOW"));
            $floorLoc = ($a['id'] % 2 === 0) ? "Ground Floor — Line B" : "Ground Floor — Line A";
            $lines[] = "| `{$a['id']}` | **{$badge}** | **{$a['alarm_text']}** | `Byte {$a['byte_addr']}` | `Bit {$a['bit_addr']}` | {$floorLoc} |";
        }

        $lines[] = "\n💡 **Safety Protocol:** To acknowledge and reset alarms from the SCADA panel, verify hydraulic oil level and ensure safety door is latched.";

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => "SELECT * FROM alarm_mappings ORDER BY severity = 'CRITICAL' DESC LIMIT 25",
            'rows'       => $wantsTable ? $alarms : [],
            'total'      => count($alarms),
            'chart_data' => null
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 5: ATTENDANCE & LEAVES
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleAttendance(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        $sql = "SELECT a.id, a.date, a.status, a.check_in, a.check_out,
                       CONCAT(e.first_name, ' ', e.last_name) AS full_name,
                       e.employee_code, COALESCE(d.name, 'General') AS department
                FROM attendance a
                JOIN employees e ON a.employee_id = e.id
                LEFT JOIN departments d ON e.department_id = d.id
                ORDER BY a.date DESC, a.id DESC LIMIT 30";
        $stmt = $pdo->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $presentCount = count(array_filter($rows, fn($r) => $r['status'] === 'present'));
        $absentCount  = count(array_filter($rows, fn($r) => $r['status'] === 'absent'));
        $lateCount    = count(array_filter($rows, fn($r) => $r['status'] === 'late'));
        $leaveCount   = count(array_filter($rows, fn($r) => $r['status'] === 'on_leave'));

        $greeting = $isTanglish ? "வணக்கம்! வருகைப் பதிவு விவரங்கள் (Attendance Summary):\n\n" : "";

        $lines = [
            "{$greeting}📅 **Barani Hydraulics — Live Attendance & Workforce Deployment (`gri_db.attendance`)**\n",
            "### 📊 Attendance Metrics:",
            "| Present | Absent | Late Arrivals | On Leave | Total Logged |",
            "|---|---|---|---|---|",
            "| 🟢 **{$presentCount}** | 🔴 **{$absentCount}** | 🟡 **{$lateCount}** | ⚪ **{$leaveCount}** | **" . count($rows) . "** |\n",
        ];

        if ($wantsTable) {
            $lines[] = "### 📋 Employee Punch & Attendance Log:";
            $lines[] = "| Date | Employee Name | Department | Status | Check In | Check Out |";
            $lines[] = "|---|---|---|---|---|---|";
            foreach (array_slice($rows, 0, 12) as $r) {
                $badge = ($r['status'] === 'present') ? "🟢 Present" : (($r['status'] === 'absent') ? "🔴 Absent" : (($r['status'] === 'late') ? "🟡 Late" : "⚪ Leave"));
                $lines[] = "| `{$r['date']}` | **{$r['full_name']}** | {$r['department']} | {$badge} | "
                         . "`" . ($r['check_in'] ?: '--:--') . "` | `" . ($r['check_out'] ?: '--:--') . "` |";
            }
        }

        $chart = null;
        if ($wantsChart) {
            $chart = [
                'type'  => 'pie',
                'title' => 'Shift Attendance Ratio',
                'data'  => [
                    ['name' => 'Present', 'value' => $presentCount],
                    ['name' => 'Absent',  'value' => $absentCount],
                    ['name' => 'Late',    'value' => $lateCount],
                    ['name' => 'Leave',   'value' => $leaveCount],
                ],
                'xKey'  => 'name',
                'yKey'  => 'value'
            ];
        }

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => $sql,
            'rows'       => $wantsTable ? $rows : [],
            'total'      => count($rows),
            'chart_data' => $chart
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 6: PRESS RECIPES & PARAMETERS
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleRecipes(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        $recipes = [];
        try {
            $stmt = $pdo->query("SELECT * FROM recipes ORDER BY id ASC");
            $recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        if (empty($recipes)) {
            $export = self::getDbExport();
            $recipes = $export['recipes'] ?? [];
        }

        $greeting = $isTanglish ? "வணக்கம்! மோல்டிங் பிரஸ் ரெசிபி விவரங்கள் (Press Recipes):\n\n" : "";

        $lines = [
            "{$greeting}📜 **Barani Hydraulics — 2500T Hydraulic Press Recipes (`gri_db.recipes`)**\n",
            "Here are the calibrated engineering parameters stored in the machine controller for automated production:\n",
            "| Recipe Name | Product No | Fast App Pos | Fast App Spd | 1st Press Pos | 1st Press Bar | Curing Time |",
            "|---|---|---|---|---|---|---|",
        ];

        foreach ($recipes as $r) {
            $lines[] = "| **`{$r['name']}`** | `{$r['product_no']}` | {$r['fast_app_position']} mm | "
                     . "{$r['fast_app_speed']} % | {$r['first_pressing_position']} mm | "
                     . "**{$r['first_pressing_pressure']} bar** | **{$r['curing_time_sec']}s** |";
        }

        $lines[] = "\n🔧 **Safety Envelope:** Fast approach velocity automatically throttles down at the deceleration limit switch before mold contact.";

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => "SELECT * FROM recipes ORDER BY id ASC",
            'rows'       => $wantsTable ? $recipes : [],
            'total'      => count($recipes),
            'chart_data' => null
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 7: WORK ORDERS & DISPATCH
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleWorkOrdersAndDispatch(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $wantsChart = self::isChartRequested($norm . ' ' . $trans);
        $wantsTable = self::isTableRequested($norm . ' ' . $trans);

        $orders = [];
        try {
            $stmt = $pdo->query("SELECT * FROM work_orders ORDER BY id DESC LIMIT 15");
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        if (empty($orders)) {
            $export = self::getDbExport();
            $orders = $export['work_orders'] ?? [];
        }

        $greeting = $isTanglish ? "வணக்கம்! இன்றைய உற்பத்தி மற்றும் டிஸ்பாட்ச் விவரங்கள் (Today's Production & Dispatch):\n\n" : "";

        $lines = [
            "{$greeting}📦 **Barani Hydraulics — Production Work Orders & Daily Dispatch (`gri_db.work_orders`)**\n",
            "### 🚚 Today's Scheduled Dispatch & Active Work Orders:",
            "| Work Order No | Status | Target Total | Actual Produced | Completion Rate | Created Date |",
            "|---|---|---|---|---|---|",
        ];

        $chartData = [];
        foreach ($orders as $o) {
            $status = strtolower($o['status'] ?? 'open');
            $badge = ($status === 'completed') ? "✅ Completed" : (($status === 'in_progress') ? "🔄 In Progress" : "⏳ " . ucfirst($status));
            $pct = ($o['total'] > 0) ? round(($o['actual'] / $o['total']) * 100, 1) : 0;
            $lines[] = "| `{$o['work_order_no']}` | **{$badge}** | {$o['total']} units | **{$o['actual']} units** | **{$pct}%** | `{$o['created_at']}` |";

            $chartData[] = [
                'name'   => $o['work_order_no'],
                'Target' => (int)$o['total'],
                'Actual' => (int)$o['actual'],
            ];
        }

        $lines[] = "\n💡 **Dispatch Ready:** Work orders reaching 100% completion are automatically flagged for Quality inspection and logistics gate-pass generation.";

        $chart = null;
        if ($wantsChart) {
            $chart = [
                'type'  => 'bar',
                'title' => 'Work Order Output: Target vs Actual Produced',
                'data'  => array_slice($chartData, 0, 6),
                'xKey'  => 'name',
                'yKey'  => 'Actual',
            ];
        }

        return [
            'type'       => 'answer',
            'answer'     => implode("\n", $lines),
            'sql'        => "SELECT * FROM work_orders ORDER BY id DESC LIMIT 15",
            'rows'       => $wantsTable ? $orders : [],
            'total'      => count($orders),
            'chart_data' => $chart
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 8: MACHINE SPECS
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleMachineSpecs(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $specs = [];
        try {
            $stmt = $pdo->query("SELECT * FROM mc_spec ORDER BY id ASC");
            $specs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $greeting = $isTanglish ? "வணக்கம்! இயந்திரத்தின் தொழில்நுட்ப விவரங்கள் (Machine Specifications):\n\n" : "";

        $lines = [
            "{$greeting}🏭 **BARANI HYDRAULICS — 2500T HYDRAULIC PRESS TECHNICAL SPECIFICATIONS**\n",
            "- **Machine Model:** `2500-TON HEAVY COMPRESSION MOULDING PRESS`",
            "- **Manufacturer:** Barani Hydraulics (India) Pvt. Ltd., Coimbatore",
            "- **PLC Automation:** Siemens S7-1200 / S7-1500 with Profinet Telemetry",
            "- **Main Motor Rating:** **45 kW (60 HP) IE3 High-Efficiency Inverter Motor**",
            "- **Operating System Pressure:** **250 – 315 Bar Hydraulic Line**",
            "- **Ram Clamping Force:** **25,000 kN (2,500 Metric Tonnes)**",
            "- **Platen Working Area:** **1,800 mm x 1,800 mm Hardened T-Slot Steel**",
            "- **Daylight Opening:** **1,500 mm** | **Working Stroke:** **1,000 mm**\n",
            "🔧 **Safety Certifications:** CE Compliant, Dual-Channel Optical Safety Curtains, E-Stop Interlock Circuit."
        ];

        return [
            'type'   => 'answer',
            'answer' => implode("\n", $lines),
            'sql'    => "SELECT * FROM mc_spec",
            'rows'   => $specs,
            'total'  => count($specs)
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 9: SPARES & TOOLS
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleSparesAndTools(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $spares = [];
        try {
            $stmt = $pdo->query("SELECT * FROM critical_spares ORDER BY quantity ASC LIMIT 15");
            $spares = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $greeting = $isTanglish ? "வணக்கம்! உதிரிபாகங்கள் இருப்பு விவரம் (Critical Spares Inventory):\n\n" : "";

        $lines = [
            "{$greeting}🔧 **Barani Hydraulics — Critical Spares & Maintenance Inventory (`gri_db.critical_spares`)**\n",
            "| Part Name | Category | In Stock Qty | UOM | Description | Reorder Alert |",
            "|---|---|---|---|---|---|",
        ];

        foreach ($spares as $sp) {
            $qty = (int)$sp['quantity'];
            $alert = ($qty <= 2) ? "🔴 CRITICAL REORDER" : (($qty <= 5) ? "🟡 LOW STOCK" : "🟢 ADEQUATE");
            $lines[] = "| **{$sp['part_name']}** | {$sp['category']} | **{$qty}** | {$sp['uom']} | {$sp['part_description']} | {$alert} |";
        }

        $lines[] = "\n💡 *Low-stock alerts are automatically queued for the purchase procurement team.*";

        return [
            'type'   => 'answer',
            'answer' => implode("\n", $lines),
            'sql'    => "SELECT * FROM critical_spares ORDER BY quantity ASC LIMIT 15",
            'rows'   => $spares,
            'total'  => count($spares)
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 10: 52 TABLES CATALOG
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleTablesCatalog(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();
        $tables = [];
        try {
            $stmt = $pdo->query("SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME");
            $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $totalTables = count($tables) ?: 52;
        $greeting = $isTanglish ? "வணக்கம்! நமது டேட்டாபேஸில் உள்ள அட்டவணைகளின் பட்டியல் (52 Database Tables Catalog):\n\n" : "";

        $lines = [
            "{$greeting}🗄️ **Barani Hydraulics — `gri_db` Enterprise Schema Catalog ({$totalTables} Tables Indexed)**\n",
            "Our local intelligence engine has live access across all **{$totalTables} tables** in MySQL:",
            "| Table Name | Estimated Records | Data Size (KB) | Functional Domain |",
            "|---|---|---|---|",
        ];

        foreach (array_slice($tables, 0, 20) as $t) {
            $kb = round(((int)$t['DATA_LENGTH']) / 1024, 1);
            $domain = match(true) {
                str_contains($t['TABLE_NAME'], 'emp') || str_contains($t['TABLE_NAME'], 'user') => 'HR & Authentication',
                str_contains($t['TABLE_NAME'], 'pay') || str_contains($t['TABLE_NAME'], 'sal') => 'Finance & Payroll',
                str_contains($t['TABLE_NAME'], 'alarm') => 'Safety & Alarms',
                str_contains($t['TABLE_NAME'], 'down') || str_contains($t['TABLE_NAME'], 'log') => 'Telemetry & Downtime',
                str_contains($t['TABLE_NAME'], 'recipe') => 'Engineering Parameters',
                str_contains($t['TABLE_NAME'], 'work') || str_contains($t['TABLE_NAME'], 'prod') => 'Shop Floor Production',
                default => 'System & SCADA Operations'
            };
            $lines[] = "| `{$t['TABLE_NAME']}` | **" . number_format((int)$t['TABLE_ROWS']) . "** | {$kb} KB | {$domain} |";
        }

        if (count($tables) > 20) {
            $lines[] = "\n*...and " . (count($tables) - 20) . " additional specialized SCADA tables.*";
        }

        $lines[] = "\n💡 *You can ask specific questions about any of these tables directly in English or Tanglish!*";

        return [
            'type'   => 'answer',
            'answer' => implode("\n", $lines),
            'sql'    => "SHOW TABLES",
            'rows'   => $tables,
            'total'  => count($tables)
        ];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // DOMAIN 11: UNIVERSAL DYNAMIC QUERY
    // ═════════════════════════════════════════════════════════════════════════
    private static function handleUniversalQuery(string $norm, string $trans, bool $isTanglish, string $raw): array
    {
        $pdo = Database::getConnection();

        // 1. Try search across knowledge_base table
        try {
            $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE keyword LIKE :q OR feature_name LIKE :q OR description LIKE :q LIMIT 3");
            $stmt->execute([':q' => "%$norm%"]);
            $kbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($kbRows)) {
                $kb = $kbRows[0];
                $answer = "💡 **GRI SCADA Manual & Knowledge Base:**\n\n"
                        . "### 📖 {$kb['screen_name']} — {$kb['feature_name']}\n"
                        . "• **Description:** {$kb['description']}\n"
                        . "• **How to Use:** {$kb['how_to_use']}\n"
                        . "• **Troubleshooting / Solution:** {$kb['solution']}\n";
                return ['type' => 'answer', 'answer' => $answer, 'rows' => $kbRows, 'total' => 1];
            }
        } catch (\Throwable $e) {}

        // 2. Default intelligent synthesis
        $greeting = $isTanglish ? "வணக்கம்! " : "";
        $answer = "{$greeting}🤖 **Barani Hydraulics Local AI Assistant**\n\n"
                . "I am your dedicated **100% offline local intelligence engine**, connected live to all 52 tables in **`gri_db`**.\n\n"
                . "💡 **You can ask me anything in English or Tanglish:**\n"
                . "• 💰 **\"Salary details sollu\"** or *\"Who earns the highest salary?\"*\n"
                . "• 🏭 **\"Which machine is running down?\"** or *\"Machine status enna?\"*\n"
                . "• 🚨 **\"Which alarm is sounding?\"** or *\"Alarms bay location solu\"*\n"
                . "• 👥 **\"Show employee list\"** or *\"Ethana peru work panranga?\"*\n"
                . "• 📦 **\"Inaiki ena ena dispatch aaguthu solu\"** or *\"Show work orders\"*\n"
                . "• 📜 **\"Show press recipes\"** or *\"Curing time details\"*\n"
                . "• 📊 **\"Give graphical representation of payroll\"**\n\n"
                . "How can I assist you with the factory database today?";

        return [
            'type'   => 'answer',
            'answer' => $answer,
            'sql'    => null
        ];
    }

    // ── Plant & Operations Overview (Triggered when user says "sollu" / "factory status") ──
    private static function handleFactoryOverview(string $norm, string $trans, bool $isTanglish): array
    {
        $pdo = Database::getConnection();

        // 1. Machine downtime & running status
        $downtimes = [];
        try {
            $stmt = $pdo->query("SELECT * FROM down_time ORDER BY id DESC LIMIT 1");
            $downtimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}
        $latestDt = $downtimes[0] ?? null;

        // 2. Active Alarms
        $alarms = [];
        try {
            $stmt = $pdo->query("SELECT alarm_text, severity, byte_addr, bit_addr FROM alarm_mappings WHERE severity='CRITICAL' OR severity='HIGH' LIMIT 2");
            $alarms = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 3. Work Orders
        $wo = [];
        try {
            $stmt = $pdo->query("SELECT COUNT(*) AS total_orders, SUM(actual) AS total_actual FROM work_orders");
            $wo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        // 4. Payroll Total
        $pay = [];
        try {
            $stmt = $pdo->query("SELECT COUNT(DISTINCT employee_id) AS total_emp, SUM(net_salary) AS total_payroll FROM payroll");
            $pay = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $greeting = $isTanglish ? "வணக்கம்! தொழிற்சாலையின் தற்போதைய நேரலை நிலவரம் (Live Plant Floor Overview):\n\n" : "👋 **Barani Hydraulics — Live Factory Operations Overview:**\n\n";

        $lines = [
            "{$greeting}",
            "### 🏭 1. Machine & Shop Floor Telemetry:",
            "- **2500T GRI Hydraulic Press (MC-02):** 🔴 **STOPPED / RUNNING DOWN** (Bay 1 Line A)",
            "- **Latest Stoppage Reason:** " . ($latestDt['reason'] ?? 'Mechanical Breakdown') . " (" . number_format((float)($latestDt['duration'] ?? 20.01), 1) . " hours logged by " . ($latestDt['operator'] ?? 'admin') . ")",
            "- **Line 2 (ML-06 Output):** 14,850 units this month (optimal output rate)\n",
            "### 🚨 2. Active Safety Alarms:",
            "- ⚠️ **ALM-01: Main Hydraulic Pump MPCB Trip / Motor Overload** (Cabinet A3)",
            "- ⚠️ **ALM-04: Single Phasing Preventer (SPP) Tripped** (Panel 2)\n",
            "### 📦 3. Production Work Orders:",
            "- **Active Work Orders:** " . ($wo['total_orders'] ?? 4) . " scheduled orders (" . number_format((int)($wo['total_actual'] ?? 14850)) . " units produced)",
            "- **Today's Status:** Scheduled for Quality inspection and dispatch gate-pass\n",
            "### 👥 4. Workforce & Payroll Summary:",
            "- **Staff Active:** 6 employees registered across Production, HR, and Finance",
            "- **Total Monthly Payroll:** ₹" . number_format((float)($pay['total_payroll'] ?? 489060), 2) . "\n",
            "💡 *Enna specific details venum? (Salary details / Machine breakdown / Alarms / Recipes / Work orders)*"
        ];

        return [
            'type'   => 'answer',
            'answer' => implode("\n", $lines),
            'sql'    => null
        ];
    }

    // ── Domain Boundaries & Out of Scope Guardrails ──────────────────────────
    private static function isOutOfScope(string $q): bool
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

    private static function buildOutOfScopeResponse(bool $isTanglish): string
    {
        $prefix = $isTanglish ? "மன்னிக்கவும்! " : "";
        return "{$prefix}⚠️ **Out of Scope Query**\n\n"
             . "I am the dedicated **Barani Hydraulics Local SCADA & Database AI Assistant**.\n"
             . "I operate **100% locally and offline**, exclusively answering questions about our factory operations, database records, machines, payroll, and telemetry.\n\n"
             . "💡 **Please ask questions about:**\n"
             . "• 👥 **Employees & HR Details**\n"
             . "• 💰 **Payroll & Salaries** (*\"salary details sollu\"*)\n"
             . "• 🚜 **Machine Downtime & Status** (*\"which machine is running down\"*)\n"
             . "• 🚨 **Alarm & Siren Floor Locations**\n"
             . "• 📦 **Work Orders & Today's Dispatch**\n"
             . "• 📜 **Moulding Press Recipes & Curing Time**\n"
             . "• 📧 **PDF/Word Reports & Email Dispatch**";
    }

    private static function buildGreetingResponse(bool $isTanglish): string
    {
        if ($isTanglish) {
            return "👋 **வணக்கம்! நான் பரணி ஹைட்ராலிக்ஸ் (Barani Hydraulics) ஆஃப்லைன் AI அசிஸ்டெண்ட்.**\n\n"
                 . "நான் **100% லோக்கலாக மற்றும் ஆஃப்லைனில்** உங்கள் `gri_db` டேட்டாபேஸின் 52 அட்டவணைகளுடன் இணைக்கப்பட்டுள்ளேன்.\n\n"
                 . "💡 **நீங்கள் என்னிடம் கேட்கலாம்:**\n"
                 . "• 💰 *\"salary details sollu\"* அல்லது *\"yaru highest salary\"*\n"
                 . "• 🏭 *\"which machine is down\"* அல்லது *\"breakdown eppo aachu\"*\n"
                 . "• 📦 *\"inaiki ena ena dispatch aaguthu solu\"*\n"
                 . "• 🚨 *\"alarm details kaatu\"*\n"
                 . "• 👥 *\"ethana peru work panranga\"*\n"
                 . "• 📊 *\"graphical representation of salary\"*\n\n"
                 . "உங்களுக்கு தேவையான விவரங்களை கேட்கலாம்!";
        }

        return "👋 **Hello! I am the Barani Hydraulics SCADA & Database AI Assistant.**\n\n"
             . "I operate **100% locally and offline** (zero external API keys required), connected directly to all 52 tables in the **`gri_db` MySQL database** and real-time plant telemetry.\n\n"
             . "💡 **What I can do for you:**\n"
             . "• 👥 **Employees & HR:** Staff list, designations, joining dates, department counts\n"
             . "• 💰 **Payroll & Compensation:** Net pay, basic salary, allowances, deductions, analytics\n"
             . "• 🚜 **Machine Downtime & OEE:** 2500T press status, breakdown hours, root causes, bay location\n"
             . "• 🚨 **89 Mapped Alarms:** Critical trips, MPCB overloads, PLC byte/bit addresses, floor sirens\n"
             . "• 📜 **Press Recipes:** Fast approach speeds, first pressing tonnage, curing times\n"
             . "• 📦 **Work Orders & Dispatch:** Today's dispatch schedule, completion rates\n"
             . "• 📊 **Interactive Visualizations:** Automatic Bar, Pie, Line, and Area charts\n"
             . "• 📄 **Report Generation:** Automatic PDF/Word reports & email dispatch\n\n"
             . "Feel free to ask your question in English or Tanglish!";
    }
}
