# Commerce Platform

A B2B order and inventory platform: product catalog, cart, checkout with real stock validation, an admin panel for managing orders and the catalog, JWT authentication with role-based authorization, async email notifications, Redis-backed catalog caching, and OpenAPI docs — all running in Docker with a single command and a GitHub Actions CI pipeline.

## Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3 + Symfony 7 |
| ORM | Doctrine ORM + Doctrine Migrations |
| Auth | LexikJWTAuthenticationBundle (access + refresh tokens), Symfony Security Voters |
| Async events | Symfony Messenger (Doctrine transport) |
| Caching | Redis (via Predis), tag-aware cache pool |
| API docs | NelmioApiDocBundle (Swagger/OpenAPI) |
| Frontend | Vue 3 + Composition API + Vite + Pinia |
| Database | PostgreSQL |
| Testing | PHPUnit + Symfony `WebTestCase` |
| Static analysis | PHPStan (level 8) |
| Containers | Docker Compose |
| CI/CD | GitHub Actions |

## Getting started

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction

cd frontend && npm install && npm run dev
```

- Backend API: `http://localhost:8080/api`
- API docs (Swagger UI): `http://localhost:8080/api/doc`
- Health check: `http://localhost:8080/api/health`
- Frontend: `http://localhost:5173`

Optional, only needed to see the async email side effect from confirming an order (see "Async events" below):

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

### Test users (from fixtures)

| Email | Password | Role |
|---|---|---|
| admin@example.com | password123 | ROLE_ADMIN |
| alice@example.com | password123 | ROLE_USER |
| bob@example.com | password123 | ROLE_USER |
| carla@example.com | password123 | ROLE_USER |

## Architecture notes

- **Auth**: short-lived JWT access tokens + a single-use, 30-day refresh token (`POST /api/token/refresh`). Login is rate-limited (5 attempts/minute).
- **Authorization**: Symfony Security Voters decide who can confirm/cancel an order (owner or admin only).
- **Business rules**: stock is validated at confirmation time, not at order creation; `POST /api/orders` accepts an `Idempotency-Key` header to make retried requests safe.
- **Soft deletes**: products and categories use a `deletedAt` column plus a Doctrine SQL filter (enabled by default) rather than physical deletes, so historical orders referencing a deleted product stay fully readable. Admin-only endpoints list and restore deleted records.
- **Async events**: confirming an order dispatches an `OrderConfirmed` message instead of running secondary effects (a simulated confirmation email) inline, so the HTTP response stays fast regardless of how slow that side effect is. Requires the `messenger:consume async` worker to actually process it — see "Getting started" above.
- **Caching**: `GET /api/products` is cached per unique `(page, limit, category, sort)` combination in Redis (60s TTL), with an `X-Cache: HIT/MISS` response header. Creating, deleting or restoring a product invalidates the whole cached catalog via a single tag (`products`) instead of tracking individual keys.
- **Audit log**: every order status change (confirm/cancel) and the async confirmation email are recorded with who/what/when, visible in the admin panel's order history.

## Running the tests

```bash
docker compose exec -e APP_ENV=test php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec -e APP_ENV=test php php bin/console cache:pool:clear cache.rate_limiter
docker compose exec -e APP_ENV=test php php vendor/bin/phpunit
docker compose exec php php vendor/bin/phpstan analyse
```

CI (`.github/workflows/ci.yml`) runs the same commands against the real Docker stack (not a mocked environment) on every push and pull request to `main`, plus a separate job that type-checks and builds the frontend.

## Switching the database to SQL Server

This project targets PostgreSQL, but Doctrine DBAL supports SQL Server without any application code changes — only configuration:

1. Install the `pdo_sqlsrv` PHP extension (via PECL or the Microsoft-provided Docker base image).
2. Change `DATABASE_URL` in `.env` to a `sqlsrv://` DSN, e.g. `sqlsrv://user:password@host:1433/database`.
3. Regenerate migrations for the SQL Server platform (`doctrine:migrations:diff`), since column types and index syntax differ from PostgreSQL's.

This was not actually implemented or tested against a running SQL Server instance — documented here as the integration path, not as proven behavior.

## Deliberate scope cuts

- **API Platform**: Symfony + Serializer is used directly instead, for full manual control over request/response shapes.
- **Psalm**: omitted; PHPStan alone covers static analysis for this project's purposes.
- **GitLab CI**: the original plan assumed GitLab; this repo lives on GitHub, so GitHub Actions is used instead.
- **SQL Server**: documented above, not implemented against a live instance.
- **i18n (ES/EN) and a manual dark/light theme toggle**: the color system already respects the OS/browser's `prefers-color-scheme`, but a manual in-app toggle and translated UI text were not built — flagged as a scope decision that needs explicit direction (persistence, auto-detection, i18n library) rather than being implemented speculatively.
- **TypeScript client generation from OpenAPI**: not built; the frontend's API types are hand-written and kept in sync manually.
- **Hosting a live demo** (Railway/Render/etc.): not done; the repo + Docker + CI are the deliverable.

## Project history

Built incrementally across 10 checkpoints, each merged as its own reviewed pull request:

0. Base Docker setup (Symfony + Vue + Postgres)
1. Domain entities + fixtures, frontend shell
2. JWT auth, product/category CRUD, real catalog in the frontend
3. Order business logic, Voters, stock validation, cart/checkout
4. API docs (Swagger), pagination/filtering, admin orders panel with audit log
5. Backend test suite, login rate limiting, JWT refresh tokens, PHPStan level 8, full responsive + dark mode pass
6. Soft deletes for products/categories, admin catalog management UI
7. Async order-confirmation email via Symfony Messenger
8. Redis-backed catalog caching
9. CI/CD pipeline, real DB-connectivity health check, this README
