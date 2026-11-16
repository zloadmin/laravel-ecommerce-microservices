"use client";

import Link from "next/link";
import { useCart } from "@/lib/cart";

export function CartButton() {
  const { data: cart } = useCart();
  const count = cart?.item_count ?? 0;

  return (
    <Link
      href="/cart"
      className="relative flex size-9 items-center justify-center rounded-lg text-fg hover:bg-muted"
      aria-label={count ? `Cart, ${count} items` : "Cart"}
    >
      <svg viewBox="0 0 24 24" className="size-5" fill="none" stroke="currentColor" strokeWidth="1.8" aria-hidden="true">
        <path d="M3 4h2l2.4 11.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20 8H6.2" strokeLinecap="round" strokeLinejoin="round" />
        <circle cx="9.5" cy="19.5" r="1.3" />
        <circle cx="17" cy="19.5" r="1.3" />
      </svg>
      {count > 0 && (
        <span className="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-accent px-1 text-[10px] font-semibold text-white">
          {count > 99 ? "99+" : count}
        </span>
      )}
    </Link>
  );
}
