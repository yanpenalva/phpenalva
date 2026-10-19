# Receitas

Os comandos abaixo são executados na raiz do projeto. O autoload `App\\` aponta para `app/`.

## Criar contexto

```sh
mkdir -p app/Billing/Domain app/Billing/Application app/Billing/Infrastructure/Persistence
```

Crie `app/Billing/Domain/Invoice.php`, contratos usados pelo contexto em `Domain/`, casos de uso em `Application/` e adapters em `Infrastructure/Persistence/`. Não importe controller, PDO ou `Core` no domínio.

## Adicionar caso de uso

Crie `app/Content/Application/ArchivePost.php` com `declare(strict_types=1)`, injete `PostRepository` e exponha `execute(...)`. Adicione teste em `tests/Unit/Content/`; registre a factory em `bootstrap/dependencies.php` e atualize `docs/modules/content.md`.

## Adicionar Value Object

Crie `app/Content/Domain/PostTitle.php`; valide e normalize no construtor e exponha o valor por método tipado. Adicione testes válidos, inválidos e limites em `tests/Unit/Content/ContentTest.php`. Atualize o módulo dono.

## Expor endpoint

1. Crie o método de borda em `app/Controllers/PostController.php`; valide formato HTTP, aplique RequestGuard/CSRF e chame o caso de uso.
2. Registre rota em `routes/routes.php`.
3. Registre factory com dependências explícitas em `bootstrap/dependencies.php` e faça o mapeamento em `bootstrap/Routes.php`.
4. Preserve envelope/status e acrescente contrato em `tests/Feature/HttpContractTest.php`.
5. Atualize `docs/modules/content.md` (controller, rota e contrato).

Verifique com `composer test`, `composer lint` e `composer analyse`.
