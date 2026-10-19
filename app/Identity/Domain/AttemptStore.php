<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface AttemptStore
{
    /** @return list<int> */
    public function recent(string $key, int $threshold): array;

    public function record(string $key, int $timestamp): void;

    public function clear(string $key): void;
}
