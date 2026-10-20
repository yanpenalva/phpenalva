<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\BaseController;
use Core\Dbg;
use Core\DebugCollector;
use function file;
use function glob;
use function gmdate;
use function htmlspecialchars;
use function is_array;
use function json_decode;
use function json_last_error;
use function json_last_error_msg;

final class DebugController extends BaseController
{
    public function index(): void
    {
        if (!Dbg::enabled()) {
            http_response_code(404);
            return;
        }
        $logs = glob(__DIR__ . '/../../storage/logs/app-*.log') ?: [];
        rsort($logs);
        $entries = [];
        foreach (array_slice($logs, 0, 1) as $log) {
            foreach (array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -100) as $line) {
                $entry = json_decode($line, true);
                if (is_array($entry)) { $entries[] = $entry; }
            }
        }
        $debugEntries = $entries;
        $requestDetails = [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'uri' => $_SERVER['REQUEST_URI'] ?? '/',
            'headers' => array_intersect_key(function_exists('getallheaders') ? getallheaders() : [], array_flip(['Accept', 'Content-Type', 'User-Agent'])),
            'body_keys' => array_keys($_POST),
        ];
        $queries = DebugCollector::queries();
        DebugCollector::checkpoint('use-case executed');
        $this->setPageTitle('Debug');
        $this->renderView('/System/debug', '/System/layout');
    }
}
