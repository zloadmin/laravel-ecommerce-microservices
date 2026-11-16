import Link from "next/link";
import { logout } from "@/lib/auth/actions";
import type { SessionUser } from "@/lib/auth/cookies";
import { ButtonLink } from "./ui/button";
import { CartButton } from "./cart-button";

export function SiteHeader({ user }: { user: SessionUser | null }) {
  return (
    <header className="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <Link href="/" className="text-lg font-bold tracking-tight text-fg">
          Storefront
        </Link>
        <nav className="hidden text-sm text-fg-muted sm:block">
          <Link href="/products" className="hover:text-fg">
            Products
          </Link>
        </nav>

        <form action="/products" className="ml-auto hidden max-w-xs flex-1 md:block">
          <input
            name="q"
            type="search"
            placeholder="Search products…"
            aria-label="Search products"
            className="h-9 w-full rounded-lg border border-line bg-muted px-3 text-sm text-fg placeholder:text-fg-subtle focus:border-accent focus:bg-surface focus:outline-none"
          />
        </form>

        <div className="ml-auto flex items-center gap-1 md:ml-0">
          <CartButton />
          {user ? (
            <UserMenu user={user} />
          ) : (
            <>
              <ButtonLink href="/login" variant="ghost" size="sm">
                Sign in
              </ButtonLink>
              <ButtonLink href="/register" size="sm" className="hidden sm:inline-flex">
                Create account
              </ButtonLink>
            </>
          )}
        </div>
      </div>
    </header>
  );
}

function UserMenu({ user }: { user: SessionUser }) {
  const item = "block w-full rounded-md px-3 py-2 text-left text-sm text-fg hover:bg-muted";
  return (
    <details className="group relative">
      <summary className="flex h-9 cursor-pointer list-none items-center gap-2 rounded-lg px-2 text-sm font-medium text-fg hover:bg-muted [&::-webkit-details-marker]:hidden">
        <span className="flex size-7 items-center justify-center rounded-full bg-accent text-xs font-semibold text-white">
          {user.name.charAt(0).toUpperCase()}
        </span>
        <span className="hidden max-w-32 truncate sm:inline">{user.name}</span>
      </summary>
      <div className="absolute right-0 mt-2 w-52 rounded-xl border border-line bg-surface p-1.5 shadow-lg">
        <p className="truncate px-3 py-2 text-xs text-fg-subtle">{user.email}</p>
        <Link href="/orders" className={item}>
          My orders
        </Link>
        <Link href="/account" className={item}>
          Account
        </Link>
        {user.role === "admin" && (
          <Link href="/admin" className={item}>
            Admin
          </Link>
        )}
        <form action={logout} className="mt-1 border-t border-line pt-1">
          <button type="submit" className={item}>
            Sign out
          </button>
        </form>
      </div>
    </details>
  );
}
