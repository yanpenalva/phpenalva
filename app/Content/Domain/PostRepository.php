<?php

declare(strict_types=1);

namespace App\Content\Domain;

interface PostRepository
{
    /** @return list<array<string, mixed>> */
    public function all(): array;
    public function find(int $id): ?Post;
    public function add(Post $post): Post;
    public function save(Post $post): void;
    public function remove(int $id): bool;
}
