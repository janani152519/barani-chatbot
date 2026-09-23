<?php
/**
 * Audit Logs API Endpoint
 * GET /api/audit_logs.php
 */

require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/response.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $user = Auth::requireAuth();
    $pdo = Database::getConnection();

    $limit = isset($_GET['limit']) ? min(100, max(1, (int)$_GET['limit'])) : 50;
    $filter = trim($_GET['q'] ?? '');

    $sql = "SELECT id, timestamp, operator_name, action_type, target, remarks 
            FROM audit_logs ";

    $params = [];
    if (!empty($filter)) {
        $sql .= "WHERE operator_name LIKE :f OR action_type LIKE :f OR remarks LIKE :f ";
        $params[':f'] = "%{$filter}%";
    }

    $sql .= "ORDER BY id DESC LIMIT {$limit}";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countStmt = $pdo->query("SELECT COUNT(*) FROM audit_logs");
    $totalCount = (int)$countStmt->fetchColumn();

    Response::success([
        'total' => $totalCount,
        'count' => count($logs),
        'logs'  => $logs,
        'current_user' => $user['username'] ?? 'admin'
    ], 'audit_logs_retrieved', 200);

} catch (\Throwable $e) {
    error_log("Audit Logs API Error: " . $e->getMessage());
    Response::serverError("Failed to retrieve audit logs: " . $e->getMessage());
}
