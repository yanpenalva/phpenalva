<?php

declare(strict_types=1);

namespace App\Traits;

use JsonException;
use function header;
use function headers_sent;
use function http_response_code;
use function in_array;
use function is_string;
use function json_encode;
use function sprintf;

trait RestResponseTrait
{
    private function jsonResponse(array $data, int $status = 200): void
    {
        $resolved = $this->resolveStatus($status);
        $this->sendStatus($resolved);

        if ($resolved === 204) {
            return;
        }

        echo $this->encodePayload($data);
    }

    public function successResponse(array $data, int $status = 200): void
    {
        $rawMessage = $data['message'] ?? '';
        unset($data['message'], $data['status']);
        $message = is_string($rawMessage) ? $rawMessage : '';
        $this->jsonResponse($this->envelope('success', $message, $data, []), $status);
    }

    public function errorResponse(string $message, int $status = 500, array $validationErrors = []): void
    {
        $this->jsonResponse($this->envelope('error', $message, null, $validationErrors), $status);
    }

    public function notFoundResponse(string $resource = 'Resource'): void
    {
        $this->jsonResponse($this->envelope('error', sprintf('%s not found', $resource), null, []), 404);
    }

    public function badResponse(string $message, int $status = 400): void
    {
        $this->jsonResponse($this->envelope('error', $message, null, []), $status);
    }

    private function envelope(string $status, string $message, mixed $data, array $errors): array
    {
        return [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ];
    }

    private function resolveStatus(int $status): int
    {
        if (in_array($status, [200, 201, 204, 400, 401, 403, 404, 422, 429, 500], true)) {
            return $status;
        }

        return 500;
    }

    private function sendStatus(int $status): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: application/json');
        http_response_code($status);
    }

    private function encodePayload(array $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            return '{"status":"error","message":"Internal Server Error","data":null,"errors":{}}';
        }
    }
}
