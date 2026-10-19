<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequestGuard;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthResult;
use App\Services\AuthService;
use App\Traits\RestResponseTrait;
use Core\BaseAuthenticate;
use Core\BaseController;
use Core\BaseDatabase;
use Core\BaseRequest;
use Core\BaseValidator;
use PDO;
use PDOException;
use function headers_sent;

/**
 * Entry points for the session (cookie) authentication strategy.
 *
 * Credentials are read from the parsed request body only — never from the query
 * string — and every unsafe action requires the session CSRF token.
 */
final class AuthController extends BaseController
{
    use RestResponseTrait;

    private const CREDENTIAL_RULES = [
        'email' => 'required|string|email|max:100',
        'password' => 'required|string|min:1|max:255',
    ];

    private BaseDatabase $connection;

    private RequestGuard $guard;

    private ?AuthService $service = null;

    private ?PDO $pdo = null;

    public function __construct()
    {
        parent::__construct();
        $this->connection = new BaseDatabase();
        $this->guard = new RequestGuard();
    }

    /**
     * Issues (or returns) the CSRF token bound to the current session.
     */
    public function csrfToken(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
        }

        $this->successResponse(['token' => $this->guard->token()]);
    }

    public function login(mixed $request = null): void
    {
        $input = $this->guard->input();

        if ($input === null) {
            return;
        }

        if (!$this->guard->csrfAccepted($input)) {
            return;
        }

        $credentials = $this->credentials($input);

        if ($credentials === null) {
            return;
        }

        $this->respondToLogin($input, $credentials);
    }

    /**
     * Logout is idempotent: a caller without an authenticated session gets the same
     * success answer, and the CSRF token is required only when there is a session
     * to protect.
     */
    public function logout(): void
    {
        $input = $this->guard->input();

        if ($input === null) {
            return;
        }

        if (!$this->logoutAllowed($input)) {
            return;
        }

        $this->service()->logout();
        $this->successResponse(['message' => 'Logout success.']);
    }

    /**
     * @return array{email: string, password: string}|null
     */
    private function credentials(BaseRequest $input): ?array
    {
        $body = $input->body();
        $errors = BaseValidator::make($body, self::CREDENTIAL_RULES);

        if ($errors !== []) {
            $this->errorResponse('Validation failed', 422, ['errors' => $errors]);

            return null;
        }

        return [
            'email' => (string) $body['email'],
            'password' => (string) $body['password'],
        ];
    }

    /**
     * @param array{email: string, password: string} $credentials
     */
    private function respondToLogin(BaseRequest $input, array $credentials): void
    {
        try {
            $result = $this->service()->login($credentials['email'], $credentials['password'], $input->clientIp());
        } catch (PDOException $exception) {
            $this->errorResponse('Unable to process the login.', 500);

            return;
        }

        $this->respondToResult($result);
    }

    private function respondToResult(AuthResult $result): void
    {
        if ($result->isThrottled()) {
            $this->errorResponse('Too many login attempts. Try again later.', 429);

            return;
        }

        if (!$result->isAuthenticated()) {
            $this->errorResponse('Invalid credentials.', 401);

            return;
        }

        $this->successResponse(['message' => 'Login success.', 'user' => $result->user()]);
    }

    private function logoutAllowed(BaseRequest $input): bool
    {
        if (!$this->service()->hasAuthenticatedUser()) {
            return true;
        }

        return $this->guard->csrfAccepted($input);
    }

    private function service(): AuthService
    {
        if ($this->service === null) {
            $pdo = $this->pdo();
            $this->service = new AuthService(
                new UserRepository($pdo),
                new BaseAuthenticate(new User($pdo)),
            );
        }

        return $this->service;
    }

    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            $this->pdo = $this->connection->getDatabase();
        }

        return $this->pdo;
    }
}
