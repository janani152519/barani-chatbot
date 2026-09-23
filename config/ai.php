<?php
/**
 * External LLM API Configuration
 */

require_once __DIR__ . '/../core/helpers.php';

return [
    'api_key' => env('AI_API_KEY', ''),
    'model' => env('AI_MODEL', 'gpt-4o-mini'),
    'api_url' => env('AI_API_URL', 'https://api.openai.com/v1/chat/completions'),
    'timeout' => 15,
    'temperature' => 0.0
];
