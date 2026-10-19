
# PHPenalva 𓅓
![logomarca](public/assets/images/logomarca.png)

### Overview
PHPenalva is a lightweight PHP micro-framework designed on the Model-View-Controller (MVC) architecture.<br>
Built with simplicity in mind, PHPenalva empowers you to swiftly create APIs and web applications.<br>

**Note**: This project is currently under construction. Please bear with us as we work to make it even better.

### Requirements
- **PHP Version**: PHP 7.4 or higher is required.
- **Web Server**: You'll need a web server with URL rewriting enabled.
- **Supported Servers**: PHPenalva plays nicely with Apache, Nginx, and IIS.
- **Database Compatibility**: PHPenalva is compatible with MySQL, MariaDB, PostgreSQL, and SQLite.
- **Platform**: PHPenalva can be used on Linux, Windows, and macOS.

### Installation
Getting started with PHPenalva is a breeze. <br>
You can install it via Composer with the following command in your project directory:<br>
`composer create-project hiyan/phpenalva your_project_name`

### Development Environment

PHPenalva comes with a pre-configured development environment using Docker, which simplifies the setup and execution of the microframework without the need to install servers and dependencies directly on your machine.

#### How to Use the Development Environment

1. **Starting the Environment**: You can start the development environment with the following command. This will launch the necessary Docker containers to run PHPenalva:
   ```bash
   composer start
   ```
   
   This command runs `docker compose up -d`, starting the containers with a web server and database ready for use.

2. **Environment Structure**: All Docker environment configurations are located in the `.docker` directory. In this directory, you'll find configuration files like `Dockerfile` and `nginx.conf`, which define how the environment is built and how services (PHP, NGINX, etc.) are configured.

3. **Customization**: If you need to adjust the development environment (e.g., change NGINX settings or add PHP extensions), you can edit the files within the `.docker` directory to customize the environment as needed.

4. **Stopping the Environment**: To stop the development environment, use the following command:
   ```bash
   composer stop
   ```
   This command runs `docker compose down`, shutting down the running containers.

With this setup, you can focus on developing with PHPenalva without worrying about complex local configurations. Simply run `composer start` to begin and `composer stop` to halt the environment as needed.

These commands make it easy to manage the environment, allowing you to work directly on the project with convenience! 🚀

### Migrations

PHPenalva includes a simple migration system that allows you to define and manage your database schema. The migration system is managed through classes and a custom Composer command, making it easy to create tables, modify columns, and keep track of changes over time.

#### How it Works

1. **Migration Classes**: Each migration is a PHP class stored in the `database/migrations` directory. These classes extend the base class `BaseMigration` and define two methods: `up()` (to apply the migration) and `down()` (to reverse it).
   
2. **Migration Manager**: The `MigrationManager` class, located in `core`, handles the execution of migrations. It checks for all migration files, applies any new migrations that haven't been executed yet, and logs them in a special table to keep track.

3. **Custom Composer Command**: We’ve added a `composer migrate` command to simplify the execution of migrations. This command looks for all new migrations in the `database/migrations` directory and applies them.

#### Running Migrations

To apply all pending migrations, simply run:

```bash
composer migrate
```

This command will create a `migrations` table in the database (if it doesn’t already exist) to keep track of which migrations have been applied. Then, it will execute each migration's `up()` method that hasn't been applied yet.

#### Creating a New Migration

1. Create a new file in `database/migrations`, with a name that reflects the purpose of the migration (e.g., `CreateUsersTable.php`).
   
2. Define a class with the same name as the file and extend `Core\BaseMigration`.

Example of a migration to create a `users` table:

```php
<?php

use Core\BaseMigration;

class CreateUsersTable extends BaseMigration
{
    public function up()
    {
        $this->execute("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name VARCHAR(100),
            email VARCHAR(100) UNIQUE,
            password VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }

    public function down()
    {
        $this->execute("DROP TABLE IF EXISTS users");
    }
}
```

#### Customizing the Migrations

You can customize your migrations by editing the individual migration classes in `database/migrations`. Each class can contain any SQL statements needed to modify the schema as required. To apply specific changes, modify the `up()` method, and to undo those changes, modify the `down()` method.

The migration system is flexible, allowing you to manage changes to the database schema over time, ensuring consistency across environments.

---

This section provides a straightforward way to manage your database schema changes with migrations, making it easy to apply and roll back changes using the `composer migrate` command.

### Supported Database Drivers

- SQLite is the primary driver and is used for local migrations and functional tests. It requires `pdo_sqlite`.
- MySQL and PostgreSQL migrations require local verification with `pdo_mysql` and `pdo_pgsql`.
- SQL Server migrations are experimental, require `PDO_SQLSRV`, and are not included in CI.
- Any `DB_CONNECTION` outside `sqlite`, `mysql`, `pgsql`, and `sqlsrv` is rejected during configuration.
- Schema rollback requires explicit `--force`; it drops schema objects and cannot recover deleted data, so arrange a separate backup.

### Tests and CI Gates

Run the local checks with:

```bash
composer validate
composer lint
composer test
composer analyse
```

Checks run locally for this landing; automatic push/PR workflow triggers are disabled. The retained manual workflow defines PHP 8.0–8.3 gates and MySQL/PostgreSQL checks, but is not evidence that those environments passed.

Authentication uses session cookies, not Bearer tokens. Obtain a token from `GET /csrf-token`, retain the session cookie, and send `X-CSRF-Token` on mutations (including registration and login). Send credentials and post descriptions in the form or JSON body; PUT also accepts URL-encoded bodies. JSON responses use `{status,message,data,errors}`; successful DELETE returns 204 without a body.
