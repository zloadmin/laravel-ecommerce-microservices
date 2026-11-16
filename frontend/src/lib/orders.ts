"use client";

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api/client";
import { unwrap } from "@/lib/api/errors";
import type { Order, OrderStatus, Page } from "@/lib/api/types";
import { cartKey } from "@/lib/cart";

export const ordersKey = ["orders"] as const;
const orderKey = (id: string) => ["orders", "detail", id] as const;

export function useOrders(filters: { status?: OrderStatus; userId?: string }) {
  return useInfiniteQuery({
    queryKey: [...ordersKey, "list", filters],
    initialPageParam: undefined as string | undefined,
    queryFn: ({ pageParam }) =>
      unwrap(
        api.GET("/orders", {
          params: { query: { status: filters.status, user_id: filters.userId, cursor: pageParam, per_page: 20 } },
        }),
      ) as Promise<Page<Order>>,
    getNextPageParam: (page) => page.meta.next_cursor ?? undefined,
  });
}

/** Polls while the order is pending, since stock is reserved asynchronously. */
export function useOrder(id: string) {
  return useQuery({
    queryKey: orderKey(id),
    queryFn: () =>
      unwrap(api.GET("/orders/{id}", { params: { path: { id } } })).then((body) => (body as { data: Order }).data),
    refetchInterval: (query) => (query.state.data?.status === "pending" ? 2000 : false),
  });
}

export function useCancelOrder() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: string) =>
      unwrap(api.POST("/orders/{id}/cancel", { params: { path: { id } } })).then((body) => (body as { data: Order }).data),
    onSuccess: (order) => {
      client.setQueryData(orderKey(order.id), order);
      client.invalidateQueries({ queryKey: [...ordersKey, "list"] });
    },
  });
}

export function useCheckout() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (idempotencyKey: string) =>
      unwrap(api.POST("/cart/checkout", { params: { header: { "Idempotency-Key": idempotencyKey } } })).then(
        (body) => (body as { data: Order }).data,
      ),
    onSuccess: (order) => {
      client.setQueryData(orderKey(order.id), order);
      client.invalidateQueries({ queryKey: cartKey });
      client.invalidateQueries({ queryKey: [...ordersKey, "list"] });
    },
  });
}

export const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: "Processing",
  confirmed: "Confirmed",
  cancelled: "Cancelled",
};

export const CANCELLATION_REASONS: Record<string, string> = {
  insufficient_stock: "Some items were out of stock.",
  cancelled_by_customer: "You cancelled this order.",
  cancelled_by_admin: "The store cancelled this order.",
  expired: "We couldn't confirm stock in time.",
};
