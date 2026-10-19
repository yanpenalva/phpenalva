<?php

declare(strict_types=1);

namespace App\Identity\Application;

use function array_filter;
use function array_values;
use function count;
use function hash;
use function time;

/**
 * Login attempt limiter keyed by account and client address.
 *
 * State lives in process memory, which is enough for a single worker or a
 * long-running server; a multi-worker deployment needs a shared store (APCu,
 * Redis) behind the same interface to keep the limit effective.
 */
final class AuthRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900;

    /** @var array<string, list<int>> */
    private static array $failures = [];

    public function tooManyAttempts(string $account, string $clientIp): bool
    {
        return count($this->recentFailures($account, $clientIp)) >= self::MAX_ATTEMPTS;
    }

    public function recordFailure(string $account, string $clientIp): void
    {
        $key = self::key($account, $clientIp);
        $failures = $this->recentFailures($account, $clientIp);
        $failures[] = time();
        self::$failures[$key] = $failures;
    }

    public function clear(string $account, string $clientIp): void
    {
        unset(self::$failures[self::key($account, $clientIp)]);
    }

    /**
     * @return list<int>
     */
    private function recentFailures(string $account, string $clientIp): array
    {
        $key = self::key($account, $clientIp);
        $threshold = time() - self::DECAY_SECONDS;
        $failures = array_values(
            array_filter(self::$failures[$key] ?? [], static fn (int $timestamp): bool => $timestamp > $threshold),
        );
        self::$failures[$key] = $failures;

        return $failures;
    }

    private static function key(string $account, string $clientIp): string
    {
        return hash('sha256', "{$account}|{$clientIp}");
    }
}
