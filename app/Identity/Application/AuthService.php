<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Application\AuthRateLimiter;
use Core\CredentialLookup;
use Core\BaseAuthenticate;
use Core\BaseSession;
use function bin2hex;
use function is_array;
use function is_string;
use function password_hash;
use function random_bytes;
use function strtolower;
use function trim;

/**
 * Login/logout rules for the session (cookie) strategy.
 *
 * Absent accounts are verified against a dummy hash so the answer and the cost of
 * the check do not reveal whether the account exists; the answer itself is a plain
 * invalid-credentials result. Successful logins rotate the session identifier.
 * Attempts are limited per account and client address, and every session value is
 * written through BaseSession so cookie flags stay uniform.
 */
final class AuthService
{
    private const SESSION_USER_KEY = 'user_id';

    private static ?string $dummyHash = null;

    private AuthRateLimiter $limiter;

    public function __construct(
        private CredentialLookup $users,
        private BaseAuthenticate $authenticate,
    ) {
        $this->limiter = new AuthRateLimiter();
    }

    public function login(string $email, string $password, string $clientIp): AuthResult
    {
        $account = self::normalizeEmail($email);

        if ($this->limiter->tooManyAttempts($account, $clientIp)) {
            return AuthResult::throttled();
        }

        $user = $this->users->findCredentials($account);
        $passwordMatches = $this->authenticate->verifyPassword($password, self::hashFor($user));

        if (!is_array($user) || !$passwordMatches) {
            $this->limiter->recordFailure($account, $clientIp);

            return AuthResult::invalid();
        }

        $this->limiter->clear($account, $clientIp);
        $this->startSession((int) $user['id']);

        return AuthResult::authenticated(self::publicUser($user));
    }

    public function logout(): void
    {
        BaseSession::destroy();
    }

    public function hasAuthenticatedUser(): bool
    {
        return BaseSession::get(self::SESSION_USER_KEY) !== null;
    }

    private function startSession(int $userId): void
    {
        BaseSession::start();
        BaseSession::regenerate(true);
        BaseSession::set(self::SESSION_USER_KEY, $userId);
    }

    /**
     * @param array<string, mixed>|null $user
     */
    private static function hashFor(?array $user): string
    {
        if (is_array($user) && is_string($user['password'] ?? null)) {
            return (string) $user['password'];
        }

        return self::dummyHash();
    }

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            self::$dummyHash = is_string($hash) ? $hash : '';
        }

        return self::$dummyHash;
    }

    /**
     * @param array<string, mixed> $user
     *
     * @return array{id: int, name: string, email: string}
     */
    private static function publicUser(array $user): array
    {
        return [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
        ];
    }

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }
}
