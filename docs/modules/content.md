# Content

## 1. Overview

Gerencia consulta, criação, atualização e remoção de posts. `PostDescription` aceita de 6 a 255 caracteres. `Post::changeDescription()` exige um value object já válido.

## 2. Models/tables

Tabela `posts`: `id`, `user_id` nullable, `description`, timestamps; FK para `users`. Agregado `Post` mantém id, descrição e authorId opcional. Schema não foi alterado.

## 3. Controllers

`PostController` atende listagem/detalhe e mutações; chamadas de escrita passam por `CreatePost`, `UpdatePost` e `DeletePost`. Descrição inválida responde 422; ids inválidos 422; item ausente 404.

## 4. Services

Casos de uso validam via domínio e persistem pelo contrato `PostRepository`. Não existe regra de publicação/moderação.

## 5. Repositories

`PostRepository` expõe listagem, busca, inclusão, salvamento e remoção. `PdoPostRepository` mapeia explicitamente SQL para entidade; parâmetros são vinculados.

## 6. Routes/middlewares

Rotas declaradas em `routes/routes.php`; mutações passam por `RequestGuard` e CSRF. As rotas protegidas também exigem autenticação conforme configuração explícita.

## 7. State machine

Não existe ciclo de publicação ou estado persistido; criação, edição e remoção são as operações disponíveis.

## 8. Frontend

Views em `app/Views/Posts/index.phtml` e `show.phtml`; controllers fornecem listagem e entidade encontrada.

## 9. Permissions/policies

Obrigatoriedade de autor e autorização por autor não estão definidas (`NOT FOUND`). `user_id` permanece opcional, sem política inferida.
