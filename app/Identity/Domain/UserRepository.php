<?php

declare(strict_types=1);

namespace App\Identity\Domain;

interface UserRepository
{
    public function findByEmail(Email $email): ?User;

    public function add(User $user): User;

    public function delete(int $id): bool;
}
