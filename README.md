# Laravel E-commerce Microservices

An e-commerce REST API built as a set of independent Laravel services.

## Architecture

| Component | Path | Role |
|---|---|---|
| Gateway | `services/gateway` | Single public entry point: routing, rate limiting, correlation IDs |
| Auth | `services/auth` (planned) | Users, registration, login, JWT (RS256) |
| Catalog | `services/catalog` (planned) | Products and categories |
| Orders | `services/orders` (planned) | Checkout and order lifecycle |
| Shared package | `packages/shared` | Cross-cutting infrastructure code only (error format, middleware) |

- **Database per service** (PostgreSQL): `auth`, `catalog`, `orders`. No cross-service joins.
- **Sync calls** go over REST; **domain events** go over RabbitMQ.
- Only the gateway is published to the host. Public paths map to services in
  `services/gateway/config/gateway.php` (`/api/v1/products` → catalog, and so on).
- Every error uses one shape: `{"error": {"code", "message", "details"}}`.

## Getting started

Requires Docker with Compose.

```sh
make setup   # env files, image build, composer install, app keys
make up      # start the stack
curl localhost:8080/api/v1/health
```

| What | Where |
|---|---|
| API (gateway) | http://localhost:8080 |
| RabbitMQ UI | http://localhost:15672 (ecommerce / secret) |
| PostgreSQL | localhost:54320 (ecommerce / secret) |
| Redis | localhost:63790 |

Host ports are set in `.env` (see `.env.example`).

## Common commands

```sh
make test s=gateway   # run one service's tests
make test-all
make sh s=gateway     # shell into a service container
make logs s=gateway
make pint s=gateway   # format code
make down
```
