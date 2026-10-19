<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\PostDescription;
use App\Content\Domain\PostRepository;
use RuntimeException;

final class UpdatePost
{
    public function __construct(private PostRepository $posts) {}
    public function execute(int $id, string $description): void
    {
        $post = $this->posts->find($id);
        if ($post === null) { throw new RuntimeException('Post not found.', 404); }
        $post->changeDescription(new PostDescription($description));
        $this->posts->save($post);
    }
}
