<?php

declare(strict_types=1);

namespace Bootstrap;

require_once __DIR__ . '/dependencies.php';

use stdClass;
use function array_keys;
use function array_reduce;
use function count;
use function explode;
use function is_string;
use function is_array;
use function array_map;
use function parse_url;
use function str_contains;

class Routes
{
    /** @var array<int, array<string, mixed>> */
    protected array $routes = [];

    public function __construct(array $routes)
    {
        $this->setRoutes($routes);
        $this->run();
    }

    private static function getUrl(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);

        return is_string($path) ? $path : '/';
    }

    /**
     * @param array<int, array<int, string>> $routes
     */
    private function setRoutes(array $routes): void
    {
        $newRoutes = [];

        foreach ($routes as $route) {
            $parts = explode('@', (string) $route[2]);
            $newRoutes[] = [
                'method' => $route[0],
                'path' => $route[1],
                'controller' => $parts[0],
                'action' => $parts[1],
                'auth' => isset($route[3]) && $route[3] === 'auth',
            ];
        }

        $this->routes = $newRoutes;
    }

    private function getRequest(): object
    {
        $request = new stdClass();
        $request->get = (object) $_GET;
        $request->post = (object) $_POST;

        return $request;
    }

    private function run(): void
    {
        $url = self::getUrl();
        $urlArray = explode('/', $url);

        foreach ($this->routes as $route) {
            $routeArray = explode('/', (string) $route['path']);

            if ($route['method'] !== ($_SERVER['REQUEST_METHOD'] ?? '') || count($urlArray) !== count($routeArray)) {
                continue;
            }

            $params = [];
            $matched = array_reduce(
                array_keys($routeArray),
                static function (bool $carry, int $index) use ($routeArray, $urlArray, &$params): bool {
                    if (!$carry) {
                        return false;
                    }

                    if (str_contains((string) $routeArray[$index], '{')) {
                        $params[] = $urlArray[$index];

                        return true;
                    }

                    return $routeArray[$index] === $urlArray[$index];
                },
                true
            );

            if (!$matched) {
                continue;
            }

            $factory = match ((string) $route['controller']) {
                'PostController' => [__NAMESPACE__ . '\\postRepository', __NAMESPACE__ . '\\createPost', __NAMESPACE__ . '\\updatePost', __NAMESPACE__ . '\\deletePost'],
                'UserController' => [__NAMESPACE__ . '\\registerUser', __NAMESPACE__ . '\\deleteUser'],
                'AuthController' => __NAMESPACE__ . '\\authService',
                default => null,
            };
            $dependencies = is_array($factory) ? array_map(static fn (string $dependency): mixed => $dependency(), $factory) : ($factory === null ? null : $factory());
            $controller = Controller::createController((string) $route['controller'], $dependencies);

            if ($route['auth'] && !$this->isAuthenticated()) {
                $controller->forbidden();

                return;
            }

            $action = (string) $route['action'];
            $params[] = $this->getRequest();
            $controller->{$action}(...$params);

            return;
        }

        Controller::pageNotFound();
    }

    /**
     * @return mixed
     */
    private function isAuthenticated(): bool
    {
        return authenticate();
    }
}
