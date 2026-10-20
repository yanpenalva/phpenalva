<?php

declare(strict_types=1);

namespace App\Middleware;

use Core\BaseRequest;
use Core\BaseSession;
use function bin2hex;
use function hash_equals;
use function is_string;
use function random_bytes;

/**
 * CSRF protection for the session (cookie) authentication strategy.
 *
 * One random token per session, accepted from the X-CSRF-Token / X-XSRF-TOKEN
 * headers or the `_token` / `csrf_token` body fields. Every unsafe method (POST,
 * PUT, PATCH, DELETE) must present a matching token; safe methods are always
 * allowed. The browser session cookie stays SameSite-restricted as an additional
 * layer, never as a replacement for the token.
 */
final class CsrfMiddleware
{
    private const SESSION_KEY = 'csrf_token';

    private const TOKEN_BYTES = 32;

    /**
     * Current session token, created on first use.
     */
    public function token(): string
    {
        BaseSession::start();
        $token = BaseSession::get(self::SESSION_KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(self::TOKEN_BYTES));
        BaseSession::set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * True when the request may proceed: safe methods, or an unsafe method with a
     * token matching the current session.
     */
    public function verify(?BaseRequest $request = null): bool
    {
        $request ??= BaseRequest::capture();

        if ($request->isSafeMethod()) {
            return true;
        }

        $expected = BaseSession::get(self::SESSION_KEY);
        $provided = $request->csrfToken();

        if (!is_string($expected) || $expected === '' || !is_string($provided)) {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
