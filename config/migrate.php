<?php

declare(strict_types=1);

use Core\Logger;
use Core\MigrationManager;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
$autoloadPath = sprintf('%s/vendor/autoload.php', $projectRoot);

$autoloadPathIsFile = is_file($autoloadPath);

if (!$autoloadPathIsFile) {
    fwrite(STDERR, "Composer dependencies are missing. Run composer install.\n");
    exit(1);
}

require $autoloadPath;

try {
    Dotenv::createImmutable($projectRoot)->safeLoad();
    $databaseConfig = require sprintf('%s/config/database.php', $projectRoot);
    $arguments = array_slice($_SERVER['argv'] ?? [], 1);
    $force = in_array('--force', $arguments, true);
    $commands = array_values(array_filter(
        $arguments,
        static fn (string $argument): bool => $argument !== '--force'
    ));
    $command = $commands[0] ?? 'up';

    $commandCount = count($commands);
    $isSupportedCommand = in_array($command, ['up', 'down', 'rollback'], true);

    if ($commandCount > 1 || !$isSupportedCommand) {
        throw new \InvalidArgumentException('Use one migration command: up, down, or rollback.');
    }

    if ($command === 'up' && $force) {
        throw new \InvalidArgumentException('--force is only valid for down or rollback.');
    }

    $isRollbackCommand = in_array($command, ['down', 'rollback'], true);

    if ($isRollbackCommand && !$force) {
        throw new \InvalidArgumentException('Schema rollback requires --force after arranging a backup.');
    }

    $connectionConfig = $databaseConfig['connections'][$databaseConfig['default']];
    $pdo = new \PDO(
        $connectionConfig['dsn'],
        $connectionConfig['username'],
        $connectionConfig['password'],
        [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]
    );
    $migrationManager = new MigrationManager($pdo);
    $changes = match ($command) {
        'up' => $migrationManager->migrate(),
        'down' => $migrationManager->rollback(true),
        'rollback' => $migrationManager->rollbackLast(true),
    };

    foreach ($changes as $migrationName) {
        $action = $command === 'up' ? 'Applied' : 'Rolled back';
        fwrite(STDOUT, sprintf("%s migration: %s\n", $action, $migrationName));
    }

    if ($changes === []) {
        fwrite(STDOUT, "No migrations changed.\n");
    }
} catch (\Throwable $exception) {
    Logger::error('Migration failed', [
        'action' => 'migration.cli',
        'outcome' => 'failure',
        'exception_class' => $exception::class,
    ]);
    $message = $exception instanceof \InvalidArgumentException
        ? $exception->getMessage()
        : 'Migration command failed. See the sanitized application log.';
    fwrite(STDERR, sprintf("%s\n", $message));
    exit(1);
}

