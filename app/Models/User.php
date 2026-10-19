<?php

declare(strict_types=1);

namespace App\Models;

use Core\BaseModel;

/**
 * Users table model: table name and validation rules only. Queries and writes
 * live in App\Repositories\UserRepository.
 */
class User extends BaseModel
{
    protected $table = 'users';

    public function __construct(\PDO $pdo)
    {
        parent::__construct($pdo);
    }

    /**
     * Rules applied to the original payload, before any password hashing.
     * Lengths match the users table columns.
     *
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:100',
            'email' => 'required|string|email|max:100',
            'password' => 'required|string|min:6|max:255',
        ];
    }
}
