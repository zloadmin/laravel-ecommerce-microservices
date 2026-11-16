import { NextResponse, type NextRequest } from "next/server";
import { API_BASE } from "@/lib/env";
import { ACCESS_COOKIE, CART_COOKIE, LONG_LIVED, cookieOptions } from "@/lib/auth/cookies";

/*
 * The browser's door to the gateway. It attaches the visitor's credentials from httpOnly
 * cookies, so tokens are never exposed to client-side JavaScript, and the browser only talks to
 * this origin, so the gateway needs no CORS setup. Token refresh has already happened in middleware.
 */

const FORWARDED_REQUEST_HEADERS = ["content-type", "idempotency-key", "x-correlation-id"];
const FORWARDED_RESPONSE_HEADERS = [
  "content-type",
  "x-correlation-id",
  "retry-after",
  "x-ratelimit-limit",
  "x-ratelimit-remaining",
];

// Auth endpoints return tokens, so they are only reachable through server actions.
const BLOCKED = new Set(["auth", "health"]);

const errorResponse = (status: number, code: string, message: string) =>
  NextResponse.json({ error: { code, message, details: {} } }, { status });

async function proxy(request: NextRequest, { params }: { params: Promise<{ path: string[] }> }) {
  const { path } = await params;
  if (BLOCKED.has(path[0])) {
    return errorResponse(404, "not_found", "Not found.");
  }

  const headers = new Headers({ Accept: "application/json" });
  for (const name of FORWARDED_REQUEST_HEADERS) {
    const value = request.headers.get(name);
    if (value) headers.set(name, value);
  }
  const forwardedFor = request.headers.get("x-forwarded-for");
  if (forwardedFor) headers.set("x-forwarded-for", forwardedFor);

  const token = request.cookies.get(ACCESS_COOKIE)?.value;
  let newCartId: string | null = null;
  if (token) {
    headers.set("Authorization", `Bearer ${token}`);
  } else if (path[0] === "cart") {
    let cartId = request.cookies.get(CART_COOKIE)?.value;
    if (!cartId) cartId = newCartId = crypto.randomUUID();
    headers.set("X-Cart-Id", cartId);
  }

  const url = `${API_BASE}/${path.map(encodeURIComponent).join("/")}${request.nextUrl.search}`;
  const hasBody = !["GET", "HEAD"].includes(request.method);

  let upstream: Response;
  try {
    upstream = await fetch(url, {
      method: request.method,
      headers,
      body: hasBody ? await request.arrayBuffer() : undefined,
      cache: "no-store",
      redirect: "manual",
    });
  } catch {
    return errorResponse(503, "service_unavailable", "The API gateway could not be reached.");
  }

  const responseHeaders = new Headers();
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) responseHeaders.set(name, value);
  }

  const body = upstream.status === 204 ? null : upstream.body;
  const response = new NextResponse(body, { status: upstream.status, headers: responseHeaders });
  if (newCartId) {
    response.cookies.set(CART_COOKIE, newCartId, cookieOptions(LONG_LIVED));
  }
  return response;
}

export { proxy as GET, proxy as POST, proxy as PUT, proxy as PATCH, proxy as DELETE };
