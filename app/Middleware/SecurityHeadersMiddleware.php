<?php

namespace App\Middleware;

/**
 * Security Headers Middleware
 *
 * Sets Content-Security-Policy and other security headers
 * on every response.
 *
 * @package    ClaudeScraper
 * @subpackage Middleware
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class SecurityHeadersMiddleware
{
    /**
     * Apply security headers.
     *
     * @return bool Always returns true.
     */
    public function handle(): bool
    {
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; img-src 'self' data: https:; connect-src 'self'");

        return true;
    }
}
