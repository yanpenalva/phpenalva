<?php

declare(strict_types=1);

namespace Tests\Unit;

use Core\Logger;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function json_decode;
use function ini_get;
use function ini_set;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

final class LoggerTest extends TestCase
{
    public function testErrorLogsContainCorrelationIdAndExcludeSensitiveContext(): void
    {
        $logPath = tempnam(sys_get_temp_dir(), 'phpenalva-log-');

        if ($logPath === false) {
            throw new \RuntimeException('Unable to create the temporary logger output file.');
        }

        $previousErrorLog = ini_get('error_log');

        if ($previousErrorLog === false) {
            unlink($logPath);
            throw new \RuntimeException('Unable to read the configured PHP error log.');
        }

        try {
            ini_set('error_log', $logPath);
            Logger::error('alice@example.test password=private token=secret cookie=session', [
                'action' => 'database.connect',
                'driver' => 'sqlite',
                'correlation_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'email' => 'alice@example.test',
                'password' => 'private',
                'token' => 'secret',
                'cookie' => 'session',
                'exception_class' => 'PDOException',
                'nested' => ['authorization' => 'header-secret', 'secret' => 'deep-secret', 'safe' => 'value'],
            ]);
            $logEntry = file_get_contents($logPath);

            if ($logEntry === false) {
                throw new \RuntimeException('Unable to read the temporary logger output.');
            }

            self::assertStringContainsString('"correlation_id":"aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee"', $logEntry);
            self::assertStringContainsString('"action":"database.connect"', $logEntry);
            self::assertStringContainsString('"driver":"sqlite"', $logEntry);
            self::assertStringContainsString('"message":"application_event"', $logEntry);
            self::assertStringNotContainsString('alice@example.test', $logEntry);
            self::assertStringNotContainsString('private', $logEntry);
            self::assertStringNotContainsString('secret', $logEntry);
            self::assertStringNotContainsString('session', $logEntry);
            $decoded = json_decode($logEntry, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            self::assertStringNotContainsString('header-secret', $logEntry);
            self::assertStringNotContainsString('deep-secret', $logEntry);
        } finally {
            ini_set('error_log', $previousErrorLog);
            unlink($logPath);
        }
    }
}
