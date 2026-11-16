"use client";

import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { api } from "@/lib/api/client";
import { unwrap } from "@/lib/api/errors";
import type { Category, CategoryInput, Page, Product, ProductInput } from "@/lib/api/types";

const productsKey = ["admin", "products"] as const;
const categoriesKey = ["categories"] as const;

const data = <T,>(body: unknown) => (body as { data: T }).data;

/** As an admin, the product list includes inactive products. */
export function useAdminProducts(q: string) {
  return useInfiniteQuery({
    queryKey: [...productsKey, "list", q],
    initialPageParam: undefined as string | undefined,
    queryFn: ({ pageParam }) =>
      unwrap(
        api.GET("/products", { params: { query: { q: q || undefined, sort: "newest", per_page: 25, cursor: pageParam } } }),
      ) as Promise<Page<Product>>,
    getNextPageParam: (page) => page.meta.next_cursor ?? undefined,
  });
}

export function useAdminProduct(id: string) {
  return useQuery({
    queryKey: [...productsKey, "detail", id],
    queryFn: () => unwrap(api.GET("/products/{id}", { params: { path: { id } } })).then(data<Product>),
  });
}

/** Creates a product when no id is given, otherwise updates it. */
export function useSaveProduct(id?: string) {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (input: Partial<ProductInput>) =>
      (id
        ? unwrap(api.PATCH("/products/{id}", { params: { path: { id } }, body: input as ProductInput }))
        : unwrap(api.POST("/products", { body: input as ProductInput }))
      ).then(data<Product>),
    onSuccess: () => client.invalidateQueries({ queryKey: productsKey }),
  });
}

export function useDeleteProduct() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => unwrap(api.DELETE("/products/{id}", { params: { path: { id } } })),
    onSuccess: () => client.invalidateQueries({ queryKey: productsKey }),
  });
}

export function useCategories() {
  return useQuery({
    queryKey: categoriesKey,
    queryFn: () => unwrap(api.GET("/categories")).then(data<Category[]>),
  });
}

export function useSaveCategory() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: ({ id, input }: { id?: string; input: Partial<CategoryInput> }) =>
      (id
        ? unwrap(api.PATCH("/categories/{id}", { params: { path: { id } }, body: input as CategoryInput }))
        : unwrap(api.POST("/categories", { body: input as CategoryInput }))
      ).then(data<Category>),
    onSuccess: () => client.invalidateQueries({ queryKey: categoriesKey }),
  });
}

export function useDeleteCategory() {
  const client = useQueryClient();
  return useMutation({
    mutationFn: (id: string) => unwrap(api.DELETE("/categories/{id}", { params: { path: { id } } })),
    onSuccess: () => client.invalidateQueries({ queryKey: categoriesKey }),
  });
}
