"use client";

import Link from "next/link";
import { ApiError, describeError } from "@/lib/api/errors";
import type { Order } from "@/lib/api/types";
import { CANCELLATION_REASONS, useCancelOrder, useOrder } from "@/lib/orders";
import { formatDate, formatMoney, shortId } from "@/lib/format";
import { OrderStatusBadge } from "@/components/order-status-badge";
import { Spinner } from "@/components/ui/spinner";
import { useToast } from "@/components/toast";
import { Button, ButtonLink } from "@/components/ui/button";
import { Alert } from "@/components/ui/field";
import { Card, EmptyState, Skeleton } from "@/components/ui/misc";

export function OrderView({ id, justPlaced }: { id: string; justPlaced: boolean }) {
  const { data: order, isPending, error } = useOrder(id);
  const cancel = useCancelOrder();
  const toast = useToast();

  if (isPending) return <Skeleton className="h-80" />;

  if (error) {
    if (error instanceof ApiError && (error.status === 404 || error.code === "validation_failed")) {
      return (
        <EmptyState
          title="Order not found"
          description="This order doesn't exist or belongs to another account."
          action={<ButtonLink href="/orders">Back to my orders</ButtonLink>}
        />
      );
    }
    return <Alert>{describeError(error)}</Alert>;
  }

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <Link href="/orders" className="text-sm text-fg-subtle hover:text-fg">
            ← My orders
          </Link>
          <h1 className="mt-1 text-2xl font-semibold tracking-tight text-fg">
            Order <span className="font-mono">#{shortId(order.id)}</span>
          </h1>
          <p className="text-sm text-fg-subtle">Placed {formatDate(order.created_at)}</p>
        </div>
        <OrderStatusBadge status={order.status} />
      </div>

      <StatusPanel order={order} justPlaced={justPlaced} />

      <Card>
        <table className="w-full text-left text-sm">
          <thead className="border-b border-line text-xs text-fg-subtle uppercase">
            <tr>
              <th className="px-5 py-3 font-medium">Product</th>
              <th className="px-5 py-3 text-right font-medium">Price</th>
              <th className="px-5 py-3 text-right font-medium">Qty</th>
              <th className="px-5 py-3 text-right font-medium">Total</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-line">
            {order.items.map((item) => (
              <tr key={item.product_id}>
                <td className="px-5 py-3">
                  <p className="font-medium text-fg">{item.name}</p>
                  <p className="text-xs text-fg-subtle">SKU {item.sku}</p>
                </td>
                <td className="px-5 py-3 text-right text-fg-muted tabular-nums">{formatMoney(item.unit_price)}</td>
                <td className="px-5 py-3 text-right text-fg-muted tabular-nums">{item.quantity}</td>
                <td className="px-5 py-3 text-right font-medium text-fg tabular-nums">{formatMoney(item.line_total)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot className="border-t border-line">
            <tr>
              <td colSpan={3} className="px-5 py-4 text-right font-semibold text-fg">
                Total
              </td>
              <td className="px-5 py-4 text-right text-lg font-semibold text-fg tabular-nums">{formatMoney(order.total)}</td>
            </tr>
          </tfoot>
        </table>
      </Card>

      {order.status !== "cancelled" && (
        <div className="flex justify-end">
          <Button
            variant="secondary"
            loading={cancel.isPending}
            onClick={() => {
              if (!confirm("Cancel this order? Reserved items will be released.")) return;
              cancel.mutate(order.id, {
                onSuccess: () => toast.success("Your order was cancelled."),
                onError: (e) => toast.error(describeError(e)),
              });
            }}
          >
            Cancel order
          </Button>
        </div>
      )}
    </div>
  );
}

function StatusPanel({ order, justPlaced }: { order: Order; justPlaced: boolean }) {
  if (order.status === "pending") {
    return (
      <Alert tone="info">
        <span className="flex items-center gap-2">
          <Spinner className="size-4" />
          {justPlaced ? "Thanks for your order! " : ""}We&apos;re reserving your items. This page updates automatically.
        </span>
      </Alert>
    );
  }
  if (order.status === "confirmed") {
    return (
      <Alert tone="success">
        {justPlaced ? "Thanks for your order! " : ""}Your order is confirmed and the items are reserved for you.
      </Alert>
    );
  }
  return (
    <Alert>
      This order was cancelled. {CANCELLATION_REASONS[order.cancellation_reason ?? ""] ?? ""}
      {order.cancellation_reason === "insufficient_stock" && (
        <>
          {" "}
          Your cart was emptied at checkout, so{" "}
          <Link href="/products" className="font-medium underline">
            add the available items again
          </Link>
          .
        </>
      )}
    </Alert>
  );
}
