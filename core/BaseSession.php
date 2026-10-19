<?php

declare(strict_types=1);

namespace Core;

use function headers_sent;
use function ini_set;
use function is_string;
use function session_destroy;
use function session_get_cookie_params;
use function session_name;
use function session_regenerate_id;
use function session_set_cookie_params;
use function session_start;
use function session_status;
use function setcookie;
use function strtolower;
use function time;
use function trim;

/**
 * Session helper for the single supported authentication strategy: a session
 * carried by a cookie.
 *
 * Cookie flags (Secure, HttpOnly, SameSite), strict mode and cookie-only mode are
 * configured before every session_start(); an already active session is never
 * restarted.
 */
final class BaseSession
{
    private const SAME_SITE = 'Lax';

    private const EXPIRED_COOKIE_OFFSET = 42000;

    private function __construct()
    {
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        self::configure();
        session_start();
    }

    public static function set(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    public static function remove(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Rotates the session identifier, keeping the session data.
     */
    public static function regenerate(bool $deleteOldSession = true): void
    {
        self::start();
        session_regenerate_id($deleteOldSession);
    }

    /**
     * Clears in-memory data, expires the cookie and destroys the active session.
     * Safe to call when no session is active.
     */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            return;
        }

        $_SESSION = [];
        self::expireCookie();
        session_destroy();
    }

    private static function configure(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', self::SAME_SITE);

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => self::isSecureRequest(),
            'httponly' => true,
            'samesite' => self::SAME_SITE,
        ]);
    }

    private static function expireCookie(): void
    {
        if (headers_sent()) {
            return;
        }

        $current = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - self::EXPIRED_COOKIE_OFFSET,
            'path' => (string) ($current['path'] ?? '/'),
            'domain' => (string) ($current['domain'] ?? ''),
            'secure' => (bool) ($current['secure'] ?? false),
            'httponly' => (bool) ($current['httponly'] ?? true),
            'samesite' => (string) ($current['samesite'] ?? self::SAME_SITE),
        ]);
    }

    /**
     * Secure cookies are mandatory over HTTPS and in production; the forwarded
     * protocol is honoured so a TLS-terminating proxy keeps the flag correct.
     */
    private static function isSecureRequest(): bool
    {
        return self::isHttpsRequest() || self::isProduction();
    }

    private static function isHttpsRequest(): bool
    {
        $https = $_SERVER['HTTPS'] ?? null;

        if (is_string($https) && $https !== '' && strtolower($https) !== 'off') {
            return true;
        }

        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        $forwarded = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;

        return is_string($forwarded) && strtolower(trim($forwarded)) === 'https';
    }

    private static function isProduction(): bool
    {
        $environment = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? '';

        return is_string($environment) && strtolower(trim($environment)) === 'production';
    }
}
