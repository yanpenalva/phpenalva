<?php

declare(strict_types=1);

namespace Core;

use JsonException;
use function explode;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_scalar;
use function is_string;
use function json_decode;
use function parse_str;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

/**
 * Single entry parser for HTTP input.
 *
 * Decodes the request body for form, JSON and verb-based payloads
 * (PUT/PATCH/DELETE included). Payloads and credentials are read from
 * {@see self::body()}; the query string is deliberately not exposed so input
 * never arrives through $_GET.
 *
 * Values are kept verbatim (no HTML filtering): escaping belongs to the output
 * layer, and filtering would corrupt credentials.
 */
final class BaseRequest
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    private const JSON_TYPE = 'application/json';

    private const CSRF_HEADERS = ['x-csrf-token', 'x-xsrf-token'];

    private const CSRF_FIELDS = ['_token', 'csrf_token'];

    private const MALFORMED_JSON = 'Malformed JSON body.';

    private const MAX_JSON_DEPTH = 32;

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     * @param array<string, mixed>  $server
     */
    private function __construct(
        private string $method,
        private array $body,
        private array $headers,
        private array $server,
        private ?string $bodyError,
    ) {
    }

    public static function capture(): self
    {
        $rawBody = file_get_contents('php://input');

        return self::create($_SERVER, $_POST, is_string($rawBody) ? $rawBody : '');
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, mixed> $post
     */
    public static function create(array $server, array $post, string $rawBody): self
    {
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $headers = self::collectHeaders($server);
        // Media type only: parameters such as charset are dropped before matching.
        $contentType = trim(explode(';', strtolower(trim($headers['content-type'] ?? '')), 2)[0]);
        $parsed = self::parseBody($method, $contentType, $post, $rawBody);

        return new self($method, $parsed['values'], $headers, $server, $parsed['error']);
    }

    public function isSafeMethod(): bool
    {
        return in_array($this->method, self::SAFE_METHODS, true);
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->body;
    }

    public function header(string $name): ?string
    {
        $value = $this->headers[strtolower($name)] ?? null;

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * CSRF token supplied by the client, from an accepted header or body field.
     */
    public function csrfToken(): ?string
    {
        foreach (self::CSRF_HEADERS as $name) {
            $token = $this->header($name);

            if ($token !== null) {
                return $token;
            }
        }

        foreach (self::CSRF_FIELDS as $field) {
            $token = $this->body[$field] ?? null;

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        return null;
    }

    /**
     * Parser failure for the current body, or null when the body decoded.
     */
    public function bodyError(): ?string
    {
        return $this->bodyError;
    }

    public function clientIp(): string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? '';

        if (!is_string($ip) || $ip === '') {
            return 'unknown';
        }

        return $ip;
    }

    /**
     * @param array<string, mixed> $server
     *
     * @return array<string, string>
     */
    private static function collectHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($key, 5)))] = trim((string) $value);
                continue;
            }

            if ($key === 'CONTENT_TYPE') {
                $headers['content-type'] = trim((string) $value);
                continue;
            }

            if ($key === 'CONTENT_LENGTH') {
                $headers['content-length'] = trim((string) $value);
            }
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array{values: array<string, mixed>, error: ?string}
     */
    private static function parseBody(string $method, string $contentType, array $post, string $rawBody): array
    {
        if (in_array($method, self::SAFE_METHODS, true)) {
            return ['values' => [], 'error' => null];
        }

        if (self::shouldParseJson($contentType, $rawBody)) {
            return self::parseJson($rawBody);
        }

        return self::parseForm($post, $rawBody);
    }

    private static function shouldParseJson(string $contentType, string $rawBody): bool
    {
        if ($contentType === self::JSON_TYPE || str_ends_with($contentType, '+json')) {
            return true;
        }

        return $contentType === '' && str_starts_with(trim($rawBody), '{');
    }

    /**
     * @return array{values: array<string, mixed>, error: ?string}
     */
    private static function parseJson(string $rawBody): array
    {
        try {
            $decoded = trim($rawBody) === ''
                ? []
                : json_decode($rawBody, true, self::MAX_JSON_DEPTH, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $decoded = null;
        }

        if (!is_array($decoded)) {
            return ['values' => [], 'error' => self::MALFORMED_JSON];
        }

        return ['values' => $decoded, 'error' => null];
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array{values: array<string, mixed>, error: ?string}
     */
    private static function parseForm(array $post, string $rawBody): array
    {
        if ($post !== []) {
            return ['values' => $post, 'error' => null];
        }

        $parsed = [];
        parse_str($rawBody, $parsed);

        return ['values' => $parsed, 'error' => null];
    }
}
