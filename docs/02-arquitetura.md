# Arquitetura

## Contextos e camadas

`App\Identity` contém identidade, cadastro e autenticação. `App\Content` contém posts. Cada contexto é organizado em `Domain`, `Application` e `Infrastructure\Persistence`. `Core` oferece mecanismos do framework; controllers fazem a borda HTTP; `bootstrap/dependencies.php` compõe dependências.

## Dependências permitidas

- Domain depende de PHP e de tipos do próprio domínio.
- Application depende do domínio e coordena casos de uso; não acessa HTTP nem SQL.
- Infrastructure implementa contratos de domínio e depende de PDO.
- Controllers dependem de casos de uso, repositórios somente para consultas de apresentação e contratos HTTP.
- Bootstrap instancia adapters e injeta dependências.

## Dependências proibidas

Domain não depende de `Core`, controllers, sessão ou PDO. `Core` não referencia namespaces `App`. Um contexto não carrega entidades/repositórios internos de outro contexto; use identificadores ou contratos públicos. SQL e formatos de linha ficam nos adapters.

## Persistência e segurança

Migrations existentes são mantidas; mudanças estruturais exigem migration registrada em `core/MigrationManager.php`. `posts.user_id` é opcional e usa `ON DELETE SET NULL`; `users.email` tem restrição única (e índice). Autenticação usa cookie, CSRF e respostas genéricas para credenciais inválidas. Matriz de drivers: SQLite verificado ao vivo; PostgreSQL 17.11 declarado, NOT VERIFIED ao vivo nesta execução (socket local indisponível); MySQL e SQL Server declarados, NOT VERIFIED ao vivo (drivers locais ausentes). O rate limiter usa arquivos compartilhados entre processos locais; multi-máquina exige store como Redis.
