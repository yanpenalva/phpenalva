<?php

declare(strict_types=1);

namespace App\Repositories;

use Core\BaseModel;
use PDO;

final class PostRepository extends BaseModel
{
    /** @var string */
    protected $table = 'posts';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }
}
