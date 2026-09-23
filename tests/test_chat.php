<?php
require_once __DIR__ . '/../core/auth.php';

// Login as Finance user (Amit) for salary and payroll lookups
$financeUser = Auth::login('amit', 'password123');
$jananiUser = Auth::login('janani', 'password123');
$hrUser = Auth::login('rajesh', 'password123');

require_once __DIR__ . '/../services/QueryPlanner.php';
require_once __DIR__ . '/../services/QueryService.php';
require_once __DIR__ . '/../services/ResponseFormatter.php';

$questions = [
    "What is Janani's salary?",
    "What is Janani's department?",
    "Who works in HR?",
    "How many employees were absent in August?",
    "Show employees from HR who joined after January 2025."
];

$context = [];

foreach ($questions as $index => $q) {
    echo "========================================\n";
    echo "Q" . ($index + 1) . ": {$q}\n";
    
    // Choose user based on query intent sensitivity
    $currentUser = (str_contains(strtolower($q), 'salary')) ? $financeUser : $hrUser;

    $plan = QueryPlanner::createPlan($q, $currentUser, $context);
    echo "Intent: {$plan['intent']} | Table: {$plan['table']} | Fields: " . implode(', ', $plan['fields']) . "\n";
    
    if ($plan['entity']) {
        $context['last_entity'] = $plan['entity'];
    }

    $results = QueryService::executePlan($plan, $currentUser);
    $response = ResponseFormatter::formatResponse($plan, $results, $q);

    echo "ANSWER: {$response['answer']}\n";
}

// Test follow-up question
echo "========================================\n";
$qFollowup = "What is her designation?";
echo "Q (Follow-up): {$qFollowup} (Context entity: {$context['last_entity']})\n";
$planFollowup = QueryPlanner::createPlan($qFollowup, $financeUser, $context);
echo "Intent: {$planFollowup['intent']} | Entity: {$planFollowup['entity']} | Fields: " . implode(', ', $planFollowup['fields']) . "\n";
$resultsFollowup = QueryService::executePlan($planFollowup, $financeUser);
$responseFollowup = ResponseFormatter::formatResponse($planFollowup, $resultsFollowup, $qFollowup);
echo "ANSWER: {$responseFollowup['answer']}\n";
