<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequestGuard;
use App\Services\UserService;
use App\Services\UserValidationException;
use App\Traits\RestResponseTrait;
use Core\BaseController;
use Core\BaseValidator;
use PDOException;
use function http_response_code;

/**
 * User registration and removal.
 *
 * The payload comes from the parsed body; the password is validated as provided
 * (presence, type and length) before hashing, and duplication is decided by the
 * repository. Both actions are unsafe methods and require the CSRF token.
 */
final class UserController extends BaseController
{
    use RestResponseTrait;

    private const IDENTIFIER_RULES = ['id' => 'required|integer|min:1'];

    private RequestGuard $guard;

    private ?PDO $pdo = null;

    public function __construct(private UserService $users)
    {
        parent::__construct();
        $this->guard = new RequestGuard();
    }

    public function store(mixed $request = null): void
    {
        $input = $this->guard->input();

        if ($input === null) {
            return;
        }

        if (!$this->guard->csrfAccepted($input)) {
            return;
        }

        $this->createUser($input->body());
    }

    public function delete(mixed $request = null): void
    {
        $input = $this->guard->input();

        if ($input === null) {
            return;
        }

        if (!$this->guard->csrfAccepted($input)) {
            return;
        }

        $identifier = $this->validatedId($request);

        if ($identifier === null) {
            return;
        }

        $this->removeUser($identifier);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createUser(array $payload): void
    {
        try {
            $user = $this->users->register($payload);
        } catch (UserValidationException $exception) {
            $this->errorResponse('Validation failed', 422, $exception->errors());

            return;
        } catch (PDOException $exception) {
            $this->errorResponse('Unable to create the user.', 500);

            return;
        }

        $this->successResponse(['message' => 'User created successfully.', 'user' => $user], 201);
    }

    private function removeUser(int $identifier): void
    {
        try {
            $deleted = $this->users->delete($identifier);
        } catch (PDOException $exception) {
            $this->errorResponse('Unable to delete the user.', 500);

            return;
        }

        if (!$deleted) {
            $this->errorResponse('User not found', 404);

            return;
        }

        http_response_code(204);
    }

    private function validatedId(mixed $identifier): ?int
    {
        $errors = BaseValidator::make(['id' => $identifier], self::IDENTIFIER_RULES);

        if ($errors !== []) {
            $this->errorResponse('Validation failed', 422, $errors);

            return null;
        }

        return (int) $identifier;
    }

}
