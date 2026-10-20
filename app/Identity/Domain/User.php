<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use InvalidArgumentException;
use function trim;

final class User
{
    private function __construct(
        private ?int $id,
        private string $name,
        private Email $email,
        private string $passwordHash,
    ) {
    }

    public static function register(string $name, Email $email, string $passwordHash): self
    {
        $name = trim($name);

        if ($name === '' || strlen($name) > 100 || $passwordHash === '') {
            throw new InvalidArgumentException('Invalid user data.');
        }

        return new self(null, $name, $email, $passwordHash);
    }

    public static function reconstitute(int $id, string $name, Email $email, string $passwordHash): self
    {
        return new self($id, $name, $email, $passwordHash);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): string
    {
        return $this->passwordHash;
    }
}
