<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\UserRepository;

final class DeleteUser
{
    public function __construct(private UserRepository $users)
    {
    }

    public function execute(int $id): bool
    {
        return $this->users->delete($id);
    }
}
