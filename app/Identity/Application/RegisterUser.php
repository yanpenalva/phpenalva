<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Email;
use App\Identity\Domain\EmailAlreadyUsed;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use Core\BaseValidator;
use RuntimeException;
use function is_string;
use function password_hash;
use function trim;

final class RegisterUser
{
    private const RULES = [
        'name' => 'required|string|min:1|max:100',
        'email' => 'required|string|email|max:100',
        'password' => 'required|string|min:6|max:255',
    ];

    public function __construct(private UserRepository $users)
    {
    }

    /** @param array<string, mixed> $input
     * @return array{id: int, name: string, email: string}
     */
    public function execute(array $input): array
    {
        $errors = BaseValidator::make($input, self::RULES);

        if ($errors !== []) {
            throw new UserValidationException($errors);
        }

        $email = new Email((string) $input['email']);

        if ($this->users->findByEmail($email) !== null) {
            throw new UserValidationException(['email' => ['The email has already been taken.']]);
        }

        $hash = password_hash((string) $input['password'], PASSWORD_DEFAULT);

        if (!is_string($hash)) {
            throw new RuntimeException('Password hashing failed.');
        }

        try {
            $user = $this->users->add(User::register(trim((string) $input['name']), $email, $hash));
        } catch (EmailAlreadyUsed $exception) {
            throw new UserValidationException(['email' => ['The email has already been taken.']]);
        }
        $id = $user->id();

        if ($id === null) {
            throw new RuntimeException('User persistence returned no identifier.');
        }

        return ['id' => $id, 'name' => $user->name(), 'email' => $user->email()->value()];
    }
}
