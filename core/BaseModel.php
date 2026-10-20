<?php

declare(strict_types=1);

namespace Core;

use PDO;
use function array_keys;
use function array_map;
use function implode;
use function sprintf;

abstract class BaseModel
{
    protected PDO $pdo;

    /** @var string */
    protected $table = '';

    protected ?BaseDatabase $connection = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getModel(string $model): BaseModel
    {
        $objModel = sprintf('\\App\\Models\\%s', $model);
        $this->connection ??= new BaseDatabase();

        return new $objModel($this->connection->getDatabase());
    }

    public function getAll(): array
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s', $this->table));
        $stmt->execute();
        $result = $stmt->fetchAll();
        $stmt->closeCursor();

        return $result;
    }

    public function getById(int|string $id): mixed
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT * FROM %s WHERE id = :id', $this->table));
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        return $result;
    }

    public function save(array $data): string
    {
        $columns = implode(', ', array_keys($data));
        $placeholders = sprintf(':%s', implode(', :', array_keys($data)));
        $stmt = $this->pdo->prepare(sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, $columns, $placeholders));

        foreach ($data as $key => $value) {
            $stmt->bindValue(sprintf(':%s', $key), $value);
        }

        $stmt->execute();
        $insertId = (string) $this->pdo->lastInsertId();
        $stmt->closeCursor();

        return $insertId;
    }

    public function update(array $data): int
    {
        $setClause = implode(', ', array_map(static fn (string $key): string => sprintf('%s = :%s', $key, $key), array_keys($data)));
        $stmt = $this->pdo->prepare(sprintf('UPDATE %s SET %s WHERE id = :id', $this->table, $setClause));

        foreach ($data as $key => $value) {
            $stmt->bindValue(sprintf(':%s', $key), $value);
        }

        $stmt->execute();
        $affected = $stmt->rowCount();
        $stmt->closeCursor();

        return $affected;
    }

    public function exists(int|string $id): bool
    {
        $stmt = $this->pdo->prepare(sprintf('SELECT COUNT(*) FROM %s WHERE id = :id', $this->table));
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $count > 0;
    }

    public function delete(int|string $id): int
    {
        $stmt = $this->pdo->prepare(sprintf('DELETE FROM %s WHERE id = :id', $this->table));
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $affected = $stmt->rowCount();
        $stmt->closeCursor();

        return $affected;
    }
}
