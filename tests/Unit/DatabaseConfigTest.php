<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use function array_key_exists;
use function dirname;
use function extension_loaded;
use function sprintf;

final class DatabaseConfigTest extends TestCase
{
    public function testUnknownDriverIsRejectedDuringConfiguration(): void
    {
        $driverWasDefined = array_key_exists('DB_CONNECTION', $_ENV);
        $originalDriver = $_ENV['DB_CONNECTION'] ?? null;
        $_ENV['DB_CONNECTION'] = 'not-a-supported-driver';

        try {
            $this->expectException(\InvalidArgumentException::class);
            require sprintf('%s/config/database.php', dirname(__DIR__, 2));
        } finally {
            if (!$driverWasDefined) {
                unset($_ENV['DB_CONNECTION']);
            }

            if ($driverWasDefined) {
                $_ENV['DB_CONNECTION'] = $originalDriver;
            }
        }
    }

    public function testSqlServerConfigurationExplainsMissingPdoSqlsrv(): void
    {
        if (extension_loaded('pdo_sqlsrv')) {
            self::markTestSkipped('PDO_SQLSRV is available in this environment.');
        }

        $driverWasDefined = array_key_exists('DB_CONNECTION', $_ENV);
        $originalDriver = $_ENV['DB_CONNECTION'] ?? null;
        $_ENV['DB_CONNECTION'] = 'sqlsrv';

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->expectExceptionMessage('SQL Server is experimental and requires the PDO_SQLSRV extension.');
            require sprintf('%s/config/database.php', dirname(__DIR__, 2));
        } finally {
            if (!$driverWasDefined) {
                unset($_ENV['DB_CONNECTION']);
            }

            if ($driverWasDefined) {
                $_ENV['DB_CONNECTION'] = $originalDriver;
            }
        }
    }
}
