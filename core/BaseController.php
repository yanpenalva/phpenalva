<?php

declare(strict_types=1);

namespace Core;

use Exception;
use stdClass;
use function file_exists;
use function http_response_code;
use function htmlspecialchars;
use function sprintf;
use function str_replace;

abstract class BaseController
{
    protected string $viewPath = '';
    protected string $layoutPath = '';

    /** @var object */
    protected $view;

    protected string $pageTitle = '';

    public function __construct()
    {
        $this->view = new stdClass();
        $this->pageTitle = '';
    }

    protected function getViewPath(string $viewName): string
    {
        foreach (['.phtml', '.html'] as $extension) {
            $filePath = sprintf('%s/../app/Views/%s%s', __DIR__, $viewName, $extension);

            if (file_exists($filePath)) {
                return $filePath;
            }
        }

        return sprintf('%s/../app/Views/%s.phtml', __DIR__, $viewName);
    }

    protected function renderView(string $viewName, ?string $layoutName = null): void
    {
        DebugCollector::checkpoint('render start');
        $this->viewPath = $this->getViewPath($viewName);
        $this->layoutPath = $this->getViewPath((string) $layoutName);

        if ($layoutName !== null && $layoutName !== '') {
            $debugPanel = $viewName === '/System/debug';
            if ($debugPanel) {
                ob_start();
            }
            $this->getLayout();
            DebugCollector::checkpoint('render end');
            if ($debugPanel) {
                $rendered = ob_get_clean();
                echo str_replace('<!--DEBUG_TIMELINE-->', htmlspecialchars(var_export(DebugCollector::timeline(), true), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), (string) $rendered);
            }

            return;
        }

        $this->getContent();
        DebugCollector::checkpoint('render end');
    }

    protected function getLayout(): void
    {
        if (file_exists($this->layoutPath)) {
            require_once $this->layoutPath;

            return;
        }

        $this->renderExceptionView(404, 'Layout path not found.');
    }

    protected function getContent(): void
    {
        if (file_exists($this->viewPath)) {
            require_once $this->viewPath;

            return;
        }

        $this->renderExceptionView(404, 'View path not found.');
    }

    protected function renderExceptionView(int $statusCode, string $message): void
    {
        http_response_code($statusCode);
        $errorDetails = [
            'message' => $message,
            'status_code' => $statusCode,
        ];

        $exceptionPath = $this->getViewPath('/System/exception');

        if (file_exists($exceptionPath)) {
            require_once $exceptionPath;

            return;
        }

        throw new Exception('Exception view not found.', 500);
    }

    protected function setPageTitle(string $pageTitle): void
    {
        $this->pageTitle = $pageTitle;
    }

    protected function getPageTitle(?string $separator = null): string
    {
        if ($separator !== null && $separator !== '' && $this->pageTitle !== '') {
            return sprintf('%s %s ', $this->pageTitle, $separator);
        }

        return $this->pageTitle;
    }

    /**
     * Handle forbidden requests by returning an array with an error message.
     */
    public function forbidden(string $message = 'Unauthorized'): array
    {
        return [
            'error' => $message,
            'status' => 403,
        ];
    }
}
