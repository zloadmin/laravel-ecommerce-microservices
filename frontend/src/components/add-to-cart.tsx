"use client";

import Link from "next/link";
import { useState } from "react";
import { describeError } from "@/lib/api/errors";
import { useAddToCart, useCart } from "@/lib/cart";
import { Button } from "./ui/button";
import { QuantityStepper } from "./quantity-stepper";
import { useToast } from "./toast";

/** The API accepts up to 100 per line. */
const MAX_PER_LINE = 100;

export function AddToCart({ productId, stock }: { productId: string; stock: number }) {
  const [quantity, setQuantity] = useState(1);
  const add = useAddToCart();
  const { data: cart } = useCart();
  const toast = useToast();

  const inCart = cart?.items.find((line) => line.product_id === productId)?.quantity ?? 0;
  const max = Math.max(0, Math.min(stock, MAX_PER_LINE) - inCart);

  if (stock === 0) {
    return <Button disabled size="lg">Out of stock</Button>;
  }

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center gap-3">
        <QuantityStepper value={Math.min(quantity, Math.max(max, 1))} onChange={setQuantity} max={Math.max(max, 1)} disabled={max === 0} />
        <Button
          size="lg"
          loading={add.isPending}
          disabled={max === 0}
          onClick={() =>
            add.mutate(
              { productId, quantity: Math.min(quantity, max) },
              {
                onSuccess: () => {
                  toast.success("Added to your cart.");
                  setQuantity(1);
                },
                onError: (error) => toast.error(describeError(error)),
              },
            )
          }
        >
          Add to cart
        </Button>
      </div>
      {inCart > 0 && (
        <p className="text-sm text-fg-muted">
          {inCart} already in your{" "}
          <Link href="/cart" className="font-medium text-accent hover:underline">
            cart
          </Link>
          {max === 0 && " — that's all we have."}
        </p>
      )}
    </div>
  );
}
