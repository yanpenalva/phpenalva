<?php

declare(strict_types=1);

namespace Core;

use function bin2hex;
use function error_log;
use function gmdate;
use function is_string;
use function json_encode;
use function preg_match;
use function random_bytes;
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

        error_log(json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
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
