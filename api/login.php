<?php
/**
 * Dedicated Login Endpoint API
 * POST /api/login.php
 * Accepts { username/email, password } or routes to auth.php
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/validator.php';
require_once __DIR__ . '/../core/response.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$input = Validator::getJsonInput();

// Handle GET request as health / info check
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    Response::success([
        'endpoint' => '/api/login.php',
        'method' => 'POST',
        'required_fields' => ['username (or email)', 'password'],
        'sample_payload' => [
            'username' => 'admin',
            'password' => 'admin123'
        ],
        'available_users' => ['admin', 'imran', 'ragavendra', 'imz', 'bhipl100', 'hussain', 'main']
    ], 'login_endpoint_ready', 200);
    exit;
}

try {
    $username = $input['username'] ?? $input['email'] ?? null;
    $password = $input['password'] ?? '';

    if (empty($username)) {
        Response::error('Field "username" or "email" is required.', 'validation_error', 422);
    }

    $user = Auth::login($username, $password);

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

} catch (\Throwable $e) {
    error_log("Login API Error: " . $e->getMessage());
    Response::serverError("An error occurred processing authentication request.");
}
