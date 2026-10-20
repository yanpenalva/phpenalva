<?php

declare(strict_types=1);

namespace Core;

use function bin2hex;
use function error_log;
use function file_put_contents;
use function file_exists;
use function filesize;
use function glob;
use function gmdate;
use function is_dir;
use function is_string;
use function json_encode;
use function mkdir;
use function preg_match;
use function rename;
use function random_bytes;
use function rtrim;
use function sort;
use function count;
use function array_shift;
use function unlink;
use function sprintf;
use function strtolower;

final class Logger
{
    private const CONTEXT_PATTERNS = [
        'action' => '/\A[a-z][a-z0-9_.-]{0,63}\z/D',
        'outcome' => '/\A(?:success|failure|denied|validation_error)\z/D',
        'driver' => '/\A(?:sqlite|mysql|pgsql|sqlsrv)\z/D',
        'migration' => '/\ACreate[A-Za-z0-9]+Table\z/D',
        'exception_class' => '/\A[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*\z/D',
    ];

    private static ?string $correlationId = null;

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $entry = [
            'timestamp' => gmdate('c'),
            'level' => $level,
            'message' => self::safeMessage($level, $message),
            'correlation_id' => self::correlationId($context),
            'context' => self::sanitizeContext($context),
        ];

        $json = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        error_log($json);
        self::appendFile($json);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function correlationId(array $context): string
    {
        $providedId = $context['correlation_id'] ?? null;
        $providedIdIsString = is_string($providedId);

        if ($providedIdIsString) {
            $providedIdValue = (string) $providedId;
            $isValid = preg_match('/\A(?:[a-f0-9]{32}|[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12})\z/iD', $providedIdValue);

            if ($isValid === 1) {
                return strtolower($providedIdValue);
            }
        }

        if (self::$correlationId === null) {
            self::$correlationId = bin2hex(random_bytes(16));
        }

        return self::$correlationId;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, string>
     */
    private static function sanitizeContext(array $context): array
    {
        $context = self::removeSecrets($context);
        $safeContext = [];

        foreach (self::CONTEXT_PATTERNS as $key => $pattern) {
            $value = $context[$key] ?? null;
            $valueIsString = is_string($value);

            if (!$valueIsString) {
                continue;
            }

            $safeValue = (string) $value;
            $matchesPattern = preg_match($pattern, $safeValue);

            if ($matchesPattern === 1) {
                $safeContext[$key] = $safeValue;
            }
        }

        return $safeContext;
    }

    private static function removeSecrets(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/password|token|cookie|authorization|secret|credential|api.?key/i', (string) $key) === 1) {
                unset($context[$key]);
            } elseif (is_array($value)) {
                $context[$key] = self::removeSecrets($value);
            }
        }
        return $context;
    }

    private static function appendFile(string $json): void
    {
        $directory = __DIR__ . '/../storage/logs';
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            return;
        }
        $path = sprintf('%s/app-%s.log', rtrim($directory, '/'), gmdate('Y-m-d'));
        if (file_exists($path) && filesize($path) >= 1048576) {
            @rename($path, $path . '.1');
        }
        @file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        $files = glob($directory . '/app-*.log*') ?: [];
        sort($files);
        while (count($files) > 7) {
            @unlink(array_shift($files));
        }
    }

    private static function safeMessage(string $level, string $message): string
    {
        $messages = [
            'info' => [
                'Request completed' => 'request_completed',
                'Migration applied' => 'migration_applied',
            ],
            'error' => [
                'Request failed' => 'request_failed',
                'Migration failed' => 'migration_failed',
            ],
        ];

        return $messages[$level][$message] ?? 'application_event';
    }
}
