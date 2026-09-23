<?php
/**
 * Core Helper Functions
 */

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

if (!function_exists('loadEnv')) {
    function loadEnv(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);

                // Strip quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, '\'') && str_ends_with($value, '\''))) {
                    $value = substr($value, 1, -1);
                }

                if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                    $_SERVER[$name] = $value;
                }
            }
        }
    }
}

// Auto load .env from root directory
loadEnv(__DIR__ . '/../.env');

// Set application default timezone (India Standard Time by default)
$appTimezone = getenv('APP_TIMEZONE') ?: 'Asia/Kolkata';
date_default_timezone_set($appTimezone);

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }

        if ($value === 'true' || $value === '(true)') return true;
        if ($value === 'false' || $value === '(false)') return false;
        if ($value === 'empty' || $value === '(empty)') return '';
        if ($value === 'null' || $value === '(null)') return null;

        return $value;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $base = __DIR__ . '/../storage';
        return $path ? $base . '/' . ltrim($path, '/') : $base;
    }
}

if (!function_exists('sanitize_input')) {
    function sanitize_input(mixed $data): mixed
    {
        if (is_array($data)) {
            return array_map('sanitize_input', $data);
        }
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }
}
