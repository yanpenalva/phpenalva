<?php

declare(strict_types=1);

namespace Bootstrap;


use JsonException;
use Throwable;
use TypeError;
use ValueError;
use function error_log;
use function headers_sent;
use function header;
use function http_response_code;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

require __DIR__ . '/../vendor/autoload.php';
\Core\DebugCollector::reset();
\Core\DebugCollector::checkpoint('bootstrap start');

// Treats all errors as exceptions.
set_exception_handler(static function (Throwable $exception): void {
    $status = exceptionStatus($exception);
    logThrowable($exception, $status);

    if (isApiRequest()) {
        renderJsonError($status, $exception);
        exit(1);
    }

    renderHtmlError($status, $exception);
    exit(1);
});

// Loads environment variables from .env file.
$dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

/*
This file takes care of the initialization of all the files in the application.
It is the first file to be called in the application.
*/
$routes = require_once __DIR__ . '/../routes/routes.php';
require_once __DIR__ . '/dependencies.php';
$router = new Routes($routes);

function exceptionStatus(Throwable $exception): int
{
    if ($exception instanceof TypeError || $exception instanceof ValueError) {
        return 422;
    }

    $code = (int) $exception->getCode();

    if (in_array($code, [400, 401, 403, 404, 422, 429], true)) {
        return $code;
    }

    return 500;
}

function isApiRequest(): bool
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');

    return str_starts_with($uri, '/api');
}

function isDebug(): bool
{
    $raw = $_ENV['APP_DEBUG'] ?? null;
    $flag = is_string($raw) ? strtolower(trim($raw)) : '';

    return $flag === '1' || $flag === 'true';
}

function publicMessage(int $status): string
{
    return match ($status) {
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        422 => 'Unprocessable Entity',
        429 => 'Too Many Requests',
        default => 'Internal Server Error',
    };
}

function logThrowable(Throwable $exception, int $status): void
{
    // TODO(logger): frente C deve ligar o logger estruturado aqui.
    error_log(sprintf(
        'Unhandled %s status=%d: %s',
        $exception::class,
        $status,
        substr(str_replace("\n", ' ', $exception->getMessage()), 0, 200)
    ));
}

function renderJsonError(int $status, Throwable $exception): void
{
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($status);
    }

    try {
        echo json_encode([
            'status' => 'error',
            'message' => publicMessage($status),
            'data' => null,
            'errors' => debugErrors($exception),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    } catch (JsonException $encodeException) {
        echo '{"status":"error","message":"Internal Server Error","data":null,"errors":{}}';
    }
}

function debugErrors(Throwable $exception): array
{
    if (!isDebug()) {
        return [];
    }

    return ['detail' => substr(str_replace("\n", ' ', $exception->getMessage()), 0, 500)];
}

function renderHtmlError(int $status, Throwable $exception): void
{
    if (!headers_sent()) {
        http_response_code($status);
    }

    $message = publicMessage($status);

    if (isDebug()) {
        $message = sprintf('%s: %s', $message, substr(str_replace("\n", ' ', $exception->getMessage()), 0, 500));
    }

    $errorDetails = [
        'message' => $message,
        'status_code' => $status,
    ];

    include __DIR__ . '/../app/Views/System/exception.phtml';
}
