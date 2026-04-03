<?php

namespace App\Middleware;

/**
 * CSRF Protection Middleware
 *
 * Validates the CSRF token on all POST/PUT/DELETE requests.
 *
 * @package    ClaudeScraper
 * @subpackage Middleware
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class CsrfMiddleware
{
    /**
     * Handle the CSRF validation.
     *
     * @return bool True if token is valid.
     */
    public function handle(): bool
    {
        $method = $_SERVER['REQUEST_METHOD'];

        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            $sessionToken = $_SESSION['csrf_token'] ?? '';

            if (empty($sessionToken) || !hash_equals($sessionToken, $token)) {
                http_response_code(403);
                echo json_encode(['error' => 'CSRF token mismatch']);
                exit;
            }
        }

        return true;
    }
}
