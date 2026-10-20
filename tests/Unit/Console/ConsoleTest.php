<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\TestCase;

final class ConsoleTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpenalva-console-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/routes', 0700, true);
        mkdir($this->root . '/storage/logs', 0700, true);
        file_put_contents($this->root . '/routes/routes.php', "<?php return [['GET', '/fixture', 'FixtureController@index']];");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/storage/logs/*') ?: [] as $file) unlink($file);
        rmdir($this->root . '/storage/logs');
        rmdir($this->root . '/storage');
        unlink($this->root . '/routes/routes.php');
        rmdir($this->root . '/routes');
        rmdir($this->root);
    }

    public function testHelpAndRoutesList(): void
    {
        [$status, $help] = $this->run();
        self::assertSame(0, $status);
        self::assertStringContainsString('migrate', $help);
        [$status, $routes] = $this->run('routes:list');
        self::assertSame(0, $status);
        self::assertStringContainsString('GET /fixture FixtureController@index', $routes);
    }

    public function testRoutesListMatchesRegisteredRouteDefinitions(): void
    {
        $expected = '';
        foreach (require dirname(__DIR__, 3) . '/routes/routes.php' as $route) $expected .= implode(' ', [$route[0], $route[1], $route[2]]) . PHP_EOL;
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/bin/phpenalva') . ' routes:list';
        exec($command, $lines, $status);
        self::assertSame(0, $status);
        self::assertSame($expected, implode(PHP_EOL, $lines) . PHP_EOL);
    }

    public function testLogsTailFiltersAndDoesNotPrintSecretFields(): void
    {
        $path = $this->root . '/storage/logs/app-' . gmdate('Y-m-d') . '.log';
        file_put_contents($path, json_encode(['timestamp' => '2026-10-20T00:00:00+00:00', 'level' => 'error', 'message' => 'safe', 'context' => ['password' => 'needle-secret']]) . "\n");
        [$status, $output] = $this->run('logs:tail', '--level=error');
        self::assertSame(0, $status);
        self::assertStringContainsString('ERROR safe', $output);
        self::assertStringNotContainsString('needle-secret', $output);
    }

    public function testMissingLogAndInvalidMigrationOptionsFail(): void
    {
        [$status] = $this->run('logs:tail');
        self::assertSame(1, $status);
        [$status, $output] = $this->run('migrate', '--rollback', '--fresh');
        self::assertSame(1, $status);
        self::assertStringContainsString('at most one', $output);
    }

    /** @return array{int, string} */
    private function run(string ...$arguments): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__, 3) . '/bin/phpenalva');
        foreach ($arguments as $argument) $command .= ' ' . escapeshellarg($argument);
        $pipes = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, ['PHPENALVA_ROOT' => $this->root]);
        self::assertIsResource($process);
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $output];
    }
}
