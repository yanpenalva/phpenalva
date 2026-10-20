<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\AttemptStore;
use RuntimeException;
use Throwable;
use function array_filter;
use function array_values;
use function bin2hex;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function flock;
use function fopen;
use function hash;
use function is_array;
use function is_dir;
use function is_file;
use function json_decode;
use function json_encode;
use function mkdir;
use function random_bytes;
use function rename;
use function sprintf;
use function sys_get_temp_dir;
use function time;
use function unlink;

final class FileAttemptStore implements AttemptStore
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? sprintf('%s/phpenalva-auth-attempts', sys_get_temp_dir());
    }

    public function recent(string $key, int $threshold): array
    {
        return $this->update($key, static fn (array $items): array => array_values(array_filter($items, static fn (int $time): bool => $time > $threshold)));
    }

    public function record(string $key, int $timestamp): void
    {
        $this->update($key, static function (array $items) use ($timestamp): array { $items[] = $timestamp; return $items; });
    }

    public function clear(string $key): void
    {
        $this->update($key, static fn (array $items): array => []);
    }

    /** @param callable(list<int>): list<int> $change @return list<int> */
    private function update(string $key, callable $change): array
    {
        try {
            if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) { return []; }
            $path = $this->directory . '/' . hash('sha256', $key) . '.json';
            $lock = fopen($path . '.lock', 'c');
            if ($lock === false || !flock($lock, LOCK_EX)) { return []; }
            $raw = is_file($path) ? file_get_contents($path) : false;
            $decoded = $raw === false ? [] : json_decode($raw, true);
            $items = is_array($decoded) ? array_values(array_filter($decoded, 'is_int')) : [];
            $items = $change($items);
            $temporary = $path . '.' . bin2hex(random_bytes(8)) . '.tmp';
            if (file_put_contents($temporary, json_encode($items), LOCK_EX) === false || !rename($temporary, $path)) { @unlink($temporary); }
            flock($lock, LOCK_UN);
            fclose($lock);
            return $items;
        } catch (Throwable) {
            return [];
        }
    }
}
