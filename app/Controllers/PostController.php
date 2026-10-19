<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Middleware\RequestGuard;
use App\Content\Application\CreatePost;
use App\Content\Application\UpdatePost;
use App\Content\Application\DeletePost;
use App\Content\Domain\PostRepository;
use App\Traits\RestResponseTrait;
use Core\BaseController;
use Core\BaseRequest;
use Core\Logger;
use InvalidArgumentException;
use Error;
use Throwable;
use TypeError;
use function filter_var;
use function is_string;
use const FILTER_VALIDATE_INT;

class PostController extends BaseController
{
    use RestResponseTrait;

    private PostRepository $posts;

    /** @var object */
    public $view;

    public function __construct(PostRepository $posts, private CreatePost $create, private UpdatePost $update, private DeletePost $delete)
    {
        parent::__construct();
        $this->posts = $posts;
    }

    public function index(): void
    {
        try {
            $this->setPageTitle('Posts');
            $this->view->posts = $this->posts->all();
            $this->renderView('Posts/index');
        } catch (Throwable $exception) {
            $this->logError('index', $exception);
            $this->renderExceptionView(500, 'Internal Server Error');
        }
    }

    public function show(mixed $id): void
    {
        try {
            $this->setPageTitle('Post Details');
            $postId = $this->parseId($id);
            $this->view->post = $postId === null ? null : $this->posts->find($postId);
            $this->renderView('Posts/show');
        } catch (Throwable $exception) {
            $this->logError('show', $exception);
            $this->renderExceptionView(500, 'Internal Server Error');
        }
    }

    public function store(): void
    {
        try {
            $input = $this->guardedInput();

            if ($input === null) {
                return;
            }

            $this->createFromRequest($input);
        } catch (TypeError | Error $exception) {
            $this->logError('store', $exception);
            $this->errorResponse('Invalid data', 422);
        } catch (Throwable $exception) {
            $this->logError('store', $exception);
            $this->errorResponse('Internal Server Error', 500);
        }
    }

    public function update(mixed $id): void
    {
        try {
            $input = $this->guardedInput();

            if ($input === null) {
                return;
            }

            $this->updateFromRequest($id, $input);
        } catch (TypeError | Error $exception) {
            $this->logError('update', $exception);
            $this->errorResponse('Invalid data', 422);
        } catch (Throwable $exception) {
            $this->logError('update', $exception);
            $this->errorResponse('Internal Server Error', 500);
        }
    }

    public function delete(mixed $id): void
    {
        try {
            if ($this->guardedInput() === null) {
                return;
            }

            $this->removePost($id);
        } catch (TypeError | Error $exception) {
            $this->logError('delete', $exception);
            $this->errorResponse('Invalid data', 422);
        } catch (Throwable $exception) {
            $this->logError('delete', $exception);
            $this->errorResponse('Internal Server Error', 500);
        }
    }

    private function guardedInput(): ?BaseRequest
    {
        $guard = new RequestGuard();
        $input = $guard->input();

        if ($input === null || !$guard->csrfAccepted($input)) {
            return null;
        }

        return $input;
    }

    private function createFromRequest(BaseRequest $request): void
    {
        $description = $this->readDescription($request);

        if ($description === null) {
            $this->errorResponse('Invalid data', 422);

            return;
        }

        try { $postId = $this->create->execute($description); }
        catch (InvalidArgumentException $exception) { $this->errorResponse('Validation failed', 422, ['description' => [$exception->getMessage()]]); return; }
        $this->successResponse(['message' => 'Post created successfully', 'id' => $postId], 201);
    }

    private function updateFromRequest(mixed $id, BaseRequest $request): void
    {
        $postId = $this->parseId($id);

        if ($postId === null) {
            $this->errorResponse('Invalid data', 422);

            return;
        }

        if ($this->posts->find($postId) === null) {
            $this->notFoundResponse('Post');

            return;
        }

        $this->persistUpdate($postId, $request);
    }

    private function persistUpdate(int $postId, BaseRequest $request): void
    {
        $description = $this->readDescription($request);

        if ($description === null) {
            $this->errorResponse('Invalid data', 422);

            return;
        }

        try { $this->update->execute($postId, $description); }
        catch (InvalidArgumentException $exception) { $this->errorResponse('Validation failed', 422, ['description' => [$exception->getMessage()]]); return; }
        $this->successResponse(['message' => 'Post updated successfully'], 200);
    }

    private function removePost(mixed $id): void
    {
        $postId = $this->parseId($id);

        if ($postId === null) {
            $this->errorResponse('Invalid data', 422);

            return;
        }

        if ($this->posts->find($postId) === null) {
            $this->notFoundResponse('Post');

            return;
        }

        $this->delete->execute($postId);
        $this->successResponse([], 204);
    }

    private function parseId(mixed $id): ?int
    {
        $parsed = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $parsed === false ? null : $parsed;
    }

    private function readDescription(BaseRequest $request): ?string
    {
        $raw = $request->body()['description'] ?? null;

        return is_string($raw) ? $raw : null;
    }

    private function logError(string $action, Throwable $exception): void
    {
        Logger::error('Request failed', ['action' => $action, 'exception_class' => $exception::class]);
    }
}
