<?php

declare(strict_types=1);

namespace Core;

use InvalidArgumentException;
use PDO;
use function is_array;
use function sprintf;

class BaseDatabase
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config !== [] ? $config : require __DIR__ . '/../config/database.php';
    }

    public function getDatabase(): PDO
    {
        $name = (string) ($_ENV['DB_CONNECTION'] ?? $this->config['default'] ?? '');
        $connections = $this->config['connections'] ?? null;

        if (!is_array($connections) || !isset($connections[$name]) || !is_array($connections[$name])) {
            throw new InvalidArgumentException(sprintf('Unknown database connection [%s].', $name));
        }

        $driverConfig = $connections[$name];
        $driver = (string) ($driverConfig['driver'] ?? '');

        $pdo = match ($driver) {
            'sqlite' => $this->connectSqlite($driverConfig),
            'pgsql' => $this->connectTcp('pgsql', $driverConfig),
            'mysql' => $this->connectTcp('mysql', $driverConfig),
            'sqlsrv' => $this->connectSqlsrv($driverConfig),
            default => throw new InvalidArgumentException(sprintf('Unsupported database driver [%s].', $driver)),
        };

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }

        return $pdo;
    }

    private function connectSqlite(array $driverConfig): PDO
    {
        $database = (string) ($driverConfig['database'] ?? '');

        if ($database === '') {
            throw new InvalidArgumentException('Missing sqlite database path.');
        }

        return new PDO(sprintf('sqlite:%s', $database));
    }

    private function connectTcp(string $driver, array $driverConfig): PDO
    {
        $host = (string) ($driverConfig['host'] ?? '');
        $port = (string) ($driverConfig['port'] ?? '');
        $database = (string) ($driverConfig['database'] ?? '');

        if ($host === '' || $port === '' || $database === '') {
            throw new InvalidArgumentException(sprintf('Missing connection settings for driver [%s].', $driver));
        }

        $dsn = sprintf('%s:host=%s;port=%s;dbname=%s', $driver, $host, $port, $database);

        if ($driver === 'mysql') {
            $dsn = sprintf('%s;charset=utf8mb4', $dsn);
        }

        return new PDO($dsn, (string) ($driverConfig['username'] ?? ''), (string) ($driverConfig['password'] ?? ''));
    }

    private function connectSqlsrv(array $driverConfig): PDO
    {
        $host = (string) ($driverConfig['host'] ?? '');
        $port = (string) ($driverConfig['port'] ?? '');
        $database = (string) ($driverConfig['database'] ?? '');

        if ($host === '' || $port === '' || $database === '') {
            throw new InvalidArgumentException('Missing connection settings for driver [sqlsrv].');
        }

        $dsn = sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port, $database);

        return new PDO($dsn, (string) ($driverConfig['username'] ?? ''), (string) ($driverConfig['password'] ?? ''));
    }
}
