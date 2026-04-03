<?php

namespace App\Middleware;

/**
 * Authentication Middleware
 *
 * Verifies that the current session has a logged-in user.
 * Redirects to the login page if not authenticated.
 *
 * @package    ClaudeScraper
 * @subpackage Middleware
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class AuthMiddleware
{
    /**
     * Handle the middleware check.
     *
     * @return bool True if authenticated, false if redirected.
     */
    public function handle(): bool
    {
        if (empty($_SESSION['user_id'])) {
            http_response_code(302);
            header('Location: /login');
            exit;
        }
        return true;
    }
}
