"use client";

import { useRouter } from "next/navigation";
import { useState } from "react";
import { ApiError, describeError } from "@/lib/api/errors";
import { checkoutBlockers, useCart } from "@/lib/cart";
import { useCheckout } from "@/lib/orders";
import { formatMoney } from "@/lib/format";
import { Button, ButtonLink } from "@/components/ui/button";
import { Alert } from "@/components/ui/field";
import { Card, EmptyState, PageHeader, Skeleton } from "@/components/ui/misc";

function checkoutError(error: unknown): string {
  if (error instanceof ApiError && error.code === "cart_empty") return "Your cart is empty.";
  if (error instanceof ApiError && error.code === "validation_failed") {
    return `We couldn't place your order: ${error.message} Please review your cart.`;
  }
  return describeError(error);
}

export function CheckoutView() {
  const router = useRouter();
  const { data: cart, isPending, error: cartError } = useCart();
  const checkout = useCheckout();
  // One key per visit to this page, so a retried or double-clicked submit creates a single order.
  const [idempotencyKey] = useState(() => crypto.randomUUID());

  if (isPending) return <Skeleton className="h-64" />;
  if (cartError) return <Alert>{describeError(cartError)}</Alert>;

  if (cart.items.length === 0 && !checkout.isSuccess) {
    return (
      <EmptyState
        title="Nothing to check out"
        description="Your cart is empty."
        action={<ButtonLink href="/products">Browse products</ButtonLink>}
      />
    );
  }

  const blockers = checkoutBlockers(cart);
  const blocked = blockers.unavailable > 0 || blockers.outOfStock > 0;

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title="Checkout" description="Review your order and place it." />

      <Card className="divide-y divide-line">
        <ul className="divide-y divide-line">
          {cart.items.map((line) => (
            <li key={line.product_id} className="flex items-center justify-between gap-4 px-5 py-4 text-sm">
              <div>
                <p className="font-medium text-fg">{line.name ?? "Unavailable product"}</p>
                <p className="text-fg-subtle">
                  {line.quantity} × {formatMoney(line.unit_price)}
                </p>
              </div>
              <span className="font-medium text-fg tabular-nums">{formatMoney(line.line_total)}</span>
            </li>
          ))}
        </ul>
        <div className="flex justify-between px-5 py-4">
          <span className="font-semibold text-fg">Total</span>
          <span className="text-lg font-semibold text-fg">{formatMoney(cart.total)}</span>
        </div>
      </Card>

      <div className="mt-6 space-y-4">
        {blocked && <Alert>Some items in your cart can&apos;t be ordered. Go back to your cart to fix them.</Alert>}
        {checkout.error && <Alert>{checkoutError(checkout.error)}</Alert>}
        <p className="text-sm text-fg-muted">
          After you place the order we reserve stock for it. This usually takes a few seconds.
        </p>
        <div className="flex justify-between gap-3">
          <ButtonLink href="/cart" variant="secondary">
            Back to cart
          </ButtonLink>
          <Button
            size="lg"
            disabled={blocked}
            loading={checkout.isPending || checkout.isSuccess}
            onClick={() =>
              checkout.mutate(idempotencyKey, { onSuccess: (order) => router.push(`/orders/${order.id}?placed=1`) })
            }
          >
            Place order
          </Button>
        </div>
      </div>
    </div>
  );
}
