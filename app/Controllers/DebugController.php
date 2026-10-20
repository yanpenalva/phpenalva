<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use Core\DebugCollector;
use function array_intersect_key;
use function array_keys;
use function array_slice;
use function file;
use function function_exists;
use function getallheaders;
use function glob;
use function is_array;
use function is_string;
use function json_decode;
use function rsort;
use function strtolower;
use function trim;

final class DebugController extends BaseController
{
    public function index(): void
    {
        if (!self::debugEnabled()) {
            http_response_code(404);

            return;
        }

        $logs = glob(__DIR__ . '/../../storage/logs/app-*.log') ?: [];
        rsort($logs);
        $entries = [];

        foreach (array_slice($logs, 0, 1) as $log) {
            foreach (array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -100) as $line) {
                $entry = json_decode($line, true);

                if (is_array($entry)) {
                    $entries[] = $entry;
                }
            }
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $this->view->requestDetails = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'headers' => array_intersect_key($headers, ['Accept' => true, 'Content-Type' => true, 'User-Agent' => true]),
            'body_keys' => array_keys($_POST),
        ];
        $this->view->queries = DebugCollector::queries();
        $this->view->debugEntries = $entries;
        DebugCollector::checkpoint('use-case executed');
        $this->setPageTitle('Debug');
        $this->renderView('/System/debug', '/System/layout');
    }

    private static function debugEnabled(): bool
    {
        $raw = $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: '';
        $flag = is_string($raw) ? strtolower(trim($raw)) : '';

        return $flag === '1' || $flag === 'true';
    }
}
