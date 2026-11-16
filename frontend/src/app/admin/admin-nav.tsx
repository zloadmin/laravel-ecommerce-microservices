"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { cn } from "@/lib/format";

const LINKS = [
  { href: "/admin/products", label: "Products" },
  { href: "/admin/categories", label: "Categories" },
  { href: "/admin/orders", label: "Orders" },
];

export function AdminNav() {
  const path = usePathname();
  return (
    <nav className="flex gap-1 md:flex-col">
      {LINKS.map((link) => (
        <Link
          key={link.href}
          href={link.href}
          className={cn(
            "rounded-lg px-3 py-2 text-sm font-medium",
            path.startsWith(link.href) ? "bg-muted text-fg" : "text-fg-muted hover:bg-muted hover:text-fg",
          )}
        >
          {link.label}
        </Link>
      ))}
    </nav>
  );
}
