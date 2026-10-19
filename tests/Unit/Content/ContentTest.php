<?php

declare(strict_types=1);

namespace Tests\Unit\Content;

use App\Content\Application\CreatePost;
use App\Content\Application\DeletePost;
use App\Content\Application\UpdatePost;
use App\Content\Domain\Post;
use App\Content\Domain\PostDescription;
use App\Content\Domain\PostRepository;
use App\Content\Infrastructure\Persistence\PdoPostRepository;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    public function testDescriptionBoundariesAndPostChange(): void
    {
        self::assertSame(6, strlen((new PostDescription('123456'))->value()));
        self::assertSame(255, strlen((new PostDescription(str_repeat('x', 255)))->value()));
        $post = Post::create(new PostDescription('old text'));
        $post->changeDescription(new PostDescription('new text'));
        self::assertSame('new text', $post->description()->value());
        $this->expectException(InvalidArgumentException::class);
        new PostDescription('short');
    }

    public function testUseCasesWorkWithMemoryRepository(): void
    {
        $repository = new MemoryPosts();
        $id = (new CreatePost($repository))->execute('valid description');
        (new UpdatePost($repository))->execute($id, 'updated description');
        self::assertSame('updated description', $repository->find($id)?->description()->value());
        self::assertTrue((new DeletePost($repository))->execute($id));
    }

    public function testPdoAdapterUsesTemporarySqliteDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, description TEXT, user_id INTEGER NULL)');
        $repository = new PdoPostRepository($pdo);
        $saved = $repository->add(Post::create(new PostDescription('valid description')));
        self::assertNotNull($repository->find((int) $saved->id()));
        $repository->remove((int) $saved->id());
        self::assertNull($repository->find((int) $saved->id()));
    }
}

final class MemoryPosts implements PostRepository
{
    /** @var array<int, Post> */
    private array $posts = [];
    private int $nextId = 1;
    public function all(): array { return []; }
    public function find(int $id): ?Post { return $this->posts[$id] ?? null; }
    public function add(Post $post): Post
    {
        $id = $this->nextId++;
        return $this->posts[$id] = Post::reconstitute($id, $post->description(), $post->authorId());
    }
    public function save(Post $post): void { $this->posts[(int) $post->id()] = $post; }
    public function remove(int $id): bool { if (!isset($this->posts[$id])) { return false; } unset($this->posts[$id]); return true; }
}
