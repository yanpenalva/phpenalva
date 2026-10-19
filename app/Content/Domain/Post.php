<?php

declare(strict_types=1);

namespace App\Content\Domain;

final class Post
{
    private function __construct(private ?int $id, private PostDescription $description, private ?int $authorId)
    {
    }

    public static function create(PostDescription $description, ?int $authorId = null): self
    {
        return new self(null, $description, $authorId);
    }

    public static function reconstitute(int $id, PostDescription $description, ?int $authorId): self
    {
        return new self($id, $description, $authorId);
    }

    public function changeDescription(PostDescription $description): void
    {
        $this->description = $description;
    }

    public function id(): ?int { return $this->id; }
    public function description(): PostDescription { return $this->description; }
    public function authorId(): ?int { return $this->authorId; }
}
