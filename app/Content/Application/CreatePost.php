<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\Post;
use App\Content\Domain\PostDescription;
use App\Content\Domain\PostRepository;

final class CreatePost
{
    public function __construct(private PostRepository $posts) {}
    public function execute(string $description): int
    {
        return (int) $this->posts->add(Post::create(new PostDescription($description)))->id();
    }
}
