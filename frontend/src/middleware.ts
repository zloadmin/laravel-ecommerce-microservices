import { NextResponse, type NextRequest } from "next/server";
import { ACCESS_COOKIE, REFRESH_COOKIE, USER_COOKIE, clearSession, writeSession } from "@/lib/auth/cookies";
import { needsRefresh, readClaims, refreshSession } from "@/lib/auth/refresh";
import type { Session } from "@/lib/api/types";

const SIGNED_IN_ONLY = ["/account", "/orders", "/checkout", "/admin"];
const GUEST_ONLY = ["/login", "/register"];

const matches = (path: string, prefixes: string[]) =>
  prefixes.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));

/**
 * Keeps the session alive and guards pages.
 *
 * Every page, server action and /api call passes through here, so this is the only place that
 * refreshes tokens: an expiring access token is renewed before rendering, and the new cookies are
 * passed both to the browser and to the code handling this request.
 */
export async function middleware(request: NextRequest) {
  const path = request.nextUrl.pathname;
  let access = request.cookies.get(ACCESS_COOKIE)?.value;
  const refresh = request.cookies.get(REFRESH_COOKIE)?.value;

  let renewed: Session | null = null;
  let signedOut = false;

  if (refresh && needsRefresh(access)) {
    const result = await refreshSession(refresh);
    if (result.status === "refreshed") {
      renewed = result.session;
      access = renewed.access_token;
    } else if (result.status === "rejected") {
      signedOut = true;
      access = undefined;
    }
  } else if (!refresh && access) {
    // An access token without its refresh token is a leftover; drop it.
    signedOut = true;
    access = undefined;
  }

  const claims = readClaims(access);
  let response: NextResponse;

  if (!path.startsWith("/api/") && matches(path, SIGNED_IN_ONLY) && !claims) {
    const login = new URL("/login", request.url);
    login.searchParams.set("next", path + request.nextUrl.search);
    response = NextResponse.redirect(login);
  } else if (matches(path, ["/admin"]) && claims?.role !== "admin") {
    response = NextResponse.redirect(new URL("/", request.url));
  } else if (matches(path, GUEST_ONLY) && claims) {
    response = NextResponse.redirect(new URL("/", request.url));
  } else {
    if (renewed) {
      request.cookies.set(ACCESS_COOKIE, renewed.access_token);
      request.cookies.set(REFRESH_COOKIE, renewed.refresh_token);
    } else if (signedOut) {
      [ACCESS_COOKIE, REFRESH_COOKIE, USER_COOKIE].forEach((name) => request.cookies.delete(name));
    }
    const headers = new Headers(request.headers);
    headers.set("cookie", request.cookies.toString());
    response = NextResponse.next({ request: { headers } });
  }

  if (renewed) writeSession(response.cookies, renewed);
  else if (signedOut) clearSession(response.cookies);

  return response;
}

export const config = {
  // Node runtime, so the refresh de-duplication cache is shared by every request in the process.
  runtime: "nodejs",
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|ico|webp)$).*)"],
};
