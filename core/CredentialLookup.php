<?php

declare(strict_types=1);

namespace Core;

interface CredentialLookup
{
    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array;

    /** @return array<string, mixed>|null */
    public function findCredentials(string $email): ?array;
}
