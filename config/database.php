<?php

declare(strict_types=1);

$environmentValue = static function (string $key, string $fallback): string {
    $value = $_ENV[$key] ?? getenv($key);

    $valueIsString = is_string($value);
    $valueIsEmpty = $value === '';

    if (!$valueIsString || $valueIsEmpty) {
        return $fallback;
    }

    return (string) $value;
};

$configuredDriver = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
$configuredDriverIsString = is_string($configuredDriver);
$driver = $configuredDriverIsString ? strtolower(trim($configuredDriver)) : 'sqlite';

$supportedDrivers = ['sqlite', 'mysql', 'pgsql', 'sqlsrv'];
$driverIsSupported = in_array($driver, $supportedDrivers, true);

if (!$driverIsSupported) {
    throw new \InvalidArgumentException('Unsupported DB_CONNECTION. Supported drivers: sqlite, mysql, pgsql, sqlsrv.');
}

$sqlServerExtensionMissing = $driver === 'sqlsrv' && !extension_loaded('pdo_sqlsrv');

if ($sqlServerExtensionMissing) {
    throw new \InvalidArgumentException('SQL Server is experimental and requires the PDO_SQLSRV extension.');
}

$availablePdoDrivers = \PDO::getAvailableDrivers();
$pdoDriverAvailable = in_array($driver, $availablePdoDrivers, true);

if (!$pdoDriverAvailable) {
    throw new \InvalidArgumentException(sprintf('DB_CONNECTION=%s requires an installed PDO driver extension.', $driver));
}

$_ENV['DB_CONNECTION'] = $driver;

$projectRoot = dirname(__DIR__);
$sqliteDatabase = $environmentValue('DB_DATABASE', sprintf('%s/database/database.sqlite', $projectRoot));
$database = $environmentValue('DB_DATABASE', 'phpenalva');
$host = $environmentValue('DB_HOST', 'localhost');
$port = $environmentValue('DB_PORT', '');
$username = $environmentValue('DB_USERNAME', '');
$password = $environmentValue('DB_PASSWORD', '');

return [
    'default' => $driver,
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => $sqliteDatabase,
            'dsn' => sprintf('sqlite:%s', $sqliteDatabase),
            'username' => '',
            'password' => '',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port !== '' ? $port : '3306',
            'database' => $database,
            'dsn' => sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port !== '' ? $port : '3306', $database),
            'username' => $username !== '' ? $username : 'root',
            'password' => $password,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => $host,
            'port' => $port !== '' ? $port : '5432',
            'database' => $database,
            'dsn' => sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port !== '' ? $port : '5432', $database),
            'username' => $username !== '' ? $username : 'postgres',
            'password' => $password,
        ],
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'host' => $host,
            'port' => $port !== '' ? $port : '1433',
            'database' => $database,
            'dsn' => sprintf('sqlsrv:Server=%s,%s;Database=%s', $host, $port !== '' ? $port : '1433', $database),
            'username' => $username !== '' ? $username : 'sa',
            'password' => $password,
        ],
    ],
];
