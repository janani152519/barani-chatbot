<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../services/QueryPlanner.php';
require_once __DIR__ . '/../services/QueryService.php';
require_once __DIR__ . '/../services/ResponseFormatter.php';

$adminUser = Auth::login('admin@barani.com', 'admin123');

$testQueries = [
    "Who are the users in the system?",
    "Show available recipes",
    "What is the capital of France?",
    "How to make coffee at home?"
];

echo "==================================================\n";
echo " DUAL LAYER TEST ON REAL gri_db DATABASE \n";
echo "==================================================\n\n";

foreach ($testQueries as $idx => $q) {
    echo "--------------------------------------------------\n";
    echo "Q" . ($idx + 1) . ": {$q}\n";
    $plan = QueryPlanner::createPlan($q, $adminUser);
    echo "Intent: {$plan['intent']} | Action: {$plan['action']} | Table: {$plan['table']}\n";

    if ($plan['action'] === 'intelligent_fallback' || $plan['intent'] === 'general_chat') {
        $response = ResponseFormatter::formatResponse($plan, [], $q);
    } else {
        $results = QueryService::executePlan($plan, $adminUser);
        $response = ResponseFormatter::formatResponse($plan, $results, $q);
    }

    echo "ANSWER:\n" . $response['answer'] . "\n\n";
}
