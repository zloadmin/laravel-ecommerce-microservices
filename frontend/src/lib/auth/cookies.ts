import type { Session, User } from "@/lib/api/types";

export const ACCESS_COOKIE = "access_token";
export const REFRESH_COOKIE = "refresh_token";
export const USER_COOKIE = "session_user";
export const CART_COOKIE = "cart_id";

const THIRTY_DAYS = 30 * 24 * 60 * 60;

/** Matches the auth service's REFRESH_TOKEN_TTL and the cart service's 30-day expiry. */
export const LONG_LIVED = THIRTY_DAYS;

export const cookieOptions = (maxAge: number) => ({
  httpOnly: true,
  sameSite: "lax" as const,
  secure: process.env.NODE_ENV === "production",
  path: "/",
  maxAge,
});

/** The parts of a user kept in a cookie for rendering the header without an API call. */
export type SessionUser = Pick<User, "id" | "name" | "email" | "role">;

/** Anything with `set`/`delete` for cookies: `cookies()` in actions, or `NextResponse.cookies`. */
type CookieWriter = {
  set(name: string, value: string, options: ReturnType<typeof cookieOptions>): unknown;
  delete(name: string): unknown;
};

export function writeSession(store: CookieWriter, session: Session): void {
  store.set(ACCESS_COOKIE, session.access_token, cookieOptions(session.expires_in));
  store.set(REFRESH_COOKIE, session.refresh_token, cookieOptions(LONG_LIVED));
  writeUser(store, session.user);
}

export function writeUser(store: CookieWriter, user: User | SessionUser): void {
  const { id, name, email, role } = user;
  store.set(USER_COOKIE, JSON.stringify({ id, name, email, role }), cookieOptions(LONG_LIVED));
}

export function clearSession(store: CookieWriter): void {
  store.delete(ACCESS_COOKIE);
  store.delete(REFRESH_COOKIE);
  store.delete(USER_COOKIE);
}

export function parseUser(value: string | undefined): SessionUser | null {
  if (!value) return null;
  try {
    const user = JSON.parse(value) as SessionUser;
    return typeof user?.id === "string" ? user : null;
  } catch {
    return null;
  }
}
