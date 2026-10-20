<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__, 2);
require sprintf('%s/vendor/autoload.php', $projectRoot);
Dotenv::createImmutable($projectRoot)->safeLoad();
$databaseEnvironmentKeys = ['DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'];

foreach ($databaseEnvironmentKeys as $environmentKey) {
    $environmentValue = getenv($environmentKey);
    $environmentValueIsString = is_string($environmentValue);

    if ($environmentValueIsString) {
        $_ENV[$environmentKey] = $environmentValue;
        continue;
    }

    if (!isset($_ENV[$environmentKey])) {
        $_ENV[$environmentKey] = '';
    }
}

$routes = require sprintf('%s/routes/routes.php', $projectRoot);
\Core\DebugCollector::reset();
\Core\DebugCollector::checkpoint('bootstrap start');
new \Bootstrap\Routes($routes);
