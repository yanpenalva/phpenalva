# PHPenalva

PHPenalva is a PHP application framework organized around HTTP entry points, bounded contexts, and PDO persistence. This README is a navigation hub; implementation detail belongs in the linked guides.

## Table of Contents

- [Usage Recipes](#usage-recipes)
- [Testing](#testing)
- [Architecture](#architecture)
- [Installation](#installation)
- [Best Practices](#best-practices)
- [Conventions](#conventions)
- [Modules](#modules)

## Usage Recipes

Executable workflows cover contexts, use cases, value objects, endpoints, repository adapters, and migrations.
Each recipe names paths, commands, expected output, and verification.

**Full guide:** [docs/03-recipes.md](docs/03-recipes.md) · [Create context](docs/03-recipes.md#create-a-bounded-context) · [Add use case](docs/03-recipes.md#add-a-use-case) · [Add value object](docs/03-recipes.md#add-a-value-object) · [Expose endpoint](docs/03-recipes.md#expose-an-endpoint) · [Add repository adapter](docs/03-recipes.md#add-a-repository-adapter) · [Add table migration](docs/03-recipes.md#add-a-migration-for-a-new-table)

## Testing

Unit tests cover domain rules and adapters; feature tests exercise the HTTP contract and migration manager.
The guide records the precise suite boundaries, commands, test patterns, and known coverage gaps.

**Full guide:** [docs/04-testing.md](docs/04-testing.md)

## Architecture

Identity and Content are bounded contexts split into Domain, Application, and Infrastructure layers.
Core provides shared HTTP, session, database, and migration facilities.

**Full guide:** [docs/02-architecture.md](docs/02-architecture.md) · [Identity module](docs/modules/identity.md) · [Content module](docs/modules/content.md)

## Installation

Install Composer dependencies, configure the PDO driver through `.env`, and apply registered migrations.
The Docker Compose development server is exposed on port 8081.

**Full guide:** [docs/01-installation.md](docs/01-installation.md)

## Best Practices

Keep domain and application rules independent of HTTP and SQL; compose adapters in bootstrap and keep controllers thin.
Use parameterized SQL, preserve the JSON response contract, and arrange backups before destructive rollback.

**Full guide:** [Best practices](docs/02-architecture.md#dependency-rules) · [Migration safety](docs/01-installation.md#migrations)

## Conventions

PHP files use strict types and explicit types; behavior changes require focused tests.
Commit messages use the Conventional Commit form `<type>: <description>`.

**Full guide:** [Code and commit conventions](docs/02-architecture.md#project-conventions)

## Modules

- [Identity](docs/modules/identity.md)
- [Content](docs/modules/content.md)
