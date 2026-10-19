<?php

declare(strict_types=1);

namespace App\Repositories;

use InvalidArgumentException;
use PDO;
use function in_array;
use function is_array;
use function is_scalar;
use function strtolower;
use function trim;

/**
 * Persistence for the users table.
 *
 * Writes use explicit columns and parameter binding; failures propagate as
 * PDOException instead of being converted into a success-looking value, so callers
 * can answer 500 and never report a write that did not happen.
 */
final class UserRepository
{
    /** Columns that may be used by unique/exists lookups, per table. */
    private const LOOKUP_COLUMNS = [
        'users' => ['email'],
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, name, email, password FROM users WHERE LOWER(email) = :email LIMIT 1',
        );
        $statement->execute(['email' => self::normalizeEmail($email)]);
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return is_array($user) ? $user : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $statement = $this->pdo->prepare('SELECT id, name, email FROM users WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $user = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        return is_array($user) ? $user : null;
    }

    public function emailExists(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function create(string $name, string $email, string $passwordHash): int
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (name, email, password) VALUES (:name, :email, :password)',
        );
        $statement->execute([
            'name' => $name,
            'email' => self::normalizeEmail($email),
            'password' => $passwordHash,
        ]);
        $statement->closeCursor();

        return (int) $this->pdo->lastInsertId();
    }

    public function deleteById(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $deleted = $statement->rowCount() > 0;
        $statement->closeCursor();

        return $deleted;
    }

    /**
     * Lookup used by the validator's unique/exists rules. Table and column come
     * from the rule, so both are checked against an allowlist before being
     * interpolated; the value is always bound.
     */
    public function exists(string $table, string $column, mixed $value, ?int $ignoreId = null): bool
    {
        $columns = self::LOOKUP_COLUMNS[$table] ?? [];

        if (!in_array($column, $columns, true)) {
            throw new InvalidArgumentException("Unsupported lookup for {$table}.{$column}.");
        }

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = :value";

        if ($ignoreId !== null) {
            $sql = "{$sql} AND id <> :ignoreId";
        }

        $statement = $this->pdo->prepare($sql);
        $statement->bindValue(':value', self::normalizeLookupValue($column, $value));

        if ($ignoreId !== null) {
            $statement->bindValue(':ignoreId', $ignoreId, PDO::PARAM_INT);
        }

        $statement->execute();
        $found = (int) $statement->fetchColumn() > 0;
        $statement->closeCursor();

        return $found;
    }

    public static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function normalizeLookupValue(string $column, mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);

        return $column === 'email' ? strtolower($text) : $text;
    }
}
