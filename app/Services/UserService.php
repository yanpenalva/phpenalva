<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use Core\BaseValidator;
use RuntimeException;
use function is_string;
use function password_hash;
use function trim;

/**
 * User write rules.
 *
 * Validation runs against the raw request payload — including the original
 * password — before anything is hashed or persisted, and duplication is decided
 * by the repository. The hash exists only between validation and the INSERT.
 */
final class UserService
{
    public function __construct(
        private UserRepository $users,
        private User $model,
    ) {
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{id: int, name: string, email: string}
     *
     * @throws UserValidationException when the payload does not satisfy the rules
     */
    public function register(array $input): array
    {
        $errors = $this->validate($input);

        if ($errors !== []) {
            throw new UserValidationException($errors);
        }

        $name = trim((string) ($input['name'] ?? ''));
        $email = UserRepository::normalizeEmail((string) ($input['email'] ?? ''));
        $hash = password_hash((string) ($input['password'] ?? ''), PASSWORD_DEFAULT);

        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        return [
            'id' => $this->users->create($name, $email, $hash),
            'name' => $name,
            'email' => $email,
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, list<string>>
     */
    public function validate(array $input): array
    {
        $errors = BaseValidator::make($input, $this->model->rules(), $this->lookup());

        if ($errors !== []) {
            return $errors;
        }

        $email = $input['email'] ?? null;

        if (!is_string($email) || !$this->users->emailExists($email)) {
            return $errors;
        }

        return ['email' => ['The email has already been taken.']];
    }

    private function lookup(): callable
    {
        return fn (string $table, string $column, mixed $value, ?int $ignoreId): bool
            => $this->users->exists($table, $column, $value, $ignoreId);
    }
}
