# Quickstart

Reach a working request in about five minutes. Every command below was executed
against a fresh clone during validation.

## 1. Clone and install

```bash
git clone https://github.com/yanpenalva/phpenalva.git
cd phpenalva
cp .env.example .env
composer install
```

## 2. Prepare the database

The clone ships with a migrated SQLite database, so this is a no-op on a fresh
checkout. For a clean database, delete it and migrate:

```bash
rm -f database/database.sqlite
php bin/phpenalva migrate
```

Expected output:

```text
Applied migration: CreateUsersTable
Applied migration: CreatePostsTable
```

An empty or missing `DB_CONNECTION` selects SQLite. MySQL, PostgreSQL, and SQL
Server need their PDO driver installed and `DB_*` set in `.env`.

## 3. Serve and check the landing page

```bash
php -S 127.0.0.1:8000 -t public
```

In another terminal:

```bash
curl -s -o /dev/null -w "GET / -> %{http_code}\n" http://127.0.0.1:8000/
curl -s http://127.0.0.1:8000/api
```

Expected: `GET / -> 200` and a JSON envelope from `/api`:

```json
{"status":"success","message":"Welcome to the API PHPenalva","data":[],"errors":[]}
```

## 4. Register, log in, post

Session state needs a writable directory. The framework falls back to
`storage/sessions` automatically when the PHP default is missing or read-only,
so no setup is required.

Keep cookies between calls with a jar file:

```bash
JAR=/tmp/phpenalva-jar
TOKEN=$(curl -s -c "$JAR" http://127.0.0.1:8000/csrf-token | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["token"];')
curl -s -b "$JAR" -c "$JAR" -X POST http://127.0.0.1:8000/user/create \
  -H 'Content-Type: application/json' \
  -d "{\"name\":\"Ada\",\"email\":\"ada@example.test\",\"password\":\"secret123\",\"csrf_token\":\"$TOKEN\"}"
```

Expected: HTTP `201` with the created user (never a password hash).

Log in the same way against `POST /login` (HTTP `200`), then create a post.
Mutating requests require a fresh CSRF token each time:

```bash
TOKEN=$(curl -s -b "$JAR" -c "$JAR" http://127.0.0.1:8000/csrf-token | php -r '$d=json_decode(stream_get_contents(STDIN),true); echo $d["data"]["token"];')
curl -s -b "$JAR" -c "$JAR" -X POST http://127.0.0.1:8000/api/posts \
  -H 'Content-Type: application/json' \
  -d "{\"description\":\"My first post\",\"csrf_token\":\"$TOKEN\"}"
```

Expected: HTTP `201` with the post id.

## 5. Expose your own endpoint

Add the route in `routes/routes.php` **before** the final `return $route;`:

```php
$route[] = ['GET', '/hello', 'WelcomeController@hello'];
```

Add the action in `app/Controllers/WelcomeController.php`:

```php
public function hello(): void
{
    $this->successResponse(['message' => 'Hello from PHPenalva'], 200);
}
```

Verify:

```bash
curl -s http://127.0.0.1:8000/hello
```

Expected: HTTP `200` with `{"status":"success","message":"Hello from PHPenalva","data":[],"errors":[]}`.

Unknown paths return `404` (HTML for pages, JSON under `/api`). With
`APP_DEBUG=false` (the default), `/debug` also returns `404`.

Next: `docs/03-recipes.md` for full usage recipes and `docs/04-testing.md`
for running the suite.
