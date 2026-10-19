<?php

declare(strict_types=1);

// Routes for web.
// Mutating routes require the session CSRF token; the protected ones are marked
// with 'auth' and are additionally rejected without an authenticated session.
$route[] = ['GET', '/', 'WelcomeController@index'];
$route[] = ['GET', '/api', 'WelcomeController@apiIndex'];
$route[] = ['GET', '/csrf-token', 'AuthController@csrfToken'];

$route[] = ['POST', '/login', 'AuthController@login'];
$route[] = ['POST', '/logout', 'AuthController@logout'];

$route[] = ['POST', '/api/posts', 'PostController@store', 'auth'];
$route[] = ['GET', '/posts', 'PostController@index', 'auth'];
$route[] = ['GET', '/contact', 'WelcomeController@contact'];
$route[] = ['GET', '/post/{id}', 'PostController@show'];
$route[] = ['POST', '/user/create', 'UserController@store'];
$route[] = ['DELETE', '/user/delete/{id}', 'UserController@delete', 'auth'];
$route[] = ['PUT', '/api/post/{id}', 'PostController@update', 'auth'];
$route[] = ['DELETE', '/api/post/{id}', 'PostController@delete', 'auth'];

return $route;
