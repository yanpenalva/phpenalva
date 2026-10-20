<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use InvalidArgumentException;
use function filter_var;
use function strtolower;
use function trim;
use const FILTER_VALIDATE_EMAIL;

final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));

        if (filter_var($normalized, FILTER_VALIDATE_EMAIL) === false || strlen($normalized) > 100) {
            throw new InvalidArgumentException('Invalid email address.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
