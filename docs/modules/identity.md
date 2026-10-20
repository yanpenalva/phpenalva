# Identity

## 1. Overview

Identity handles user registration and deletion, credential lookup, session login/logout, and login-attempt throttling. `Email` trims and lowercases addresses, validates their format, and enforces a 100-character maximum. `User` validates a non-empty name up to 100 characters and a non-empty password hash. `RegisterUser` validates the supplied password before hashing and returns public user data without the hash.

## 2. Models/tables

The `users` table contains `id`, `name`, `email`, `password`, `created_at`, and `updated_at`. `email` has a unique constraint. `App\Identity\Domain\User` is the entity; `Email` is its value object. `AttemptStore` defines recent-attempt lookup, recording, and clearing; its attempt records are stored by the persistence adapter rather than in `users`.

## 3. Controllers

`UserController` serves `POST /user/create` and `DELETE /user/delete/{id}`. It delegates to `RegisterUser` and `DeleteUser`; invalid registration or identifiers return `422`, persistence failures return `500`, missing users return `404`, successful registration returns `201`, and successful deletion returns `204` with no body.

`AuthController` serves `GET /csrf-token`, `POST /login`, and `POST /logout`. Invalid credentials return `401`; throttling returns `429`; invalid request/CSRF data returns an error response. Controllers validate HTTP input and delegate application behavior to use cases/services.

## 4. Services

`RegisterUser` validates the original password, checks email uniqueness, hashes the password, and persists the user. A database unique constraint also protects email uniqueness. `DeleteUser` removes a user by identifier.

`AuthService` verifies credentials through `CredentialLookup`, uses a dummy hash when an account is absent, starts a session on success, and clears recorded failures after successful login. `AuthRateLimiter` uses the `AttemptStore` domain contract. `FileAttemptStore` records account/IP-keyed timestamps in files, uses `flock` for concurrent local access, and fails open on I/O errors. The limit is five failures over 900 seconds. The store is file-backed and scoped to processes sharing a machine filesystem; it does not coordinate separate machines.

## 5. Repositories

`UserRepository` defines user persistence operations. `PdoUserRepository` implements the repository and `Core\CredentialLookup`, providing registration checks, credential lookup, public lookup by ID, and deletion. Recognized unique-email conflicts become `EmailAlreadyUsed`; other database failures propagate.

## 6. Routes/middlewares

Routes are declared in `routes/routes.php`. Mutating endpoints pass through `RequestGuard` input and CSRF checks. The delete-user route is marked `auth`; registration and login are not marked as authenticated routes. `Core\BaseAuthenticate` depends on `Core\CredentialLookup`, which the Identity adapter implements without making the framework depend on `App`.

## 7. State machine

There is no persisted user lifecycle state. A session transitions from anonymous to authenticated after valid credentials and is destroyed on logout. Failed login timestamps remain in the store until older than the 900-second window; successful login clears the account/IP key. At five recent failures, login is throttled.

## 8. Frontend

Identity has no dedicated frontend. HTTP clients use the session cookie and CSRF token. `GET /csrf-token` returns the session-bound token.

## 9. Permissions/policies

Routes marked `auth` require an authenticated session. Mutating routes require CSRF validation. The database foreign key for `posts.user_id` uses `ON DELETE SET NULL` on MySQL, PostgreSQL, and SQL Server; SQLite DDL declares the foreign key without that delete action. No additional ownership policy is enforced by the documented Identity routes.
