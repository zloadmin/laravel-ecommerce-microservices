import "server-only";
import { cache } from "react";
import { ApiError, unwrap } from "@/lib/api/errors";
import { serverApi } from "@/lib/api/server";
import type { Category, Page, Product } from "@/lib/api/types";

export const SORTS = [
  { value: "newest", label: "Newest" },
  { value: "price", label: "Price: low to high" },
  { value: "-price", label: "Price: high to low" },
  { value: "name", label: "Name: A–Z" },
  { value: "-name", label: "Name: Z–A" },
  { value: "oldest", label: "Oldest" },
] as const;

export type Sort = (typeof SORTS)[number]["value"];

export type ProductQuery = {
  q?: string;
  category?: string;
  min_price?: number;
  max_price?: number;
  sort?: Sort;
  cursor?: string;
  per_page?: number;
};

export async function listProducts(query: ProductQuery): Promise<Page<Product>> {
  const api = await serverApi();
  return unwrap(api.GET("/products", { params: { query } })) as Promise<Page<Product>>;
}

export const listCategories = cache(async (): Promise<Category[]> => {
  const api = await serverApi();
  const body = await unwrap(api.GET("/categories"));
  return (body as { data: Category[] }).data;
});

/** A product by slug or id, or null if it does not exist (or is hidden from this visitor). */
export const getProduct = cache(async (idOrSlug: string): Promise<Product | null> => {
  const api = await serverApi();
  try {
    const body = await unwrap(api.GET("/products/{id}", { params: { path: { id: idOrSlug } } }));
    return (body as { data: Product }).data;
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return null;
    throw error;
  }
});
