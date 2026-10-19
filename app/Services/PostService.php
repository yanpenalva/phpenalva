<?php

declare(strict_types=1);

namespace App\Services;

use App\Content\Application\CreatePost;
use App\Content\Application\DeletePost;
use App\Content\Application\UpdatePost;
use App\Content\Domain\PostRepository;
use function is_array;
use function is_object;

final class PostService
{
    public function __construct(private PostRepository $posts, private CreatePost $create, private UpdatePost $update, private DeletePost $delete) {}

    public function listPosts(): array
    {
        return $this->posts->all();
    }

    public function findPost(int $id): ?object
    {
        $post = $this->posts->find($id);

        if (is_array($post)) {
            return (object) $post;
        }

        return is_object($post) ? $post : null;
    }

    public function postExists(int $id): bool
    {
        return $this->posts->find($id) !== null;
    }

    public function validatePost(array $data): array
    {
        try { new \App\Content\Domain\PostDescription((string) ($data['description'] ?? '')); return []; }
        catch (\InvalidArgumentException $exception) { return ['description' => [$exception->getMessage()]]; }
    }

    public function createPost(array $data): string
    {
        return (string) $this->create->execute((string) ($data['description'] ?? ''));
    }

    public function updatePost(int $id, array $data): int
    {
        $this->update->execute($id, (string) ($data['description'] ?? ''));
        return 1;
    }

    public function deletePost(int $id): int
    {
        return $this->delete->execute($id) ? 1 : 0;
    }
}
