<?php

declare(strict_types=1);

namespace Core;

use function header;
use function sprintf;

class BaseRedirect
{
    public static function redirect(string $url): void
    {
        header(sprintf('Location: %s', $url));
        exit;
    }
}
