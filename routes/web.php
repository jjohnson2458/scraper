<?php

/**
 * Web Routes
 *
 * Defines all HTTP routes for the application.
 *
 * @package    ClaudeScraper
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\ScanController;
use App\Controllers\ImportController;
use App\Controllers\ApiController;
use App\Controllers\LegalController;
use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;

$router = new Router();

// Public routes
$router->get('/login', AuthController::class, 'showLogin', [], 'login');
$router->post('/login', AuthController::class, 'login', [CsrfMiddleware::class]);
$router->get('/logout', AuthController::class, 'logout', [], 'logout');

// Legal pages
$router->get('/terms', LegalController::class, 'terms', [], 'terms');
$router->get('/privacy', LegalController::class, 'privacy', [], 'privacy');

// Authenticated routes
$auth = [AuthMiddleware::class];
$authCsrf = [AuthMiddleware::class, CsrfMiddleware::class];

// Dashboard
$router->get('/', DashboardController::class, 'index', $auth, 'dashboard');
$router->get('/dashboard', DashboardController::class, 'index', $auth);

// Scans
$router->get('/scans', ScanController::class, 'index', $auth, 'scans.index');
$router->get('/scans/new', ScanController::class, 'create', $auth, 'scans.create');
$router->post('/scans/url', ScanController::class, 'scrapeUrl', $authCsrf, 'scans.scrape_url');
$router->post('/scans/photo', ScanController::class, 'scrapePhoto', $authCsrf, 'scans.scrape_photo');
$router->get('/scans/{id}', ScanController::class, 'show', $auth, 'scans.show');
$router->post('/scans/{id}/save', ScanController::class, 'saveItems', $authCsrf, 'scans.save');
$router->delete('/scans/{id}', ScanController::class, 'destroy', $authCsrf, 'scans.destroy');
$router->get('/scans/{id}/export', ScanController::class, 'export', $auth, 'scans.export');

// Imports
$router->get('/imports', ImportController::class, 'index', $auth, 'imports.index');
$router->get('/imports/new/{scanId}', ImportController::class, 'create', $auth, 'imports.create');
$router->post('/imports', ImportController::class, 'store', $authCsrf, 'imports.store');
$router->get('/imports/{id}', ImportController::class, 'show', $auth, 'imports.show');

// API endpoints (internal, JSON responses)
$router->post('/api/scans/preview', ApiController::class, 'previewScrape', $authCsrf, 'api.preview');
$router->post('/api/ocr/process', ApiController::class, 'processOcr', $authCsrf, 'api.ocr');
$router->get('/api/scans/{id}/items', ApiController::class, 'getScanItems', $auth, 'api.scan_items');
$router->put('/api/scans/{id}/items', ApiController::class, 'updateScanItems', $authCsrf, 'api.update_items');
$router->post('/api/scans/{id}/images', ApiController::class, 'updateScanImages', $authCsrf, 'api.scan_images');
$router->get('/api/platforms', ApiController::class, 'getPlatforms', $auth, 'api.platforms');
$router->get('/api/platforms/{slug}/stores', ApiController::class, 'getStores', $auth, 'api.stores');

return $router;
