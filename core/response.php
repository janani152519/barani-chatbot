<?php
/**
 * Standardized API Response Helper
 */

class Response
{
    private static function cleanBuffer(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }

    /**
     * Send a JSON success response.
     */
    public static function success(mixed $data = [], string $type = 'success', int $statusCode = 200, array $extra = []): void
    {
        self::cleanBuffer();
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $dataPayload = is_array($data) ? $data : ['data' => $data];

        $response = array_merge([
            'success' => true,
            'type'    => $type,
            'data'    => $data,
        ], $dataPayload, $extra);

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (defined('TESTING') && TESTING) {
            throw new Exception($message, $statusCode);
        }
        exit;
    }

    /**
     * Send a JSON error response.
     */
    public static function error(string $message, string $type = 'error', int $statusCode = 400, array $details = []): void
    {
        self::cleanBuffer();
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        $response = [
            'success' => false,
            'type' => $type,
            'message' => $message,
        ];

        if (!empty($details)) {
            $response['details'] = $details;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (defined('TESTING') && TESTING) {
            throw new Exception($message, $statusCode);
        }
        exit;
    }

    /**
     * Send an unauthenticated error response (401).
     */
    public static function unauthenticated(string $message = 'Authentication required. Please login.'): void
    {
        self::error($message, 'unauthenticated', 401);
    }

    /**
     * Send an unauthorized/permission denied error response (403).
     */
    public static function unauthorized(string $message = 'You are not authorized to access this information.'): void
    {
        self::error($message, 'permission_error', 403);
    }

    /**
     * Send a validation error response (422).
     */
    public static function validationError(string $message = 'Validation failed', array $errors = []): void
    {
        self::error($message, 'validation_error', 422, $errors);
    }

    /**
     * Send a internal server error response (500).
     */
    public static function serverError(string $message = 'An unexpected server error occurred.'): void
    {
        self::error($message, 'server_error', 500);
    }
}
