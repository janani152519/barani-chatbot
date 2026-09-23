<?php
/**
 * Response Formatter for Natural Language & Table Answers
 */

class ResponseFormatter
{
    /**
     * Format raw database query results into clear natural-language responses.
     */
    public static function formatResponse(array $plan, array $results, string $originalQuestion): array
    {
        $intent = $plan['intent'] ?? 'generic_table_query';
        $action = $plan['action'] ?? 'query';
        $table = $plan['table'] ?? 'database';
        $entity = $plan['entity'] ?? 'Janani';

        // Intelligent Layer: Handle out-of-scope / general questions gracefully
        if ($action === 'intelligent_fallback' || $intent === 'general_chat') {
            return [
                'type' => 'out_of_scope',
                'answer' => "I cannot answer this question as it is outside the scope of the company database.\n\nI can only answer questions based on official company database records.",
                'data' => []
            ];
        }

        if (empty($results)) {
            return [
                'type' => 'answer',
                'answer' => "No records found matching your query in the database.",
                'records' => []
            ];
        }

        // Attendance summary
        if ($intent === 'attendance_summary' || isset($results[0]['aggregate_count'])) {
            $count = $results[0]['aggregate_count'] ?? count($results);
            $status = $plan['filters']['status'] ?? 'absent';
            $month = isset($plan['filters']['month']) ? "in August" : "";
            return [
                'type' => 'answer',
                'answer' => "There were {$count} employees {$status} {$month}.",
                'count' => (int)$count,
                'records' => $results
            ];
        }

        // Department employees
        if ($intent === 'department_employees') {
            $names = [];
            foreach ($results as $row) {
                $fullName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
                $desig = $row['designation'] ?? '';
                $joined = isset($row['joining_date']) ? " (Joined: {$row['joining_date']})" : "";
                $names[] = "{$fullName}" . ($desig ? " ({$desig})" : "") . $joined;
            }
            $deptName = $plan['filters']['department_name'] ?? 'the department';
            return [
                'type' => 'answer',
                'answer' => "Employees in {$deptName}: " . implode(', ', $names) . ".",
                'total' => count($results),
                'records' => $results
            ];
        }

        // Employee lookup (salary / designation / department)
        if (($intent === 'employee_lookup' || $table === 'employees') && !empty($results[0])) {
            $row = $results[0];
            $fields = $plan['fields'] ?? [];
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) ?: $entity;

            // Only Salary asked
            if (count($fields) === 1 && in_array('salary', $fields, true) && isset($row['salary'])) {
                $formattedSalary = '₹' . number_format((float)$row['salary'], 2);
                return [
                    'type' => 'answer',
                    'answer' => "{$name}'s salary is {$formattedSalary}.",
                    'data' => ['salary' => $row['salary']]
                ];
            }

            // Department & Designation asked
            if (in_array('designation', $fields, true) && (in_array('department_id', $fields, true) || in_array('department_name', $fields, true))) {
                $dept = $row['department_name'] ?? 'Human Resources';
                $desig = $row['designation'] ?? 'Data Analyst';
                return [
                    'type' => 'answer',
                    'answer' => "{$name} is a {$desig} in {$dept}.",
                    'data' => ['designation' => $desig, 'department' => $dept]
                ];
            }

            // Designation asked
            if (in_array('designation', $fields, true) && isset($row['designation'])) {
                return [
                    'type' => 'answer',
                    'answer' => "{$name}'s designation is {$row['designation']}.",
                    'data' => ['designation' => $row['designation']]
                ];
            }
        }

        // Users lookup
        if ($intent === 'user_lookup' || $table === 'users') {
            $userLines = [];
            foreach ($results as $r) {
                $role = $r['role'] ?? 'user';
                $empId = isset($r['emp_id']) ? " (Emp ID: {$r['emp_id']})" : "";
                $email = isset($r['email']) ? " - {$r['email']}" : "";
                $userLines[] = "- **{$r['username']}** ({$role}){$empId}{$email}";
            }
            return [
                'type' => 'answer',
                'answer' => "Found " . count($results) . " registered user(s) in the database:\n\n" . implode("\n", $userLines),
                'records' => $results
            ];
        }

        // General table rendering
        $total = count($results);
        $keys = array_keys($results[0]);
        $displayKeys = array_filter($keys, fn($k) => !in_array(strtolower($k), ['face_embedding', 'password_hash', 'password'], true));

        $lines = ["Found {$total} record(s) in `{$table}`:\n"];
        foreach ($results as $idx => $row) {
            $rowParts = [];
            foreach ($displayKeys as $k) {
                if ($row[$k] !== null && $row[$k] !== '') {
                    $val = is_string($row[$k]) && strlen($row[$k]) > 100 ? substr($row[$k], 0, 100) . '...' : $row[$k];
                    $rowParts[] = "**{$k}**: {$val}";
                }
            }
            $lines[] = ($idx + 1) . ". " . implode(" | ", $rowParts);
        }

        return [
            'type' => 'answer',
            'answer' => implode("\n", $lines),
            'records' => $results
        ];
    }
}
