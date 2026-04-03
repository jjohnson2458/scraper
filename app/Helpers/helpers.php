<?php

/**
 * Global Helper Functions
 *
 * @package    ClaudeScraper
 * @subpackage Helpers
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */

/**
 * Escape a string for safe HTML output.
 *
 * @param string|null $value The value to escape.
 * @return string
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate the full URL for an asset.
 *
 * @param string $path Relative path within public/assets/.
 * @return string
 */
function asset(string $path): string
{
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    return $base . '/assets/' . ltrim($path, '/');
}

/**
 * Generate a URL relative to the app base.
 *
 * @param string $path The path.
 * @return string
 */
function url(string $path = ''): string
{
    $base = rtrim($_ENV['APP_URL'] ?? '', '/');
    return $base . '/' . ltrim($path, '/');
}

/**
 * Generate a CSRF hidden input field.
 *
 * @return string HTML hidden input.
 */
function csrf_field(): string
{
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="_csrf_token" value="' . e($token) . '">';
}

/**
 * Generate a method spoofing hidden input.
 *
 * @param string $method The HTTP method (PUT, DELETE).
 * @return string HTML hidden input.
 */
function method_field(string $method): string
{
    return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
}

/**
 * Get flash messages and clear them from the session.
 *
 * @return array
 */
function flash_messages(): array
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

/**
 * Check if the current user is authenticated.
 *
 * @return bool
 */
function is_authenticated(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Get the current authenticated user's data.
 *
 * @return array|null
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Format a date string.
 *
 * @param string|null $date   The date string.
 * @param string      $format The desired format.
 * @return string
 */
function format_date(?string $date, string $format = 'M j, Y g:i A'): string
{
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

/**
 * Format a price value.
 *
 * @param float|null $price The price.
 * @return string
 */
function format_price(?float $price): string
{
    if ($price === null) {
        return 'N/A';
    }
    return '$' . number_format($price, 2);
}

/**
 * Truncate a string to a given length.
 *
 * @param string $text   The text to truncate.
 * @param int    $length Max characters.
 * @param string $suffix Suffix to append if truncated.
 * @return string
 */
function truncate(string $text, int $length = 100, string $suffix = '...'): string
{
    if (mb_strlen($text) <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length) . $suffix;
}
