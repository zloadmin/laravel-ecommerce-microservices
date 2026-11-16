import "server-only";
import createClient from "openapi-fetch";
import { cookies } from "next/headers";
import { API_BASE } from "@/lib/env";
import { ACCESS_COOKIE, CART_COOKIE, USER_COOKIE, parseUser, type SessionUser } from "@/lib/auth/cookies";
import type { paths } from "./schema";

/*
 * openapi-fetch passes a Request object to fetch. On the server, a 401 answer to a Request with a
 * streamed body makes fetch throw "expected non-null body source" (it prepares to resend the body
 * with credentials). Sending url + init with a buffered body avoids that.
 */
async function bufferedFetch(request: Request): Promise<Response> {
  return fetch(request.url, {
    method: request.method,
    headers: request.headers,
    body: request.body ? await request.arrayBuffer() : undefined,
    cache: "no-store",
    signal: request.signal,
  });
}

/** An API client for Server Components and Server Actions that acts as the current visitor. */
export async function serverApi() {
  const jar = await cookies();
  const headers: Record<string, string> = { Accept: "application/json" };
  const token = jar.get(ACCESS_COOKIE)?.value;
  const cartId = jar.get(CART_COOKIE)?.value;
  if (token) headers.Authorization = `Bearer ${token}`;
  else if (cartId) headers["X-Cart-Id"] = cartId;

  return createClient<paths>({ baseUrl: API_BASE, headers, fetch: bufferedFetch });
}

/** An API client that sends no credentials. */
export function publicApi() {
  return createClient<paths>({ baseUrl: API_BASE, headers: { Accept: "application/json" }, fetch: bufferedFetch });
}

/** The signed-in user as stored at login, or null for guests. Middleware keeps it in sync with the tokens. */
export async function currentUser(): Promise<SessionUser | null> {
  const jar = await cookies();
  if (!jar.get(ACCESS_COOKIE)) return null;
  return parseUser(jar.get(USER_COOKIE)?.value);
}
