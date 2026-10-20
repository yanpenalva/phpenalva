<?php

declare(strict_types=1);

namespace App\Content\Application;

use App\Content\Domain\PostRepository;

final class DeletePost
{
    public function __construct(private PostRepository $posts) {}
    public function execute(int $id): bool { return $this->posts->remove($id); }
}
