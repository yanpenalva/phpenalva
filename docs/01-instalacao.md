# Instalação

## Requisitos

PHP 8.0 ou superior, Composer, servidor web com reescrita de URL e extensão PDO para o driver escolhido. Instale as dependências com `composer install`; copie `.env.example` para `.env` e configure a conexão.

## Banco

O padrão é SQLite em `database/database.sqlite`. Para criar o schema execute `composer migrate`. Drivers aceitos pela configuração são `sqlite`, `mysql`, `pgsql` e `sqlsrv`; SQL Server é experimental e exige `pdo_sqlsrv`. Instale a extensão PDO correspondente e configure `DB_CONNECTION` e as variáveis `DB_*`.

## Testes e execução

Execute `composer test`, `composer lint` e `composer analyse`. Testes dos adapters criam bancos SQLite em memória/temporários e não usam o banco persistente. Para iniciar o ambiente Docker: `composer start`; para encerrar: `composer stop`.

## Autenticação HTTP

Obtenha CSRF em `GET /csrf-token`, mantenha o cookie de sessão e envie `X-CSRF-Token` em mutações. O limitador de login reside na memória do processo; múltiplos workers não compartilham contagem.
