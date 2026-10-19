# Recipes

Commands run from the repository root. Composer autoload maps `App\\` to `app/`.

## Create a bounded context

```sh
mkdir -p app/Billing/{Domain,Application,Infrastructure/Persistence}
```

Add domain entities and repository contracts to `app/Billing/Domain/`, use cases to `app/Billing/Application/`, and persistence adapters to `app/Billing/Infrastructure/Persistence/`. Register factories in `bootstrap/dependencies.php`. Keep domain code independent of controllers, PDO, sessions, and `Core`.

## Add a use case

Create `app/Content/Application/ArchivePost.php` with `declare(strict_types=1);`, inject `PostRepository`, and expose an `execute(...)` method. Add tests to `tests/Unit/Content/ContentTest.php`, register a factory in `bootstrap/dependencies.php`, and update `docs/modules/content.md`.

## Add a value object

Create `app/Content/Domain/PostTitle.php`. Validate and normalize the value in the constructor and expose it through a typed method. Add valid, invalid, and boundary cases to `tests/Unit/Content/ContentTest.php`.

## Expose an endpoint

1. Add an HTTP action in `app/Controllers/PostController.php`. Parse and guard HTTP input there; delegate writes to an application use case.
2. Declare the method and path in `routes/routes.php`.
3. Add required factories to `bootstrap/dependencies.php` and map them in `bootstrap/Routes.php`.
4. Add or update the HTTP contract case in `tests/Feature/HttpContractTest.php`.
5. Document the endpoint in `docs/modules/content.md`.

Mutations require the session CSRF token: obtain it from `GET /csrf-token`, retain the session cookie, and send `X-CSRF-Token` with the request. `composer test` runs the test suite.
