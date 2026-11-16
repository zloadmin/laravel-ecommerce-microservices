import type { Metadata } from "next";
import Link from "next/link";
import { ApiError, describeError } from "@/lib/api/errors";
import { SORTS, listCategories, listProducts, type ProductQuery, type Sort } from "@/lib/catalog";
import { toMinorUnits } from "@/lib/format";
import { ProductGrid } from "@/components/product-card";
import { AutoSubmitSelect } from "@/components/auto-submit-select";
import { Alert, Field, Input, Select } from "@/components/ui/field";
import { Button, ButtonLink, buttonClasses } from "@/components/ui/button";
import { EmptyState, PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Products" };

type Params = Record<string, string | string[] | undefined>;

const one = (value: string | string[] | undefined) => (Array.isArray(value) ? value[0] : value)?.trim() || undefined;

export default async function ProductsPage({ searchParams }: { searchParams: Promise<Params> }) {
  const params = await searchParams;
  const filters = {
    q: one(params.q),
    category: one(params.category),
    min: one(params.min),
    max: one(params.max),
    sort: (SORTS.some((s) => s.value === one(params.sort)) ? one(params.sort) : "newest") as Sort,
  };

  // Shoppers type prices in dollars; the API takes minor units.
  const query: ProductQuery = {
    q: filters.q,
    category: filters.category,
    min_price: filters.min ? (toMinorUnits(filters.min) ?? undefined) : undefined,
    max_price: filters.max ? (toMinorUnits(filters.max) ?? undefined) : undefined,
    sort: filters.sort,
    cursor: one(params.cursor),
    per_page: 12,
  };

  const categories = await listCategories();
  let result: Awaited<ReturnType<typeof listProducts>> | null = null;
  let error: string | null = null;
  try {
    result = await listProducts(query);
  } catch (e) {
    if (!(e instanceof ApiError) || e.status >= 500) throw e;
    error = describeError(e);
  }

  const activeCategory = categories.find((c) => c.slug === filters.category || c.id === filters.category);
  const hasFilters = Boolean(filters.q || filters.category || filters.min || filters.max);

  const pageLink = (cursor: string) => {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(filters)) if (value) search.set(key, value);
    search.set("cursor", cursor);
    return `/products?${search}`;
  };

  return (
    <>
      <PageHeader
        title={activeCategory?.name ?? (filters.q ? `Results for “${filters.q}”` : "All products")}
        description={hasFilters ? <Link href="/products" className="text-accent hover:underline">Clear all filters</Link> : undefined}
      />

      <div className="grid gap-8 lg:grid-cols-[240px_1fr]">
        <form action="/products" className="space-y-5 self-start rounded-xl border border-line bg-surface p-5 shadow-sm">
          <Field label="Search" htmlFor="q">
            <Input id="q" name="q" type="search" defaultValue={filters.q} placeholder="Name or SKU" />
          </Field>
          <Field label="Category" htmlFor="category">
            <Select id="category" name="category" defaultValue={activeCategory?.slug ?? ""}>
              <option value="">All categories</option>
              {categories.map((category) => (
                <option key={category.id} value={category.slug}>
                  {category.name}
                </option>
              ))}
            </Select>
          </Field>
          <fieldset>
            <legend className="mb-1.5 text-sm font-medium text-fg">Price, USD</legend>
            <div className="flex items-center gap-2">
              <Input name="min" inputMode="decimal" placeholder="Min" defaultValue={filters.min} aria-label="Minimum price" />
              <span className="text-fg-subtle">–</span>
              <Input name="max" inputMode="decimal" placeholder="Max" defaultValue={filters.max} aria-label="Maximum price" />
            </div>
          </fieldset>
          <Field label="Sort by" htmlFor="sort">
            <AutoSubmitSelect id="sort" name="sort" defaultValue={filters.sort}>
              {SORTS.map((sort) => (
                <option key={sort.value} value={sort.value}>
                  {sort.label}
                </option>
              ))}
            </AutoSubmitSelect>
          </Field>
          <Button type="submit" className="w-full">
            Apply filters
          </Button>
        </form>

        <div>
          {error ? (
            <Alert>{error}</Alert>
          ) : result && result.data.length > 0 ? (
            <>
              <ProductGrid products={result.data} />
              <nav className="mt-10 flex justify-between" aria-label="Pagination">
                {result.meta.prev_cursor ? (
                  <Link href={pageLink(result.meta.prev_cursor)} className={buttonClasses("secondary")}>
                    ← Previous
                  </Link>
                ) : (
                  <span />
                )}
                {result.meta.next_cursor && (
                  <Link href={pageLink(result.meta.next_cursor)} className={buttonClasses("secondary")}>
                    Next →
                  </Link>
                )}
              </nav>
            </>
          ) : (
            <EmptyState
              title="No products found"
              description="Try a different search or remove some filters."
              action={<ButtonLink href="/products" variant="secondary">Show all products</ButtonLink>}
            />
          )}
        </div>
      </div>
    </>
  );
}
