<?php

declare(strict_types=1);

namespace Bootstrap;

use Core\BaseDatabase;
use Exception;
use function class_exists;
use function is_array;
use function file_exists;
use function header;
use function headers_sent;
use function http_response_code;
use function json_encode;
use function sprintf;
use function str_starts_with;

class Controller
{
    protected BaseDatabase $connection;

    public function __construct()
    {
        $this->connection = new BaseDatabase();
    }

    public static function createController(string $controller, mixed $dependency = null): object
    {
        $controllerClass = sprintf('App\\Controllers\\%s', $controller);

        if (!class_exists($controllerClass)) {
            throw new Exception(sprintf('Controller not found: %s', $controllerClass));
        }

        return $dependency !== null ? new $controllerClass(...(is_array($dependency) ? $dependency : [$dependency])) : new $controllerClass();
    }

    public function getModel(string $model): object
    {
        $objModel = sprintf('\\App\\Models\\%s', $model);

        return new $objModel($this->connection->getDatabase());
    }

    public static function pageNotFound(): void
    {
        http_response_code(404);

        if (self::isApiRequest()) {
            self::renderNotFoundJson();

            return;
        }

        $viewPath = __DIR__ . '/../app/Views/System/notFound.phtml';

        if (!file_exists($viewPath)) {
            throw new Exception('View path not found.', 404);
        }

        require_once $viewPath;
    }

    private static function isApiRequest(): bool
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

        return str_starts_with($uri, '/api');
    }

    private static function renderNotFoundJson(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        echo json_encode([
            'status' => 'error',
            'message' => 'Not Found',
            'data' => null,
            'errors' => [],
        ]);
    }
}
