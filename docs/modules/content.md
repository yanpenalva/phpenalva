# Content

## 1. Overview

Content provides post listing, detail lookup, creation, update, and deletion. `PostDescription` accepts descriptions from 6 through 255 characters. `Post::changeDescription()` accepts a validated value object.

## 2. Models/tables

The `posts` table contains `id`, nullable `user_id`, `description`, `created_at`, and `updated_at`. `Post` is the aggregate with identifier, description, and optional author identifier. The MySQL, PostgreSQL, and SQL Server migrations specify `ON DELETE SET NULL` for the nullable user reference; the SQLite migration declares the foreign key without that action.

## 3. Controllers

`PostController` handles `GET /posts`, `GET /post/{id}`, `POST /api/posts`, `PUT /api/post/{id}`, and `DELETE /api/post/{id}`. Writes delegate to `CreatePost`, `UpdatePost`, and `DeletePost`. Invalid descriptions or identifiers return `422`; missing posts return `404`; successful create returns `201`, update returns `200`, and delete returns `204` without a response body.

## 4. Services

Application use cases validate values through domain types and persist through `PostRepository`. `CreatePost` creates a post, `UpdatePost` changes its description, and `DeletePost` removes it. No publication or moderation rule is implemented.

## 5. Repositories

`PostRepository` defines listing, lookup, insertion, update, and deletion operations. `PdoPostRepository` maps SQL rows explicitly to domain entities and binds query parameters.

## 6. Routes/middlewares

Routes are declared in `routes/routes.php`. `POST /api/posts`, `GET /posts`, `PUT /api/post/{id}`, and `DELETE /api/post/{id}` are marked `auth`; `GET /post/{id}` is public. Mutations require valid parsed input and a session CSRF token through `RequestGuard`.

## 7. State machine

Posts have no persisted publication state. Available operations create, update, and delete records.

## 8. Frontend

The post views are `app/Views/Posts/index.phtml` and `app/Views/Posts/show.phtml`. Controllers provide the collection or resolved post to those views.

## 9. Permissions/policies

Authentication is required on the routes marked `auth`. The application does not enforce post ownership: the nullable `user_id` is not used as an ownership authorization rule. The database delete action is driver-specific as described in section 2.
