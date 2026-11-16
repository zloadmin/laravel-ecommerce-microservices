"use client";

import Link from "next/link";
import { describeError } from "@/lib/api/errors";
import type { CartLine } from "@/lib/api/types";
import { checkoutBlockers, useCart, useClearCart, useRemoveFromCart, useSetQuantity } from "@/lib/cart";
import { cn, formatMoney } from "@/lib/format";
import { ProductImage } from "@/components/product-image";
import { QuantityStepper } from "@/components/quantity-stepper";
import { useToast } from "@/components/toast";
import { Button, ButtonLink } from "@/components/ui/button";
import { Alert } from "@/components/ui/field";
import { Card, EmptyState, PageHeader, Skeleton } from "@/components/ui/misc";

export function CartView() {
  const { data: cart, isPending, error } = useCart();
  const clear = useClearCart();
  const toast = useToast();

  if (isPending) {
    return (
      <>
        <PageHeader title="Your cart" />
        <div className="space-y-3">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-28" />
          ))}
        </div>
      </>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader title="Your cart" />
        <Alert>{describeError(error)}</Alert>
      </>
    );
  }

  if (cart.items.length === 0) {
    return (
      <>
        <PageHeader title="Your cart" />
        <EmptyState
          title="Your cart is empty"
          description="Browse the catalog and add something you like."
          action={<ButtonLink href="/products">Start shopping</ButtonLink>}
        />
      </>
    );
  }

  const blockers = checkoutBlockers(cart);
  const blocked = blockers.unavailable > 0 || blockers.outOfStock > 0;

  return (
    <>
      <PageHeader
        title="Your cart"
        description={`${cart.item_count} ${cart.item_count === 1 ? "item" : "items"}`}
        actions={
          <Button
            variant="ghost"
            size="sm"
            loading={clear.isPending}
            onClick={() => {
              if (confirm("Remove everything from your cart?")) {
                clear.mutate(undefined, { onError: (e) => toast.error(describeError(e)) });
              }
            }}
          >
            Clear cart
          </Button>
        }
      />

      <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
        <ul className="space-y-3">
          {cart.items.map((line) => (
            <CartLineRow key={line.product_id} line={line} />
          ))}
        </ul>

        <Card className="space-y-4 self-start p-5">
          <h2 className="font-semibold text-fg">Order summary</h2>
          <div className="flex justify-between text-sm">
            <span className="text-fg-muted">Subtotal</span>
            <span className="font-medium text-fg">{formatMoney(cart.total)}</span>
          </div>
          <p className="text-xs text-fg-subtle">Prices are current catalog prices and are confirmed at checkout.</p>
          {blocked && (
            <Alert>
              {blockers.unavailable > 0 && "Remove items that are no longer available. "}
              {blockers.outOfStock > 0 && "Lower the quantity of items marked as low on stock."}
            </Alert>
          )}
          {blocked ? (
            <Button size="lg" className="w-full" disabled>
              Checkout
            </Button>
          ) : (
            <ButtonLink href="/checkout" size="lg" className="w-full">
              Checkout
            </ButtonLink>
          )}
          <Link href="/products" className="block text-center text-sm text-accent hover:underline">
            Continue shopping
          </Link>
        </Card>
      </div>
    </>
  );
}

function CartLineRow({ line }: { line: CartLine }) {
  const setQuantity = useSetQuantity();
  const remove = useRemoveFromCart();
  const toast = useToast();
  const busy = setQuantity.isPending || remove.isPending;
  const onError = (error: unknown) => toast.error(describeError(error));

  return (
    <li className={cn("flex gap-4 rounded-xl border border-line bg-surface p-4 shadow-sm", busy && "opacity-70")}>
      <ProductImage id={line.product_id} name={line.name} className="size-20 shrink-0" />
      <div className="flex min-w-0 flex-1 flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div className="min-w-0">
          {line.available ? (
            <>
              <Link href={`/products/${line.slug}`} className="font-medium text-fg hover:text-accent">
                {line.name}
              </Link>
              <p className="text-sm text-fg-subtle">{formatMoney(line.unit_price)} each</p>
              {line.in_stock === false && (
                <p className="mt-1 text-sm text-amber-600 dark:text-amber-400">Not enough stock for this quantity.</p>
              )}
            </>
          ) : (
            <>
              <p className="font-medium text-fg-muted">Unavailable product</p>
              <p className="text-sm text-red-600 dark:text-red-400">This product is no longer sold.</p>
            </>
          )}
        </div>

        <div className="flex items-center gap-4">
          {line.available && (
            <QuantityStepper
              value={line.quantity}
              disabled={busy}
              onChange={(quantity) => setQuantity.mutate({ productId: line.product_id, quantity }, { onError })}
            />
          )}
          <span className="w-20 text-right font-medium text-fg tabular-nums">{line.available ? formatMoney(line.line_total) : "—"}</span>
          <button
            type="button"
            disabled={busy}
            onClick={() => remove.mutate(line.product_id, { onError })}
            className="text-sm text-fg-subtle hover:text-red-600"
            aria-label={`Remove ${line.name ?? "item"}`}
          >
            Remove
          </button>
        </div>
      </div>
    </li>
  );
}
