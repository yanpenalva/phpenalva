<?php

declare(strict_types=1);

namespace App\Content\Infrastructure\Persistence;

use App\Content\Domain\Post;
use App\Content\Domain\PostDescription;
use App\Content\Domain\PostRepository;
use PDO;
use function is_array;

final class PdoPostRepository implements PostRepository
{
    public function __construct(private PDO $pdo) {}
    public function all(): array
    {
        $rows = $this->pdo->query('SELECT id, description, user_id FROM posts ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
    public function find(int $id): ?Post
    {
        $statement = $this->pdo->prepare('SELECT id, description, user_id FROM posts WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();
        return is_array($row) ? self::map($row) : null;
    }
    public function add(Post $post): Post
    {
        $statement = $this->pdo->prepare('INSERT INTO posts (description, user_id) VALUES (:description, :user_id)');
        $statement->execute(['description' => $post->description()->value(), 'user_id' => $post->authorId()]);
        $statement->closeCursor();
        return Post::reconstitute((int) $this->pdo->lastInsertId(), $post->description(), $post->authorId());
    }
    public function save(Post $post): void
    {
        $statement = $this->pdo->prepare('UPDATE posts SET description = :description WHERE id = :id');
        $statement->execute(['description' => $post->description()->value(), 'id' => $post->id()]);
        $statement->closeCursor();
    }
    public function remove(int $id): bool
    {
        $statement = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        $statement->execute(['id' => $id]);
        $removed = $statement->rowCount() > 0;
        $statement->closeCursor();
        return $removed;
    }
    /** @param array<string, mixed> $row */
    private static function map(array $row): Post
    {
        return Post::reconstitute((int) $row['id'], new PostDescription((string) $row['description']), isset($row['user_id']) ? (int) $row['user_id'] : null);
    }
}
