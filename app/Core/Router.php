<?php

namespace App\Core;

/**
 * HTTP Router
 *
 * Registers and dispatches routes to controller actions.
 *
 * @package    ClaudeScraper
 * @subpackage Core
 * @author     J.J. Johnson <visionquest716@gmail.com>
 */
class Router
{
    /** @var array<string, array<string, array{controller: string, action: string, middleware: array}>> Registered routes grouped by method */
    private array $routes = [];

    /** @var array<string, string> Named route patterns */
    private array $namedRoutes = [];

    /**
     * Register a GET route.
     *
     * @param string   $path       The URI pattern (e.g., '/scans/{id}').
     * @param string   $controller Fully qualified controller class.
     * @param string   $action     Method name on the controller.
     * @param array    $middleware  Middleware classes to apply.
     * @param string|null $name    Optional route name.
     * @return self
     */
    public function get(string $path, string $controller, string $action, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('GET', $path, $controller, $action, $middleware, $name);
    }

    /**
     * Register a POST route.
     *
     * @param string   $path       The URI pattern.
     * @param string   $controller Fully qualified controller class.
     * @param string   $action     Method name on the controller.
     * @param array    $middleware  Middleware classes to apply.
     * @param string|null $name    Optional route name.
     * @return self
     */
    public function post(string $path, string $controller, string $action, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('POST', $path, $controller, $action, $middleware, $name);
    }

    /**
     * Register a PUT route.
     *
     * @param string   $path       The URI pattern.
     * @param string   $controller Fully qualified controller class.
     * @param string   $action     Method name on the controller.
     * @param array    $middleware  Middleware classes to apply.
     * @param string|null $name    Optional route name.
     * @return self
     */
    public function put(string $path, string $controller, string $action, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('PUT', $path, $controller, $action, $middleware, $name);
    }

    /**
     * Register a DELETE route.
     *
     * @param string   $path       The URI pattern.
     * @param string   $controller Fully qualified controller class.
     * @param string   $action     Method name on the controller.
     * @param array    $middleware  Middleware classes to apply.
     * @param string|null $name    Optional route name.
     * @return self
     */
    public function delete(string $path, string $controller, string $action, array $middleware = [], ?string $name = null): self
    {
        return $this->addRoute('DELETE', $path, $controller, $action, $middleware, $name);
    }

    /**
     * Add a route to the registry.
     *
     * @param string      $method     HTTP method.
     * @param string      $path       URI pattern.
     * @param string      $controller Controller class.
     * @param string      $action     Controller method.
     * @param array       $middleware Middleware stack.
     * @param string|null $name       Optional route name.
     * @return self
     */
    private function addRoute(string $method, string $path, string $controller, string $action, array $middleware, ?string $name): self
    {
        $this->routes[$method][$path] = [
            'controller' => $controller,
            'action' => $action,
            'middleware' => $middleware,
        ];

        if ($name) {
            $this->namedRoutes[$name] = $path;
        }

        return $this;
    }

    /**
     * Dispatch the current request to the appropriate controller.
     *
     * @param string $method The HTTP method.
     * @param string $uri    The request URI.
     * @return void
     */
    public function dispatch(string $method, string $uri): void
    {
        // Strip query string
        $uri = strtok($uri, '?');
        $uri = rtrim($uri, '/') ?: '/';

        // Support PUT/DELETE via _method field
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        if (!isset($this->routes[$method])) {
            $this->sendError(405, 'Method Not Allowed');
            return;
        }

        foreach ($this->routes[$method] as $path => $route) {
            $params = $this->matchRoute($path, $uri);
            if ($params !== false) {
                $this->executeRoute($route, $params);
                return;
            }
        }

        $this->sendError(404, 'Not Found');
    }

    /**
     * Match a route pattern against a URI.
     *
     * @param string $pattern The route pattern with {param} placeholders.
     * @param string $uri     The actual request URI.
     * @return array|false Matched parameters or false.
     */
    private function matchRoute(string $pattern, string $uri): array|false
    {
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }

        return false;
    }

    /**
     * Execute a matched route through middleware and into the controller.
     *
     * @param array $route  The route definition.
     * @param array $params The matched URI parameters.
     * @return void
     */
    private function executeRoute(array $route, array $params): void
    {
        // Run middleware
        foreach ($route['middleware'] as $middlewareClass) {
            $middleware = new $middlewareClass();
            if (!$middleware->handle()) {
                return;
            }
        }

        $controller = new $route['controller']();
        call_user_func_array([$controller, $route['action']], $params);
    }

    /**
     * Send an HTTP error response.
     *
     * @param int    $code    HTTP status code.
     * @param string $message Error message.
     * @return void
     */
    private function sendError(int $code, string $message): void
    {
        http_response_code($code);
        $viewFile = __DIR__ . '/../../resources/views/errors/' . $code . '.php';
        if (file_exists($viewFile)) {
            require $viewFile;
        } else {
            echo "<h1>{$code} - {$message}</h1>";
        }
    }

    /**
     * Generate a URL for a named route.
     *
     * @param string $name   The route name.
     * @param array  $params Parameters to substitute.
     * @return string The generated URL.
     */
    public function url(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name] ?? '/';
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }
        return $path;
    }
}
