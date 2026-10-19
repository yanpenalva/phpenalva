# Recipes

Commands run from the repository root. Composer maps `App\\` to `app/`. These recipes show the repository's current wiring and avoid assuming generated scaffolding.

## Create a bounded context

**Goal:** create the Domain, Application, and Infrastructure boundaries.

**Files:** directories `app/Billing/Domain/`, `app/Billing/Application/`, and `app/Billing/Infrastructure/Persistence/`; then add PHP classes under those paths. Add composition in `bootstrap/dependencies.php`, and tests under `tests/Unit/Billing/` (create the directory).

```sh
mkdir -p app/Billing/Domain app/Billing/Application app/Billing/Infrastructure/Persistence tests/Unit/Billing
```

**Expected output:** no output; the five directories exist. **Verify:**

```sh
test -d app/Billing/Domain && test -d app/Billing/Application && test -d app/Billing/Infrastructure/Persistence && test -d tests/Unit/Billing && echo 'Billing context directories ready'
```

Expected: `Billing context directories ready`. Match the existing context shape in `app/Content/` and `app/Identity/`; Composer discovers new `App\\` classes through its existing PSR-4 mapping.

## Add a use case

**Goal:** coordinate an application operation through a domain repository contract. Example: a focused `ArchivePost` operation (no archive behavior currently exists, so persistence changes must be added if archiving state is required).

**Files to create/edit:** `app/Content/Application/ArchivePost.php`; a behavior test in `tests/Unit/Content/ContentTest.php`; and, when invoked by HTTP, factory registration in `bootstrap/dependencies.php` plus the relevant controller wiring. Existing use cases expose `execute(...)` and receive repository interfaces.

```sh
sed -n '1,180p' app/Content/Application/DeletePost.php
sed -n '1,100p' app/Content/Domain/PostRepository.php
```

**Expected output:** current deletion use-case and repository source. **Verify:** build the class against that interface; run `vendor/bin/phpunit --configuration phpunit.xml --filter ContentTest` (the Composer script passes arguments to PHPUnit).

## Add a value object

**Goal:** enforce a domain value invariant at construction. Existing example: `PostDescription`.

**Files to create/edit:** `app/Content/Domain/PostTitle.php` and `tests/Unit/Content/ContentTest.php`.

```sh
sed -n '1,140p' app/Content/Domain/PostDescription.php
```

**Expected output:** constructor validation and typed `value()` pattern. Implement `PostTitle` with strict types and an invariant supported by the feature, then test accepted, rejected, and boundary values. **Verify:** `vendor/bin/phpunit --configuration phpunit.xml --filter ContentTest` reports passing `ContentTest` cases.

## Expose an endpoint

**Goal:** expose an application operation through the existing route/controller path.

**Files to create/edit:** `app/Controllers/PostController.php`, `routes/routes.php`, `bootstrap/dependencies.php`, `bootstrap/Routes.php`, and `tests/Feature/HttpContractTest.php`.

```sh
sed -n '1,220p' app/Controllers/PostController.php
cat routes/routes.php
```

**Expected output:** controller action and route tuple conventions. Add a route, controller action, dependency factory/mapping, and contract assertion following those patterns. Authenticated mutations require session authentication and CSRF. **Verify:** `vendor/bin/phpunit --configuration phpunit.xml --filter HttpContractTest`.

## Add a repository adapter

**Goal:** implement a domain repository contract using PDO and explicit mapping.

**Files to create/edit:** the adapter under `app/Content/Infrastructure/Persistence/`, its composition in `bootstrap/dependencies.php` and (for a controller) `bootstrap/Routes.php`, plus an adapter test in `tests/Unit/Content/ContentTest.php`.

```sh
sed -n '1,220p' app/Content/Infrastructure/Persistence/PdoPostRepository.php
sed -n '1,100p' app/Content/Domain/PostRepository.php
```

**Expected output:** adapter and interface conventions. Use prepared statements and explicit row-to-domain mapping. In the adapter test, create only the needed schema in `new PDO('sqlite::memory:')`, then assert insert/read/delete behavior. **Verify:** `vendor/bin/phpunit --configuration phpunit.xml --filter ContentTest`.

## Add a migration for a new table

**Goal:** add driver-aware DDL for SQLite, MySQL, PostgreSQL, and SQL Server, then register it explicitly. Migration files are not auto-discovered.

**Files to create/edit:** `database/migrations/CreateInvoicesTable.php` (example table), `core/MigrationManager.php` (require, migration/dependency order, rollback order, and factory match), and `tests/Feature/MigrationManagerTest.php` (SQLite lifecycle and driver DDL checks).

```sh
sed -n '1,180p' database/migrations/CreatePostsTable.php
sed -n '1,100p' core/BaseMigration.php
```

**Expected output:** migration `up()`/`down()` and base validation/execute pattern. Implement each supported driver's DDL; make `down()` require the existing force signal; place dependencies in both manager orders and instantiate the migration in its factory match. Add SQLite and recording-PDO assertions.

```sh
vendor/bin/phpunit --configuration phpunit.xml --filter MigrationManagerTest
```

Expected: PHPUnit reports `MigrationManagerTest` passed. To apply the registered migration to the configured database, run `composer migrate`; expected output is `Applied migration: CreateInvoicesTable` when pending, or `No migrations changed.` otherwise. **Verify:** inspect `migrations` history and the table using the configured database client; never run rollback against data that must be retained.
