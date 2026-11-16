"use client";

import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api/client";
import { unwrap } from "@/lib/api/errors";
import type { Cart } from "@/lib/api/types";

export const cartKey = ["cart"] as const;

const asCart = (body: { data: unknown }) => body.data as Cart;

export function useCart() {
  return useQuery({
    queryKey: cartKey,
    queryFn: () => unwrap(api.GET("/cart")).then(asCart),
  });
}

/** Every cart endpoint returns the whole cart, so mutations replace the cached copy with it. */
function useCartMutation<V>(request: (vars: V) => Promise<Cart>) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: request,
    onSuccess: (cart) => client.setQueryData(cartKey, cart),
  });
}

export function useAddToCart() {
  return useCartMutation(({ productId, quantity }: { productId: string; quantity: number }) =>
    unwrap(api.POST("/cart/items", { body: { product_id: productId, quantity } })).then(asCart),
  );
}

export function useSetQuantity() {
  return useCartMutation(({ productId, quantity }: { productId: string; quantity: number }) =>
    unwrap(api.PUT("/cart/items/{productId}", { params: { path: { productId } }, body: { quantity } })).then(asCart),
  );
}

export function useRemoveFromCart() {
  return useCartMutation((productId: string) =>
    unwrap(api.DELETE("/cart/items/{productId}", { params: { path: { productId } } })).then(asCart),
  );
}

export function useClearCart() {
  return useCartMutation(() => unwrap(api.DELETE("/cart")).then(asCart));
}

/** Lines that block checkout: products that are gone, or quantities that exceed stock. */
export function checkoutBlockers(cart: Cart | undefined) {
  const lines = cart?.items ?? [];
  return {
    unavailable: lines.filter((line) => !line.available).length,
    outOfStock: lines.filter((line) => line.available && line.in_stock === false).length,
  };
}
