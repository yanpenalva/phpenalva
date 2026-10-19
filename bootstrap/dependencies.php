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
use Core\BaseAuthenticate;
use Core\BaseDatabase;

function userRepository(): PdoUserRepository
{
    return new PdoUserRepository((new BaseDatabase())->getDatabase());
}

function postRepository(): PdoPostRepository
{
    return new PdoPostRepository((new BaseDatabase())->getDatabase());
}

function authService(): AuthService
{
    $users = userRepository();
    return new AuthService($users, new BaseAuthenticate($users));
}

function authenticate(): bool
{
    return (new BaseAuthenticate(userRepository()))->check() !== null;
}

function registerUser(): RegisterUser { return new RegisterUser(userRepository()); }
function deleteUser(): DeleteUser { return new DeleteUser(userRepository()); }
function createPost(): CreatePost { return new CreatePost(postRepository()); }
function updatePost(): UpdatePost { return new UpdatePost(postRepository()); }
function deletePost(): DeletePost { return new DeletePost(postRepository()); }
