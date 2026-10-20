<?php

declare(strict_types=1);

namespace Core;

use PDOStatement;
use function microtime;

final class TimedPDOStatement extends PDOStatement
{
    protected function __construct()
    {
    }

    public function execute(?array $params = null): bool
    {
        $startedAt = microtime(true);
        try {
            return parent::execute($params);
        } finally {
            DebugCollector::query($this->queryString, (microtime(true) - $startedAt) * 1000);
        }
    }
}
