<?php

declare(strict_types=1);

namespace Core;

use App\Middleware\CsrfMiddleware;
use App\Models\User;
use App\Traits\RestResponseTrait;
use RuntimeException;
use function base64_encode;
use function ctype_digit;
use function get_object_vars;
use function hash_hmac;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use function json_encode;
use function password_verify;
use function rtrim;
use function strtr;
use function time;
use function trim;

/**
 * Authentication guard for the session (cookie) strategy.
 *
 * The guard owns three responsibilities: verifying stored passwords, emitting
 * signed tokens for future stateless clients, and authorizing requests that
 * carry the session cookie. Because the router has no middleware chain, the guard
 * also enforces the CSRF token on state-changing methods for authenticated
 * routes; the token itself is managed by {@see CsrfMiddleware}.
 */
final class BaseAuthenticate
{
    use RestResponseTrait;

    private const TOKEN_TTL_SECONDS = 86400;

    private const SESSION_USER_KEY = 'user_id';

    private CsrfMiddleware $csrf;

    public function __construct(private User $user)
    {
        $this->csrf = new CsrfMiddleware();
    }

    /**
     * Emits a Base64url (unpadded) token. Not part of the session contract used by
     * the application; kept for a future stateless contract and requires SECRET_KEY.
     */
    public function generateToken(int $id): string
    {
        $header = self::base64UrlEncode((string) json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));

        $payload = self::base64UrlEncode((string) json_encode([
            'id' => $id,
            'exp' => time() + self::TOKEN_TTL_SECONDS,
            'iat' => time(),
        ], JSON_THROW_ON_ERROR));

        $signature = self::base64UrlEncode(hash_hmac('sha256', "{$header}.{$payload}", $this->secretKey(), true));

        return "{$header}.{$payload}.{$signature}";
    }

    public function verifyPassword(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        return password_verify($password, $hash);
    }

    /**
     * Returns the authenticated user, or null after emitting the error response.
     *
     * @return array<string, mixed>|null
     */
    public function check(): ?array
    {
        BaseSession::start();

        $userId = BaseSession::get(self::SESSION_USER_KEY);

        if (!self::isUserId($userId)) {
            $this->errorResponse('Unauthorized', 401);

            return null;
        }

        if (!$this->csrf->verify()) {
            $this->errorResponse('Invalid CSRF token.', 403);

            return null;
        }

        return $this->findUser((int) $userId);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findUser(int $id): ?array
    {
        $row = $this->user->getById($id);

        if (is_array($row)) {
            return $row;
        }

        if (!is_object($row)) {
            $this->errorResponse('Unauthorized', 401);

            return null;
        }

        return get_object_vars($row);
    }

    private function secretKey(): string
    {
        $key = $_ENV['SECRET_KEY'] ?? $_SERVER['SECRET_KEY'] ?? '';

        if (!is_string($key) || trim($key) === '') {
            throw new RuntimeException('SECRET_KEY is missing or empty: token signing is unavailable.');
        }

        return $key;
    }

    private static function isUserId(mixed $value): bool
    {
        if (is_int($value)) {
            return $value > 0;
        }

        return is_string($value) && ctype_digit($value);
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
