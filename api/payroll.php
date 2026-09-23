<?php
/**
 * Payroll API Endpoint
 * GET  /api/payroll.php?month=9&year=2026        → list payroll records
 * POST /api/payroll.php                          → generate/process payroll
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../core/logger.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$user = Auth::requireAuth();
$pdo  = Database::getConnection();

// ─── GET: Fetch payroll records ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $month = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
    $year  = isset($_GET['year'])  ? (int)$_GET['year']  : (int)date('Y');

    $stmt = $pdo->prepare("
        SELECT p.*,
               e.employee_code, e.first_name, e.last_name, e.email,
               e.designation, e.status as emp_status,
               d.name as department_name
        FROM payroll p
        JOIN employees e ON p.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE p.month = :month AND p.year = :year
        ORDER BY d.name, e.first_name
    ");
    $stmt->execute([':month' => $month, ':year' => $year]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Compute summary
    $summary = [
        'total_employees'   => count($records),
        'total_basic'       => 0,
        'total_allowances'  => 0,
        'total_deductions'  => 0,
        'total_net_salary'  => 0,
        'paid_count'        => 0,
        'pending_count'     => 0,
    ];
    foreach ($records as $r) {
        $summary['total_basic']      += $r['basic_salary'];
        $summary['total_allowances'] += $r['allowances'];
        $summary['total_deductions'] += $r['deductions'];
        $summary['total_net_salary'] += $r['net_salary'];
        if ($r['payment_status'] === 'paid') $summary['paid_count']++;
        else                                 $summary['pending_count']++;
    }

    Response::success([
        'month'   => $month,
        'year'    => $year,
        'records' => $records,
        'summary' => $summary,
    ], 'payroll_list', 200);
    exit;
}

// ─── POST: Generate / Process payroll ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = Validator::getJsonInput();
    $action = $input['action'] ?? 'generate';

    $month = (int)($input['month'] ?? date('n'));
    $year  = (int)($input['year']  ?? date('Y'));

    if ($action === 'generate') {
        // Fetch all active employees
        $empStmt = $pdo->query("
            SELECT e.*, d.name as department_name
            FROM employees e
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE e.status = 'active'
        ");
        $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);

        $generated = [];
        foreach ($employees as $emp) {
            // Count attendance for this month
            $attStmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                    SUM(CASE WHEN status = 'absent'  THEN 1 ELSE 0 END) as absent_days,
                    SUM(CASE WHEN status = 'half_day' THEN 0.5 ELSE 0 END) as half_days,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
                FROM attendance
                WHERE employee_id = :eid
                  AND MONTH(date) = :month
                  AND YEAR(date) = :year
            ");
            $attStmt->execute([':eid' => $emp['id'], ':month' => $month, ':year' => $year]);
            $att = $attStmt->fetch(PDO::FETCH_ASSOC);

            $basicSalary  = (float)$emp['salary'];
            $workingDays  = 26; // standard working days per month
            $presentDays  = max(0, (float)($att['present_days'] ?? 0) + (float)($att['half_days'] ?? 0));
            $absentDays   = max(0, (float)($att['absent_days'] ?? 0));

            // Calculate pay based on attendance
            $perDayRate  = $basicSalary / $workingDays;
            $absentDeduction = $absentDays * $perDayRate;
            $lateDeduction   = ((float)($att['late_days'] ?? 0)) * ($perDayRate * 0.25);

            // Allowances: HRA (40%), TA (10%), Medical (5%)
            $hra      = $basicSalary * 0.40;
            $ta       = $basicSalary * 0.10;
            $medical  = $basicSalary * 0.05;
            $allowances = $hra + $ta + $medical;

            // Deductions: PF (12%), ESI (1.75% if salary <= 21000), late/absent
            $pf  = $basicSalary * 0.12;
            $esi = ($basicSalary <= 21000) ? ($basicSalary * 0.0175) : 0;
            $deductions = $pf + $esi + $absentDeduction + $lateDeduction;

            $netSalary = $basicSalary + $allowances - $deductions;

            // Upsert payroll record
            $upsert = $pdo->prepare("
                INSERT INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_salary, payment_status)
                VALUES (:eid, :month, :year, :basic, :allow, :deduct, :net, 'pending')
                ON DUPLICATE KEY UPDATE
                    basic_salary     = VALUES(basic_salary),
                    allowances       = VALUES(allowances),
                    deductions       = VALUES(deductions),
                    net_salary       = VALUES(net_salary),
                    payment_status   = IF(payment_status='paid', 'paid', 'pending')
            ");
            $upsert->execute([
                ':eid'    => $emp['id'],
                ':month'  => $month,
                ':year'   => $year,
                ':basic'  => round($basicSalary, 2),
                ':allow'  => round($allowances, 2),
                ':deduct' => round($deductions, 2),
                ':net'    => round($netSalary, 2),
            ]);

            $generated[] = [
                'employee_code'   => $emp['employee_code'],
                'name'            => $emp['first_name'] . ' ' . $emp['last_name'],
                'department'      => $emp['department_name'],
                'basic_salary'    => round($basicSalary, 2),
                'allowances'      => round($allowances, 2),
                'deductions'      => round($deductions, 2),
                'net_salary'      => round($netSalary, 2),
                'present_days'    => $presentDays,
                'absent_days'     => $absentDays,
            ];
        }

        Logger::audit($user['id'], 'payroll_generated', "Generated payroll for {$month}/{$year} - " . count($generated) . " employees");

        Response::success([
            'month'     => $month,
            'year'      => $year,
            'count'     => count($generated),
            'records'   => $generated,
            'message'   => 'Payroll generated successfully for ' . count($generated) . ' employees.',
        ], 'payroll_generated', 200);

    } elseif ($action === 'mark_paid') {
        // Mark all for month/year as paid
        $stmt = $pdo->prepare("
            UPDATE payroll SET payment_status = 'paid', payment_date = CURDATE()
            WHERE month = :month AND year = :year
        ");
        $stmt->execute([':month' => $month, ':year' => $year]);
        Logger::audit($user['id'], 'payroll_paid', "Marked payroll paid for {$month}/{$year}");
        Response::success(['message' => "All payroll records for {$month}/{$year} marked as paid."], 'payroll_paid', 200);

    } elseif ($action === 'send_report') {
        require_once __DIR__ . '/../services/ReportService.php';
        require_once __DIR__ . '/../services/EmailService.php';

        $recipients = $input['recipients'] ?? [];
        $reportBody = $input['report_body'] ?? '';
        $reportType = $input['report_type'] ?? 'payroll';
        $format     = strtolower(trim($input['format'] ?? 'pdf'));
        if ($format === 'word' || $format === 'doc') $format = 'docx';
        if (!in_array($format, ['pdf', 'docx'], true)) $format = 'pdf';

        $monthName = date('F', mktime(0, 0, 0, $month, 1));
        $cover     = EmailService::getFormalCoverLetter('payroll', null, $input['notes'] ?? null, $user);
        $subject   = $input['subject'] ?? "Payroll Report — Barani Hydraulics ({$monthName} {$year})";

        // Clean formal cover letter for email body
        $emailBody = (!empty($reportBody) && str_contains($reportBody, 'Dear Sir/Madam'))
            ? $reportBody
            : $cover['body_text'];

        // Generate customized attachment file with official BWC-7578 / Payroll ledger
        $customReport = ReportService::generateCustomizableReport(
            'payroll',
            $format,
            $emailBody,
            $input['notes'] ?? "Amended True-Up Payroll report for {$monthName} {$year}",
            $user,
            null,
            $subject,
            array_merge($input, ['month' => $month, 'year' => $year])
        );

        $attachPath = $customReport['file_path'] ?? null;
        $result = EmailService::sendPayrollReport($recipients, $emailBody, $month, $year, $user, $attachPath);
        $result['report'] = $customReport;

        Response::success($result, 'payroll_report_sent', 200);
    }

    exit;
}

Response::error('Method not allowed', 'method_not_allowed', 405);
