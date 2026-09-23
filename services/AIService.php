<?php
/**
 * External LLM Integration Service via PHP cURL
 */

require_once __DIR__ . '/SchemaProvider.php';
require_once __DIR__ . '/IntentService.php';
require_once __DIR__ . '/../core/logger.php';

class AIService
{
    /**
     * Understand user query and generate structured query plan JSON.
     * The LLM NEVER directly accesses MySQL, executes SQL, or sends email.
     */
    public static function generateQueryPlan(string $question, array $user, ?array $conversationContext = null): array
    {
        $config = require __DIR__ . '/../config/ai.php';
        $apiKey = $config['api_key'] ?? '';
        // 1. First try trained local GRI AI Engine microservice (port 5005)
        try {
            $ch = curl_init('http://127.0.0.1:5005/chat');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['message' => $question, 'user' => $user]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            $aiRes = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $aiRes) {
                $parsed = json_decode($aiRes, true);
                if (!empty($parsed['answer'])) {
                    Logger::audit($user['id'], 'gri_ai_engine_hit', 'Resolved question via local GRI AI Engine');
                    return [
                        'intent'        => $parsed['intent'] ?? 'gri_db_query',
                        'action'        => 'direct_answer',
                        'direct_answer' => $parsed['answer'],
                        'table'         => 'gri_db',
                        'fields'        => ['*']
                    ];
                }
            }
        } catch (\Throwable $e) {
            // Proceed to next fallback
        }

        // 2. If no valid external API key is configured, fallback to deterministic IntentService
        if (empty($apiKey) || $apiKey === 'your_llm_api_key_here' || $apiKey === 'mock-api-key-for-testing') {
            Logger::audit($user['id'], 'ai_intent_fallback', 'Using fallback intent engine (mock/offline key)');
            return IntentService::parseIntent($question, $conversationContext);
        }

        $schema = SchemaProvider::getSchemaMetadata();

        $systemPrompt = "You are a company database query planner assistant.
Your task is to convert natural language questions about company data into a strict JSON query plan.
CRITICAL RULES:
1. You must NEVER generate executable SQL code.
2. You must NEVER generate HTML or markdown formatting outside JSON.
3. Return ONLY valid, clean JSON with no extra text or commentary.
4. Available tables: " . implode(', ', array_keys($schema)) . "
5. User Role: {$user['role']}
6. Respond with JSON structure matching this format:
{
    \"intent\": \"employee_lookup\",
    \"action\": \"query\",
    \"table\": \"employees\",
    \"entity\": \"Janani\",
    \"fields\": [\"salary\"],
    \"filters\": {\"first_name\": \"Janani\"},
    \"join\": null,
    \"aggregation\": null,
    \"sorting\": null,
    \"limit\": 1
}";

        $contextText = "";
        if ($conversationContext && !empty($conversationContext['last_entity'])) {
            $contextText = "Previous conversation context: The user was asking about entity '{$conversationContext['last_entity']}'.";
        }

        $userPrompt = "{$contextText}\nUser Question: \"{$question}\"";

        $payload = [
            'model' => $config['model'],
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt]
            ],
            'temperature' => $config['temperature'],
            'response_format' => ['type' => 'json_object']
        ];

        try {
            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $responseRaw = curl_exec($ch);
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($curlError || $httpCode !== 200) {
                Logger::audit($user['id'], 'ai_api_error', "cURL Error: {$curlError} | HTTP Code: {$httpCode}");
                return IntentService::parseIntent($question, $conversationContext);
            }

            $decoded = json_decode($responseRaw, true);
            $content = $decoded['choices'][0]['message']['content'] ?? '';
            $plan = json_decode($content, true);

            if (!is_array($plan) || empty($plan['table'])) {
                Logger::audit($user['id'], 'ai_invalid_json', 'LLM returned invalid JSON structure');
                return IntentService::parseIntent($question, $conversationContext);
            }

            return $plan;

        } catch (\Throwable $e) {
            Logger::audit($user['id'], 'ai_exception', $e->getMessage());
            return IntentService::parseIntent($question, $conversationContext);
        }
    }
}
