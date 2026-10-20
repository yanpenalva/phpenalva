<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence;

use App\Identity\Domain\Email;
use App\Identity\Domain\EmailAlreadyUsed;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use Core\CredentialLookup;
use PDO;
use PDOException;
use function is_array;

final class PdoUserRepository implements UserRepository, CredentialLookup
{
    public function __construct(private PDO $pdo)
    {
    }

    public function findByEmail(Email $email): ?User
    {
        $statement = $this->pdo->prepare('SELECT id, name, email, password FROM users WHERE LOWER(email) = :email LIMIT 1');
        $statement->execute(['email' => $email->value()]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (!is_array($row)) {
            return null;
        }

        return User::reconstitute((int) $row['id'], (string) $row['name'], new Email((string) $row['email']), (string) $row['password']);
    }

    public function add(User $user): User
    {
        try {
            $statement = $this->pdo->prepare('INSERT INTO users (name, email, password) VALUES (:name, :email, :password)');
            $statement->execute(['name' => $user->name(), 'email' => $user->email()->value(), 'password' => $user->passwordHash()]);
            $statement->closeCursor();
        } catch (PDOException $exception) {
            if ($this->isEmailConflict($exception)) {
                throw new EmailAlreadyUsed('The email has already been taken.', 0, $exception);
            }

            throw $exception;
        }

        return User::reconstitute((int) $this->pdo->lastInsertId(), $user->name(), $user->email(), $user->passwordHash());
    }

    public function delete(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->execute(['id' => $id]);
        $deleted = $statement->rowCount() > 0;
        $statement->closeCursor();

        return $deleted;
    }

    /** @return array<string, mixed>|null */
    public function findCredentials(string $email): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, email, password FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $statement->execute(['email' => $email]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, email FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return is_array($row) ? $row : null;
    }

    private function isEmailConflict(PDOException $exception): bool
    {
        $info = $exception->errorInfo ?? [];
        $message = (string) ($info[2] ?? $exception->getMessage());

        if (($info[1] ?? 0) === 1062 || $exception->getCode() === '23505') {
            return true;
        }

        return str_contains($message, 'UNIQUE constraint failed') && str_contains($message, 'users.email');
    }
}
