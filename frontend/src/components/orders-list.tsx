"use client";

import Link from "next/link";
import { useState } from "react";
import { describeError } from "@/lib/api/errors";
import type { OrderStatus } from "@/lib/api/types";
import { STATUS_LABELS, useOrders } from "@/lib/orders";
import { cn, formatDate, formatMoney, shortId } from "@/lib/format";
import { OrderStatusBadge } from "./order-status-badge";
import { Button, ButtonLink } from "./ui/button";
import { Alert } from "./ui/field";
import { EmptyState, Skeleton } from "./ui/misc";

const FILTERS: Array<OrderStatus | undefined> = [undefined, "pending", "confirmed", "cancelled"];

/** Orders of the signed-in customer, or everyone's for admins (optionally of one user). */
export function OrdersList({ userId, admin = false }: { userId?: string; admin?: boolean }) {
  const [status, setStatus] = useState<OrderStatus | undefined>();
  const query = useOrders({ status, userId });
  const orders = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div>
      <div className="mb-5 flex flex-wrap gap-2" role="tablist" aria-label="Filter by status">
        {FILTERS.map((value) => (
          <button
            key={value ?? "all"}
            role="tab"
            aria-selected={status === value}
            onClick={() => setStatus(value)}
            className={cn(
              "rounded-full px-3.5 py-1.5 text-sm font-medium transition",
              status === value ? "bg-fg text-bg" : "bg-muted text-fg-muted hover:text-fg",
            )}
          >
            {value ? STATUS_LABELS[value] : "All"}
          </button>
        ))}
      </div>

      {query.isPending ? (
        <div className="space-y-2">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-16" />
          ))}
        </div>
      ) : query.error ? (
        <Alert>{describeError(query.error)}</Alert>
      ) : orders.length === 0 ? (
        <EmptyState
          title="No orders yet"
          description={status ? "No orders with this status." : "When you place an order, it will show up here."}
          action={admin ? undefined : <ButtonLink href="/products">Start shopping</ButtonLink>}
        />
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-line bg-surface shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line text-xs text-fg-subtle uppercase">
                <tr>
                  <th className="px-4 py-3 font-medium">Order</th>
                  <th className="px-4 py-3 font-medium">Placed</th>
                  {admin && <th className="px-4 py-3 font-medium">Customer</th>}
                  <th className="px-4 py-3 font-medium">Items</th>
                  <th className="px-4 py-3 font-medium">Total</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {orders.map((order) => (
                  <tr key={order.id} className="hover:bg-muted/50">
                    <td className="px-4 py-3">
                      <Link href={`/orders/${order.id}`} className="font-mono font-medium text-accent hover:underline">
                        #{shortId(order.id)}
                      </Link>
                    </td>
                    <td className="px-4 py-3 text-fg-muted">{formatDate(order.created_at)}</td>
                    {admin && <td className="px-4 py-3 font-mono text-xs text-fg-muted">{shortId(order.user_id)}</td>}
                    <td className="px-4 py-3 text-fg-muted">{order.items.reduce((sum, item) => sum + item.quantity, 0)}</td>
                    <td className="px-4 py-3 font-medium text-fg tabular-nums">{formatMoney(order.total)}</td>
                    <td className="px-4 py-3">
                      <OrderStatusBadge status={order.status} />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {query.hasNextPage && (
            <div className="mt-6 text-center">
              <Button variant="secondary" loading={query.isFetchingNextPage} onClick={() => query.fetchNextPage()}>
                Load more
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
}
