<?php

declare(strict_types=1);

namespace Bootstrap;

use App\Identity\Application\DeleteUser;
use App\Identity\Application\RegisterUser;
use App\Identity\Application\AuthService;
use App\Content\Application\CreatePost;
use App\Content\Application\DeletePost;
use App\Content\Application\UpdatePost;
use App\Content\Infrastructure\Persistence\PdoPostRepository;
use App\Identity\Infrastructure\Persistence\PdoUserRepository;
use App\Services\PostService;
use App\Services\UserService;
use App\Models\User;
use Core\BaseAuthenticate;
use Core\BaseDatabase;

function userService(): UserService
{
    $repository = new PdoUserRepository((new BaseDatabase())->getDatabase());

    return new UserService(new RegisterUser($repository), new DeleteUser($repository), $repository);
}

function postService(): PostService
{
    $repository = new PdoPostRepository((new BaseDatabase())->getDatabase());
    return new PostService($repository, new CreatePost($repository), new UpdatePost($repository), new DeletePost($repository));
}

function authService(): AuthService
{
    $pdo = (new BaseDatabase())->getDatabase();
    return new AuthService(new PdoUserRepository($pdo), new BaseAuthenticate(new User($pdo)));
}

function authenticate(): bool
{
    $pdo = (new BaseDatabase())->getDatabase();
    return (new BaseAuthenticate(new User($pdo)))->check() !== null;
}
