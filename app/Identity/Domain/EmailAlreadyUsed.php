<?php

declare(strict_types=1);

namespace App\Identity\Domain;

use RuntimeException;

final class EmailAlreadyUsed extends RuntimeException
{
}
