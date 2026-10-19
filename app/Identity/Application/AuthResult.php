<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * Outcome of a login attempt, with no detail that could tell an attacker whether
 * the account exists.
 */
final class AuthResult
{
    public const AUTHENTICATED = 'authenticated';

    public const INVALID = 'invalid';

    public const THROTTLED = 'throttled';

    /**
     * @param array<string, mixed>|null $user
     */
    private function __construct(private string $status, private ?array $user)
    {
    }

    /**
     * @param array<string, mixed> $user
     */
    public static function authenticated(array $user): self
    {
        return new self(self::AUTHENTICATED, $user);
    }

    public static function invalid(): self
    {
        return new self(self::INVALID, null);
    }

    public static function throttled(): self
    {
        return new self(self::THROTTLED, null);
    }

    public function status(): string
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function user(): ?array
    {
        return $this->user;
    }

    public function isAuthenticated(): bool
    {
        return $this->status === self::AUTHENTICATED;
    }

    public function isThrottled(): bool
    {
        return $this->status === self::THROTTLED;
    }
}
