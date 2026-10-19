<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Traits\RestResponseTrait;
use Core\BaseController;
use Throwable;
use function error_log;
use function sprintf;
use function str_replace;
use function substr;

class WelcomeController extends BaseController
{
    use RestResponseTrait;

    public function index(): void
    {
        $this->setPageTitle('Home');
        $this->renderView('/System/layout');
    }

    public function contact(): void
    {
        $this->setPageTitle('Contact');
        $this->renderView('/Home/contact');
    }

    public function apiIndex(): void
    {
        try {
            $this->successResponse(['message' => 'Welcome to the API PHPenalva'], 200);
        } catch (Throwable $exception) {
            // TODO(logger): frente C deve ligar o logger estruturado aqui.
            error_log(sprintf('WelcomeController apiIndex failed: %s', substr(str_replace("\n", ' ', $exception->getMessage()), 0, 200)));
            $this->errorResponse('Internal Server Error', 500);
        }
    }
}
