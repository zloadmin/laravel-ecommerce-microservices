# Frontend

Storefront and admin UI for the e-commerce API, built with Next.js (App Router), TypeScript and Tailwind CSS.

## Running

With the backend stack up (`make up` from the repository root):

```sh
make up                 # also starts the `frontend` container on http://localhost:3000
# or, on the host:
make frontend-install
make frontend-dev       # http://localhost:3000, talks to the gateway on localhost:8080
```

Sign in with the seeded accounts (`make seed`): `customer@example.com` or `admin@example.com`, password `password`.

| Command | What it does |
|---|---|
| `npm run dev` | Development server |
| `npm run build && npm start` | Production build and server |
| `npm run lint`, `npm run typecheck` | ESLint and TypeScript checks (`make frontend-check`) |
| `npm run api:types` | Regenerate `src/lib/api/schema.d.ts` from `docs/openapi.yaml` (`make frontend-types`) |

Configuration: `GATEWAY_URL` (see `.env.example`), the gateway's base URL as seen from the Next.js server.

## How it talks to the API

The browser never calls the gateway. Next.js sits in front of it:

- **Server Components** (catalog, product pages, account) call the gateway directly with `serverApi()`.
- **Client components** (cart, checkout, orders, admin) call `/api/*` on this app. The route handler in
  `src/app/api/[...path]/route.ts` forwards the request to the gateway, so no CORS setup is needed.
- **Sign-in, registration, sign-out and profile changes** are Server Actions (`src/lib/auth/actions.ts`).
  `/api/auth/*` is blocked, so tokens never reach client-side JavaScript.

Tokens live in httpOnly cookies (`access_token`, `refresh_token`, plus `session_user` for rendering the header).
`src/middleware.ts` runs before every page, action and `/api` call: when the access token is missing or about to
expire it rotates the refresh token, writes the new cookies to the response, and hands them to the code handling
the same request. Refresh tokens are single-use and reusing one revokes every session, so concurrent refreshes of
the same token share one call (`src/lib/auth/refresh.ts`). That cache is per process: run a single Next.js
instance, or move it to Redis before scaling out.

Guests get a `cart_id` cookie (a UUID sent as `X-Cart-Id`) the first time they touch the cart. Signing in or
registering merges that cart into the customer's cart and deletes the cookie.

The middleware also guards routes: `/account`, `/orders`, `/checkout` and `/admin` need a session, and `/admin`
needs the `admin` role. The gateway enforces roles independently.

## Layout

```
src/
  app/                 routes (storefront, account, orders, admin, /api proxy)
  components/          shared UI; components/ui holds the primitives (Button, Field, Card…)
  lib/api/             generated schema types, server and browser clients, error handling
  lib/auth/            cookies, token refresh, server actions
  lib/cart.ts, orders.ts, admin.ts   TanStack Query hooks per area
  lib/catalog.ts       server-side catalog queries
  middleware.ts
```

Prices come from the API as integer minor units and are formatted with `formatMoney()` (`src/lib/format.ts`).
After checkout an order is `pending` until catalog reserves stock; the order page polls every 2 seconds until it is
`confirmed` or `cancelled`.
