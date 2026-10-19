<?php

declare(strict_types=1);

namespace App\Content\Domain;

use InvalidArgumentException;
use function strlen;

final class PostDescription
{
    public function __construct(private string $value)
    {
        if (strlen($value) < 6 || strlen($value) > 255) {
            throw new InvalidArgumentException('Description must be between 6 and 255 characters.');
        }
    }

    public function value(): string
    {
        return $this->value;
    }
}
