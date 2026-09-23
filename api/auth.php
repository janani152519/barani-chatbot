<?php
/**
 * Authentication Endpoint API
 * POST /api/auth.php
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';

header('Content-Type: application/json; charset=utf-8');

// Enable CORS for API consumption if needed
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = Validator::getJsonInput();
$action = $input['action'] ?? $_GET['action'] ?? 'login';

try {
    switch ($action) {
        case 'login':
            Validator::requireFields($input, ['username', 'password']);
            $user = Auth::login($input['username'], $input['password']);

            Response::success([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'employee_id' => $user['employee_id'],
                    'department_id' => $user['department_id'],
                    'department_name' => $user['department_name'],
                    'full_name' => $user['full_name'],
                ],
                'token' => $user['token'],
                'message' => 'Login successful'
            ], 'login_success', 200);
            break;

        case 'logout':
            Auth::logout();
            Response::success([
                'message' => 'Logged out successfully'
            ], 'logout_success', 200);
            break;

        case 'me':
        case 'verify':
            $user = Auth::requireAuth();
            Response::success([
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'role' => $user['role'],
                    'employee_id' => $user['employee_id'],
                    'department_id' => $user['department_id'],
                    'department_name' => $user['department_name'],
                    'full_name' => $user['full_name'],
                ],
                'authenticated' => true
            ], 'session_active', 200);
            break;

        default:
            Response::error("Invalid action '{$action}'. Supported actions: login, logout, me.", 'invalid_action', 400);
    }
} catch (\Throwable $e) {
    error_log("Auth API Error: " . $e->getMessage());
    Response::serverError("An error occurred processing authentication request.");
}
