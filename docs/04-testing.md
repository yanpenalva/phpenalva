# Testing

## Suites and coverage

`phpunit.xml` discovers `*Test.php` files under `tests/` in one `PHPenalva` suite.

| Location | What is exercised |
|---|---|
| `tests/Unit/Content/ContentTest.php` | Post description boundaries, entity change, use cases against an in-memory repository, and `PdoPostRepository` against SQLite memory. |
| `tests/Unit/Identity/IdentityTest.php` | Email/user rules, registration and duplicate handling, `PdoUserRepository` and race handling using SQLite memory, and file-backed login-attempt limits including a separate PHP process. |
| `tests/Unit/DatabaseConfigTest.php` | Database configuration and supported driver selection. |
| `tests/Unit/LoggerTest.php` | Logger behavior. |
| `tests/Unit/Console/ConsoleTest.php` | CLI command output and exit codes in subprocesses using temporary route and log fixtures. |
| `tests/Feature/HttpContractTest.php` | Live PHP HTTP server, JSON envelope, routing/errors, registration and post flows, authentication/CSRF, malformed bodies, and persistence errors with a temporary SQLite database. |
| `tests/Feature/MigrationManagerTest.php` | SQLite migration, ordering, and rollback lifecycle; MySQL/PostgreSQL/SQL Server DDL strings through a recording PDO double, not live servers. |

No separate test harness directories exist beyond those feature helpers; `HttpTestRouter.php` is the HTTP test router harness. SQL Server, MySQL, and PostgreSQL are not exercised against live database servers.

## Commands

From the repository root:

```sh
composer test
vendor/bin/phpunit --configuration phpunit.xml
vendor/bin/phpunit --configuration phpunit.xml --filter ContentTest
vendor/bin/phpunit --configuration phpunit.xml tests/Feature/HttpContractTest.php
composer lint
composer analyse
```

`composer test` runs the same configured PHPUnit suite. A filter can select a test class or method name, for example `--filter testDescriptionBoundariesAndPostChange`. Lint and PHPStan are separate scripts, not part of `composer test`.

## Test patterns

### Domain test

Add a method to `tests/Unit/Content/ContentTest.php`; current tests construct domain values directly and assert their behavior:

```php
public function testDescriptionBoundariesAndPostChange(): void
{
    self::assertSame(6, strlen((new PostDescription('123456'))->value()));
    $this->expectException(InvalidArgumentException::class);
    new PostDescription('short');
}
```

### Adapter test

Use the adapter's real PDO implementation with a minimal in-memory schema, as in `ContentTest` and `IdentityTest`:

```php
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, description TEXT, user_id INTEGER NULL)');
$repository = new PdoPostRepository($pdo);
```

Assert persisted results and relevant error cases. This does not require or alter `database/database.sqlite`.

### HTTP contract test

Add an assertion to `tests/Feature/HttpContractTest.php`. Its setup starts a local PHP server, migrates a per-test temporary SQLite database, retains the session cookie, and obtains a CSRF token. Existing request helpers should be used rather than starting another server:

```php
$this->login();
$response = $this->request('POST', '/api/posts', ['description' => 'Created through JSON']);
self::assertSame(201, $response['status']);
self::assertSame('success', $this->decodeResponse($response)['status']);
```

The setup/teardown owns temporary database and session files. Adapter tests use SQLite `:memory:`; feature tests use `tempnam(sys_get_temp_dir(), 'phpenalva-test-')` and remove the files. Avoid fixed database paths and shared mutable fixtures.

## Intentional limits

There are no live-server integration tests for MySQL, PostgreSQL, or SQL Server; no independent browser/UI tests; no dedicated tests for every controller branch, migration CLI output, Docker startup, or cross-machine file-store coordination. These are not established by the current automated suite.
