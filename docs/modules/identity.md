# Identity

## 1. Overview

Gerencia cadastro, remoção e autenticação por sessão. `Email` normaliza para minúsculas após trim e exige formato válido com até 100 caracteres. `User` protege nome e hash não vazio. `RegisterUser` valida senha original antes de gerar hash; retorna somente id, nome e e-mail. Unicidade é verificada antes do INSERT e novamente pela constraint.

## 2. Models/tables

Tabela `users`: `id`, `name`, `email` (unique, indexado), `password`, timestamps. Entidade `App\Identity\Domain\User`; value object `Email`.

## 3. Controllers

`UserController`: criação e remoção; delega para `RegisterUser` e `DeleteUser`; erros de entrada/duplicidade retornam 422, remoção inexistente 404. `AuthController`: CSRF, login/logout; credencial inválida retorna 401 e throttling 429.

## 4. Services

`RegisterUser` valida, coordena unicidade, hash e persistência. `DeleteUser` remove por id. `AuthService` verifica credenciais com hash dummy em conta inexistente, inicia sessão e limpa falhas em sucesso. `AuthRateLimiter` usa `AttemptStore`; `FileAttemptStore` coordena processos na mesma máquina com `flock`, guarda cinco falhas por 900 segundos e falha aberta em erro de IO para não derrubar login. Em multi-máquina, os arquivos não são compartilhados; Redis poderá substituir o adapter futuramente.

## 5. Repositories

`UserRepository` é contrato de domínio. `PdoUserRepository` implementa cadastro, busca de credenciais, consulta pública por id e remoção. Somente conflito unique reconhecido é convertido em `EmailAlreadyUsed`; outros erros propagam.

## 6. Routes/middlewares

Rotas de usuário e autenticação ficam em `routes/routes.php`; `RequestGuard` valida entrada e CSRF. `BaseAuthenticate` recebe `Core\CredentialLookup`; o adapter Identity implementa o contrato, sem o framework depender de `App`.

## 7. State machine

Não há estados persistidos do usuário. Sessão: anônima → autenticada após credencial válida; logout destrói sessão. Falhas acumulam até limite e expiram por janela temporal.

## 8. Frontend

Não há frontend próprio do contexto; consumidores HTTP usam cookie de sessão e token CSRF.

## 9. Permissions/policies

Rotas marcadas `auth` exigem sessão e CSRF onde aplicável. Política de remoção de usuário com posts vinculados não está definida (`NOT FOUND`); nenhuma cascata foi adicionada.
