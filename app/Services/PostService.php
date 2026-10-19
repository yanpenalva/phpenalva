<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Post;
use App\Repositories\PostRepository;
use Core\BaseValidator;
use function is_array;
use function is_object;

final class PostService
{
    private PostRepository $posts;

    public function __construct(PostRepository $posts)
    {
        $this->posts = $posts;
    }

    public function listPosts(): array
    {
        return $this->posts->getAll();
    }

    public function findPost(int $id): ?object
    {
        $post = $this->posts->getById($id);

        if (is_array($post)) {
            return (object) $post;
        }

        return is_object($post) ? $post : null;
    }

    public function postExists(int $id): bool
    {
        return $this->posts->exists($id);
    }

    public function validatePost(array $data): array
    {
        $errors = BaseValidator::make($data, Post::rules());

        return is_array($errors) ? $errors : [];
    }

    public function createPost(array $data): string
    {
        return $this->posts->save($data);
    }

    public function updatePost(int $id, array $data): int
    {
        $data['id'] = $id;

        return $this->posts->update($data);
    }

    public function deletePost(int $id): int
    {
        return $this->posts->delete($id);
    }
}
