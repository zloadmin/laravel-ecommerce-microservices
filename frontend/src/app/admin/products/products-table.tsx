"use client";

import Link from "next/link";
import { useDeferredValue, useState } from "react";
import { describeError } from "@/lib/api/errors";
import { useAdminProducts, useDeleteProduct } from "@/lib/admin";
import { formatMoney } from "@/lib/format";
import { useToast } from "@/components/toast";
import { Button } from "@/components/ui/button";
import { Alert, Input } from "@/components/ui/field";
import { Badge, EmptyState, Skeleton } from "@/components/ui/misc";

export function ProductsTable() {
  const [search, setSearch] = useState("");
  const q = useDeferredValue(search.trim());
  const query = useAdminProducts(q);
  const remove = useDeleteProduct();
  const toast = useToast();
  const products = query.data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div className="space-y-4">
      <Input type="search" placeholder="Search by name or SKU" value={search} onChange={(e) => setSearch(e.target.value)} className="max-w-sm" aria-label="Search products" />

      {query.isPending ? (
        <Skeleton className="h-64" />
      ) : query.error ? (
        <Alert>{describeError(query.error)}</Alert>
      ) : products.length === 0 ? (
        <EmptyState title="No products" description={q ? "Nothing matches this search." : "Create your first product."} />
      ) : (
        <>
          <div className="overflow-x-auto rounded-xl border border-line bg-surface shadow-sm">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-line text-xs text-fg-subtle uppercase">
                <tr>
                  <th className="px-4 py-3 font-medium">Product</th>
                  <th className="px-4 py-3 font-medium">Category</th>
                  <th className="px-4 py-3 text-right font-medium">Price</th>
                  <th className="px-4 py-3 text-right font-medium">Stock</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3" />
                </tr>
              </thead>
              <tbody className="divide-y divide-line">
                {products.map((product) => (
                  <tr key={product.id} className="hover:bg-muted/50">
                    <td className="px-4 py-3">
                      <Link href={`/admin/products/${product.id}`} className="font-medium text-fg hover:text-accent">
                        {product.name}
                      </Link>
                      <p className="font-mono text-xs text-fg-subtle">{product.sku}</p>
                    </td>
                    <td className="px-4 py-3 text-fg-muted">{product.category?.name ?? "—"}</td>
                    <td className="px-4 py-3 text-right tabular-nums">{formatMoney(product.price)}</td>
                    <td className={`px-4 py-3 text-right tabular-nums ${product.stock === 0 ? "text-red-600" : ""}`}>{product.stock}</td>
                    <td className="px-4 py-3">
                      {product.is_active ? <Badge tone="success">Active</Badge> : <Badge>Inactive</Badge>}
                    </td>
                    <td className="px-4 py-3 text-right whitespace-nowrap">
                      <Link href={`/admin/products/${product.id}`} className="mr-3 text-accent hover:underline">
                        Edit
                      </Link>
                      <button
                        type="button"
                        className="text-fg-subtle hover:text-red-600 disabled:opacity-50"
                        disabled={remove.isPending}
                        onClick={() => {
                          if (!confirm(`Delete “${product.name}”? Existing orders keep their copy of it.`)) return;
                          remove.mutate(product.id, {
                            onSuccess: () => toast.success("Product deleted."),
                            onError: (e) => toast.error(describeError(e)),
                          });
                        }}
                      >
                        Delete
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {query.hasNextPage && (
            <div className="text-center">
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
