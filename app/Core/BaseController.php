<?php

namespace App\Core;

/**
 * Base Controller
 *
 * Provides shared functionality for all controllers including
 * view rendering, redirects, JSON responses, and CSRF validation.
 *
 * @package    ClaudeScraper
 * @subpackage Core
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
abstract class BaseController
{
    /** @var array Data to pass to views */
    protected array $viewData = [];

    /**
     * Render a view template with layout.
     *
     * @param string $view   Dot-notation view path (e.g., 'dashboard.index').
     * @param array  $data   Data to extract into the view scope.
     * @param string $layout The layout template to use.
     * @return void
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts.app'): void
    {
        $data = array_merge($this->viewData, $data);
        $data['csrfToken'] = $_SESSION['csrf_token'] ?? '';

        extract($data);

        $viewPath = $this->resolveViewPath($view);
        $layoutPath = $this->resolveViewPath($layout);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout && file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    /**
     * Render a view without a layout wrapper.
     *
     * @param string $view Dot-notation view path.
     * @param array  $data Data to extract into the view scope.
     * @return void
     */
    protected function renderPartial(string $view, array $data = []): void
    {
        $data = array_merge($this->viewData, $data);
        extract($data);

        $viewPath = $this->resolveViewPath($view);
        require $viewPath;
    }

    /**
     * Convert dot-notation view name to a file path.
     *
     * @param string $view Dot-notation view name.
     * @return string Resolved file path.
     */
    private function resolveViewPath(string $view): string
    {
        $path = str_replace('.', DIRECTORY_SEPARATOR, $view);
        return __DIR__ . '/../../resources/views/' . $path . '.php';
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data       The data to encode.
     * @param int   $statusCode HTTP status code.
     * @return void
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Redirect to a URL.
     *
     * @param string $url        The target URL.
     * @param int    $statusCode HTTP redirect status code.
     * @return void
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }

    /**
     * Redirect back to the previous page.
     *
     * @return void
     */
    protected function back(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($referer);
    }

    /**
     * Set a flash message in the session.
     *
     * @param string $type    Message type (success, error, warning, info).
     * @param string $message The message text.
     * @return void
     */
    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Get and clear flash messages.
     *
     * @return array Flash messages.
     */
    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }

    /**
     * Validate CSRF token from the request.
     *
     * @return bool True if valid.
     */
    protected function validateCsrf(): bool
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if (empty($sessionToken) || !hash_equals($sessionToken, $token)) {
            http_response_code(403);
            echo 'CSRF token mismatch';
            exit;
        }

        return true;
    }

    /**
     * Get sanitized input from POST or GET.
     *
     * @param string     $key     The input key.
     * @param mixed|null $default Default value if not set.
     * @return mixed
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        $value = $_POST[$key] ?? $_GET[$key] ?? $default;
        if (is_string($value)) {
            return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
        }
        return $value;
    }

    /**
     * Get raw (unsanitized) input.
     *
     * @param string     $key     The input key.
     * @param mixed|null $default Default value if not set.
     * @return mixed
     */
    protected function rawInput(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }
}
