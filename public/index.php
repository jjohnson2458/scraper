<?php

/**
 * Front Controller
 *
 * All requests are routed through this file. It bootstraps
 * the application, starts the session, and dispatches to the router.
 *
 * @package    ClaudeScraper
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
$config = require __DIR__ . '/../config/app.php';

// Load helpers
require_once __DIR__ . '/../app/Helpers/helpers.php';

// Load database
require_once __DIR__ . '/../config/database.php';

// Start session
session_start();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Apply security headers
$securityMiddleware = new \App\Middleware\SecurityHeadersMiddleware();
$securityMiddleware->handle();

// Load routes and dispatch
$router = require __DIR__ . '/../routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
