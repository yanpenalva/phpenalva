<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;
use PDO;

class Post extends BaseModel
{
    /** @var string */
    protected $table = 'posts';

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
    }

    public static function rules(): array
    {
        return [
            'description' => 'required|min:6|max:255',
        ];
    }
}
