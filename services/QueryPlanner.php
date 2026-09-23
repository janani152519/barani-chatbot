<?php
/**
 * Query Planner Wrapper & Plan Normalizer
 */

require_once __DIR__ . '/AIService.php';
require_once __DIR__ . '/IndustrialAgentService.php';

class QueryPlanner
{
    /**
     * Build and normalize structured query plan.
     */
    public static function createPlan(string $question, array $user, ?array $context = null): array
    {
        // 0. Check Industrial AI SQL Data Analyst Agent Engine (v1.2 Spec)
        if (IndustrialAgentService::canHandle($question, $context)) {
            return IndustrialAgentService::handle($question, $user, $context);
        }

        $rawPlan = AIService::generateQueryPlan($question, $user, $context);

        // If offline engine returned a direct_answer, pass it through as-is with all rich metadata
        if (($rawPlan['action'] ?? '') === 'direct_answer') {
            return array_merge([
                'intent'        => $rawPlan['intent'] ?? 'direct_query',
                'action'        => 'direct_answer',
                'direct_answer' => $rawPlan['direct_answer'] ?? '',
                'table'         => $rawPlan['table'] ?? null,
                'entity'        => $rawPlan['entity'] ?? null,
                'fields'        => ['*'],
                'filters'       => [],
                'join'          => null,
                'aggregation'   => null,
                'sorting'       => null,
                'limit'         => 1,
                'report_type'   => null,
                'report_format' => null,
                'recipient_group' => null
            ], $rawPlan);
        }

        // Normalize fields
        $table = $rawPlan['table'] ?? 'employees';
        $fields = $rawPlan['fields'] ?? ['*'];

        if (is_string($fields)) {
            $fields = [$fields];
        }

        // Clean nulls and defaults
        return [
            'intent'          => $rawPlan['intent'] ?? 'employee_lookup',
            'action'          => $rawPlan['action'] ?? 'query',
            'table'           => strtolower(trim($table)),
            'entity'          => $rawPlan['entity'] ?? null,
            'fields'          => array_values(array_filter($fields)),
            'filters'         => is_array($rawPlan['filters'] ?? null) ? $rawPlan['filters'] : [],
            'join'            => $rawPlan['join'] ?? null,
            'aggregation'     => $rawPlan['aggregation'] ?? null,
            'sorting'         => $rawPlan['sorting'] ?? null,
            'limit'           => isset($rawPlan['limit']) ? (int)$rawPlan['limit'] : 10,
            'report_type'     => $rawPlan['report_type'] ?? null,
            'report_format'   => $rawPlan['report_format'] ?? null,
            'recipient_group' => $rawPlan['recipient_group'] ?? null
        ];
    }
}
