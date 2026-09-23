<?php
/**
 * Company AI Assistant Backend API - Index & Status Gateway
 */

// If running as PHP built-in server router, serve existing files directly
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
if ($reqPath !== '/' && !empty($reqPath)) {
    $targetFile = __DIR__ . $reqPath;
    if (file_exists($targetFile) && !is_dir($targetFile)) {
        return false;
    }
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/database.php';

$dbStatus = 'disconnected';
$dbError = null;
$tableCount = 0;

try {
    $pdo = Database::getConnection();
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
    $dbStatus = 'connected';
} catch (Exception $e) {
    $dbError = $e->getMessage();
}

$endpoints = [
    ['method' => 'POST', 'path' => '/api/auth.php', 'desc' => 'User Authentication (Login, Logout, Session Verify)'],
    ['method' => 'POST', 'path' => '/api/chat.php', 'desc' => 'AI Assistant Chat & Intent Query Engine'],
    ['method' => 'POST', 'path' => '/api/report.php', 'desc' => 'Generate PDF, Excel, and CSV Reports'],
    ['method' => 'GET',  'path' => '/api/download.php', 'desc' => 'Secure Path-Protected Report File Download'],
    ['method' => 'POST', 'path' => '/api/email.php', 'desc' => 'SMTP Report Email Dispatcher'],
    ['method' => 'GET',  'path' => '/api/payroll.php', 'desc' => 'Payroll Records & Aggregations'],
    ['method' => 'GET',  'path' => '/api/scheduled_emails.php', 'desc' => 'Scheduled Email Dispatch Automation']
];

$isJson = (isset($_GET['format']) && $_GET['format'] === 'json') ||
          (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

if ($isJson) {
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'online',
        'service' => 'Barani Hydraulics AI Assistant Backend API',
        'frontend_url' => 'http://localhost:3000',
        'php_version' => PHP_VERSION,
        'database' => [
            'status' => $dbStatus,
            'name' => env('DB_DATABASE', 'gri_db'),
            'tables_count' => $tableCount,
            'error' => $dbError
        ],
        'endpoints' => $endpoints,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Barani Hydraulics AI Assistant Backend — Status</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #212121;
            --bg-secondary: #171717;
            --bg-card: #262626;
            --bg-card-hover: #2f2f2f;
            --border-card: #343434;
            --border-subtle: #2d2d2d;
            --accent-green: #10a37f;
            --accent-green-hover: #1a7f64;
            --text-main: #ececec;
            --text-secondary: #d1d5db;
            --text-muted: #8e8e8e;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1.5rem;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 900px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-card);
        }

        .title-group {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo-icon {
            width: 44px;
            height: 44px;
            background: #2f2f2f;
            border: 1px solid #444444;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.4);
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
            fill: none;
            stroke: #ececec;
            stroke-width: 2;
        }

        h1 {
            font-size: 1.45rem;
            font-weight: 600;
            letter-spacing: -0.015em;
            color: #ffffff;
        }

        .subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .badge-live {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #171717;
            border: 1px solid #383838;
            color: #ececec;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--accent-green);
            box-shadow: 0 0 0 rgba(16, 163, 127, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(16, 163, 127, 0.7); }
            70% { box-shadow: 0 0 0 8px rgba(16, 163, 127, 0); }
            100% { box-shadow: 0 0 0 0 rgba(16, 163, 127, 0); }
        }

        /* Hero Banner CTA */
        .cta-banner {
            background: var(--bg-secondary);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 1.35rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1.5rem;
            margin-bottom: 1.75rem;
        }

        .cta-banner-text h2 {
            font-size: 1.15rem;
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 0.25rem;
        }

        .cta-banner-text p {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .btn-frontend {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: #ffffff;
            color: #0d0d0d;
            text-decoration: none;
            padding: 0.65rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .btn-frontend:hover {
            background: #e3e3e3;
            transform: translateY(-1px);
        }

        /* Stats Grid */
        .grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .stat-card {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 1.15rem 1.25rem;
            transition: border-color 0.15s ease;
        }

        .stat-card:hover {
            border-color: #484848;
        }

        .stat-label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 0.4rem;
            font-weight: 600;
        }

        .stat-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 0.35rem;
        }

        /* Endpoints Table */
        .endpoints-section {
            background: var(--bg-card);
            border: 1px solid var(--border-card);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        .section-header h3 {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
        }

        .endpoint-list {
            display: flex;
            flex-direction: column;
            gap: 0.6rem;
        }

        .endpoint-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.75rem 1rem;
            background: #212121;
            border: 1px solid #303030;
            border-radius: 8px;
            transition: all 0.15s ease;
        }

        .endpoint-item:hover {
            background: var(--bg-card-hover);
            border-color: #424242;
        }

        .endpoint-path {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: #ececec;
        }

        .method-badge {
            font-size: 0.68rem;
            font-weight: 700;
            padding: 0.2rem 0.55rem;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .method-post {
            background: #171717;
            color: #d1d5db;
            border: 1px solid #424242;
        }

        .method-get {
            background: rgba(16, 163, 127, 0.12);
            color: var(--accent-green);
            border: 1px solid rgba(16, 163, 127, 0.35);
        }

        .endpoint-desc {
            font-size: 0.825rem;
            color: var(--text-muted);
            text-align: right;
        }

        .footer {
            margin-top: 2rem;
            text-align: center;
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .footer a {
            color: var(--text-main);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .footer a:hover {
            color: #ffffff;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="title-group">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                </div>
                <div>
                    <h1>Barani Hydraulics AI Assistant Backend</h1>
                    <div class="subtitle">Core PHP 8 REST API Engine & Database Gateway</div>
                </div>
            </div>
            <div class="badge-live">
                <div class="pulse-dot"></div>
                API Server Online
            </div>
        </div>

        <!-- Banner pointing to Frontend App -->
        <div class="cta-banner">
            <div class="cta-banner-text">
                <h2>Looking for the Web UI?</h2>
                <p>The interactive ChatGPT-style frontend application is running on port 3000.</p>
            </div>
            <a href="http://localhost:3000" target="_blank" class="btn-frontend">
                Launch Frontend UI (Port 3000) &rarr;
            </a>
        </div>

        <!-- System & Database Stats -->
        <div class="grid-stats">
            <div class="stat-card">
                <div class="stat-label">Database Connection</div>
                <div class="stat-value">
                    <?php if ($dbStatus === 'connected'): ?>
                        <span style="color: #10a37f;">● Connected</span>
                    <?php else: ?>
                        <span style="color: #ef4444;">● Failed</span>
                    <?php endif; ?>
                </div>
                <div class="stat-sub">
                    <?php if ($dbStatus === 'connected'): ?>
                        Database: <strong><?= htmlspecialchars(env('DB_DATABASE', 'gri_db')) ?></strong> (<?= $tableCount ?> tables)
                    <?php else: ?>
                        Error: <?= htmlspecialchars($dbError ?? 'Unknown') ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Environment</div>
                <div class="stat-value">PHP <?= PHP_VERSION ?></div>
                <div class="stat-sub">Server: <?= htmlspecialchars($_SERVER['SERVER_SOFTWARE'] ?? 'PHP Built-in') ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Architecture</div>
                <div class="stat-value" style="color: #ececec;">Zero-Trust RBAC</div>
                <div class="stat-sub">PDO Parameterized &bull; Field-level guards</div>
            </div>
        </div>

        <!-- Active API Endpoints -->
        <div class="endpoints-section">
            <div class="section-header">
                <h3>Active REST Endpoints</h3>
                <a href="?format=json" style="color: #ececec; font-size: 0.825rem; text-decoration: underline; text-underline-offset: 3px;">View as JSON &rarr;</a>
            </div>
            <div class="endpoint-list">
                <?php foreach ($endpoints as $ep): ?>
                    <div class="endpoint-item">
                        <div class="endpoint-path">
                            <span class="method-badge <?= $ep['method'] === 'POST' ? 'method-post' : 'method-get' ?>">
                                <?= $ep['method'] ?>
                            </span>
                            <span><?= htmlspecialchars($ep['path']) ?></span>
                        </div>
                        <div class="endpoint-desc"><?= htmlspecialchars($ep['desc']) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            Barani Hydraulics AI Assistant &bull; Server Time: <?= date('Y-m-d H:i:s T') ?> &bull; <a href="http://localhost:3000">Open Web Interface</a>
        </div>
    </div>
</body>
</html>
