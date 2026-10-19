<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Registration input rejected by the user rules. Carries the field-level errors
 * so the controller can answer 422 without re-running validation.
 */
final class UserValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(private array $errors)
    {
        parent::__construct('The given user data was invalid.');
    }

    /**
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
