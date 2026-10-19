<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Traits\RestResponseTrait;
use Core\BaseRequest;

/**
 * Request guard for controller entry points.
 *
 * The router has no middleware chain, so every state-changing action runs the
 * same two checks explicitly: the body must parse (form, JSON or verb payload)
 * and unsafe methods must carry a valid CSRF token. Failures are answered here
 * with the shared response envelope, keeping controllers free of transport
 * details.
 */
final class RequestGuard
{
    use RestResponseTrait;

    private CsrfMiddleware $csrf;

    public function __construct()
    {
        $this->csrf = new CsrfMiddleware();
    }

    /**
     * Parsed request, or null after answering 422 for a malformed body.
     */
    public function input(): ?BaseRequest
    {
        $request = BaseRequest::capture();

        if ($request->bodyError() === null) {
            return $request;
        }

        $this->errorResponse($request->bodyError(), 422);

        return null;
    }

    /**
     * True when the CSRF token is valid; otherwise answers 403.
     */
    public function csrfAccepted(BaseRequest $request): bool
    {
        if ($this->csrf->verify($request)) {
            return true;
        }

        $this->errorResponse('Invalid CSRF token.', 403);

        return false;
    }

    /**
     * Session CSRF token for clients that still need to obtain one.
     */
    public function token(): string
    {
        return $this->csrf->token();
    }
}
