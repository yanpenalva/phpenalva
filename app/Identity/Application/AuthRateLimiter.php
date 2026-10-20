<?php

declare(strict_types=1);

namespace App\Identity\Application;

use function array_filter;
use function array_values;
use function count;
use function hash;
use function time;
use App\Identity\Domain\AttemptStore;
use App\Identity\Infrastructure\Persistence\FileAttemptStore;

/**
 * Login attempt limiter keyed by account and client address.
 *
 * IO failures fail open so an unavailable limiter never prevents login.
 */
final class AuthRateLimiter
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900;

    private AttemptStore $store;

    public function __construct(?AttemptStore $store = null) { $this->store = $store ?? new FileAttemptStore(); }

    public function tooManyAttempts(string $account, string $clientIp): bool
    {
        return count($this->store->recent(self::key($account, $clientIp), time() - self::DECAY_SECONDS)) >= self::MAX_ATTEMPTS;
    }

    public function recordFailure(string $account, string $clientIp): void
    {
        $this->store->record(self::key($account, $clientIp), time());
    }

    public function clear(string $account, string $clientIp): void
    {
        $this->store->clear(self::key($account, $clientIp));
    }

    private static function key(string $account, string $clientIp): string
    {
        return hash('sha256', "{$account}|{$clientIp}");
    }
}
