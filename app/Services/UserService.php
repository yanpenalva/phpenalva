<?php

declare(strict_types=1);

namespace App\Services;

use App\Identity\Application\DeleteUser;
use App\Identity\Application\RegisterUser;
use App\Identity\Domain\UserRepository as IdentityUserRepository;
use App\Identity\Domain\Email;
use function is_string;

/**
 * User write rules.
 *
 * Validation runs against the raw request payload — including the original
 * password — before anything is hashed or persisted, and duplication is decided
 * by the repository. The hash exists only between validation and the INSERT.
 */
final class UserService
{
    public function __construct(private RegisterUser $registerUser, private DeleteUser $deleteUser, private IdentityUserRepository $users)
    {
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
        return $this->registerUser->execute($input);
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, list<string>>
     */
    public function validate(array $input): array
    {
        $email = $input['email'] ?? null;

        return is_string($email) && $this->users->findByEmail(new Email($email)) !== null
            ? ['email' => ['The email has already been taken.']]
            : [];
    }

    public function delete(int $id): bool
    {
        return $this->deleteUser->execute($id);
    }
}
