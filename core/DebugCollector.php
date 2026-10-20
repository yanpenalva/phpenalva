<?php

declare(strict_types=1);

namespace Core;

use function microtime;
use function preg_replace;
use function trim;

final class DebugCollector
{
    private static float $startedAt = 0.0;
    private static array $timeline = [];
    private static array $queries = [];

    public static function reset(): void
    {
        self::$startedAt = microtime(true);
        self::$timeline = [];
        self::$queries = [];
    }

    public static function checkpoint(string $stage): void
    {
        if (self::$startedAt === 0.0) {
            self::reset();
        }
        self::$timeline[] = ['stage' => $stage, 'ms' => (microtime(true) - self::$startedAt) * 1000];
    }

    public static function query(string $statement, float $milliseconds): void
    {
        self::$queries[] = ['statement' => self::safeStatement($statement), 'ms' => $milliseconds];
    }

    public static function timeline(): array
    {
        return self::$timeline;
    }

    public static function queries(): array
    {
        return self::$queries;
    }

    private static function safeStatement(string $statement): string
    {
        return trim(preg_replace("/'(?:''|[^'])*'|\"(?:\"\"|[^\"])*\"|\\b\\d+\\b/", '?', $statement) ?? '');
    }
}
