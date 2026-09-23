<?php
/**
 * Request Input Validation Helper
 */

require_once __DIR__ . '/response.php';

class Validator
{
    /**
     * Parse and validate incoming HTTP JSON payload.
     */
    public static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return $_POST;
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Response::error('Invalid JSON payload supplied.', 'invalid_json', 400);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validate presence of required keys.
     */
    public static function requireFields(array $input, array $fields): void
    {
        $missing = [];
        foreach ($fields as $field) {
            if (!isset($input[$field])) {
                $missing[] = $field;
            } elseif (is_string($input[$field]) && trim($input[$field]) === '') {
                $missing[] = $field;
            } elseif (is_array($input[$field]) && empty($input[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            Response::validationError('Missing required request parameters.', [
                'missing_fields' => $missing
            ]);
        }
    }

    /**
     * Sanitize string parameter.
     */
    public static function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }
}
