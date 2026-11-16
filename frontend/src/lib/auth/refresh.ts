import "server-only";
import { decodeJwt } from "jose";
import { API_BASE } from "@/lib/env";
import type { Role, Session } from "@/lib/api/types";

export type AccessClaims = { sub: string; role: Role; exp: number };

/** Reads the claims of an access token without verifying it; the gateway verifies every request. */
export function readClaims(token: string | undefined): AccessClaims | null {
  if (!token) return null;
  try {
    const claims = decodeJwt(token);
    if (typeof claims.sub !== "string" || typeof claims.exp !== "number") return null;
    return { sub: claims.sub, role: claims.role === "admin" ? "admin" : "customer", exp: claims.exp };
  } catch {
    return null;
  }
}

/** True when the token is missing, unreadable, or expires within the next 30 seconds. */
export function needsRefresh(token: string | undefined): boolean {
  const claims = readClaims(token);
  return !claims || claims.exp * 1000 - Date.now() < 30_000;
}

export type RefreshResult =
  | { status: "refreshed"; session: Session }
  | { status: "rejected" } // the refresh token is invalid, expired or already used: sign out
  | { status: "unavailable" }; // auth could not be reached: keep the cookies and try again later

/*
 * Refresh tokens are single-use, and presenting a used one revokes every session of the user.
 * A page load fires several requests at once, so concurrent refreshes with the same token share
 * one call, and its result is remembered briefly for requests that arrive just after it.
 * This is per process: run one Next.js instance, or move this cache to Redis when scaling out.
 */
const inFlight = new Map<string, Promise<RefreshResult>>();
const recent = new Map<string, { result: RefreshResult; at: number }>();
const REMEMBER_MS = 60_000;

export function refreshSession(refreshToken: string): Promise<RefreshResult> {
  const now = Date.now();
  for (const [token, entry] of recent) {
    if (now - entry.at > REMEMBER_MS) recent.delete(token);
  }

  const remembered = recent.get(refreshToken);
  if (remembered) return Promise.resolve(remembered.result);

  let pending = inFlight.get(refreshToken);
  if (!pending) {
    pending = callRefresh(refreshToken)
      .then((result) => {
        if (result.status !== "unavailable") recent.set(refreshToken, { result, at: Date.now() });
        return result;
      })
      .finally(() => inFlight.delete(refreshToken));
    inFlight.set(refreshToken, pending);
  }
  return pending;
}

async function callRefresh(refreshToken: string): Promise<RefreshResult> {
  try {
    const response = await fetch(`${API_BASE}/auth/refresh`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ refresh_token: refreshToken }),
      cache: "no-store",
    });
    if (response.ok) {
      const body = (await response.json()) as { data: Session };
      return { status: "refreshed", session: body.data };
    }
    return response.status === 401 || response.status === 422 ? { status: "rejected" } : { status: "unavailable" };
  } catch {
    return { status: "unavailable" };
  }
}
