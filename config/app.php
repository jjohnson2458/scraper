<?php

/**
 * Application Configuration
 *
 * Loads environment variables from .env and provides
 * configuration values to the application.
 *
 * @package    ClaudeScraper
 * @subpackage Config
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

$envFile = __DIR__ . '/../.env';

if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
                putenv("{$key}={$value}");
            }
        }
    }
}

/**
 * Get an environment variable with an optional default.
 *
 * @param string $key     The environment variable name.
 * @param mixed  $default The default value if not set.
 * @return mixed
 */
if (!function_exists('env')) {
function env(string $key, mixed $default = null): mixed
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false) {
        return $default;
    }
    return match (strtolower($value)) {
        'true', '(true)' => true,
        'false', '(false)' => false,
        'null', '(null)' => null,
        default => $value,
    };
}
}

return [
    'name' => env('APP_NAME', 'claude_scraper'),
    'env' => env('APP_ENV', 'local'),
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://scraper.local'),

    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => env('DB_PORT', 3306),
        'database' => env('DB_DATABASE', 'scraper'),
        'username' => env('DB_USERNAME', 'root'),
        'password' => env('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
    ],

    'session' => [
        'lifetime' => env('SESSION_LIFETIME', 120),
    ],

    'csrf' => [
        'enabled' => env('CSRF_ENABLED', true),
    ],

    'paths' => [
        'views' => __DIR__ . '/../resources/views',
        'storage' => __DIR__ . '/../storage',
        'public' => __DIR__ . '/../public',
        'uploads' => __DIR__ . '/../public/uploads',
    ],
];
