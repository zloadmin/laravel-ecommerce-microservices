import type { Metadata } from "next";
import type { ReactNode } from "react";
import { AdminNav } from "./admin-nav";

export const metadata: Metadata = { title: { default: "Admin", template: "%s · Admin · Storefront" } };

/** Middleware only lets admins in; the gateway enforces the role on every write as well. */
export default function AdminLayout({ children }: { children: ReactNode }) {
  return (
    <div className="grid gap-8 md:grid-cols-[180px_1fr]">
      <aside>
        <p className="mb-3 px-3 text-xs font-semibold tracking-wide text-fg-subtle uppercase">Admin</p>
        <AdminNav />
      </aside>
      <div className="min-w-0">{children}</div>
    </div>
  );
}
