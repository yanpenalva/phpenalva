<?php

declare(strict_types=1);

namespace Core;

use PDO;
use RuntimeException;
use function extension_loaded;
use function in_array;

abstract class BaseMigration
{
    private const SUPPORTED_DRIVERS = ['sqlite', 'mysql', 'pgsql', 'sqlsrv'];

    protected PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    abstract public function up(): void;

    abstract public function down(bool $force = false): void;

    protected function driverName(): string
    {
        $driverName = (string) $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driverIsSupported = in_array($driverName, self::SUPPORTED_DRIVERS, true);

        if (!$driverIsSupported) {
            throw new RuntimeException('Unsupported PDO driver for migrations.');
        }

        return $driverName;
    }

    protected function execute(string $sql): void
    {
        $result = $this->pdo->exec($sql);

        if ($result === false) {
            throw new RuntimeException('Migration DDL execution failed.');
        }
    }
}
