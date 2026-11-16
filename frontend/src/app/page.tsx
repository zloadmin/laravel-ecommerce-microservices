import Link from "next/link";
import { listCategories, listProducts } from "@/lib/catalog";
import { ProductGrid } from "@/components/product-card";
import { ButtonLink } from "@/components/ui/button";

export default async function HomePage() {
  const [categories, newest] = await Promise.all([
    listCategories(),
    listProducts({ sort: "newest", per_page: 8 }),
  ]);

  return (
    <div className="space-y-14">
      <section className="overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-indigo-500 to-violet-500 px-6 py-14 text-white sm:px-12 sm:py-20">
        <p className="text-sm font-medium tracking-wide text-indigo-100 uppercase">New season</p>
        <h1 className="mt-2 max-w-xl text-4xl font-bold tracking-tight sm:text-5xl">Gear that keeps up with you.</h1>
        <p className="mt-4 max-w-md text-indigo-100">
          Laptops, phones and the accessories that tie them together, shipped from live stock.
        </p>
        <div className="mt-8 flex flex-wrap gap-3">
          <ButtonLink href="/products" size="lg" variant="secondary" className="border-transparent">
            Shop all products
          </ButtonLink>
        </div>
      </section>

      {categories.length > 0 && (
        <section>
          <h2 className="mb-4 text-xl font-semibold text-fg">Shop by category</h2>
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
            {categories.map((category) => (
              <Link
                key={category.id}
                href={`/products?category=${category.slug}`}
                className="rounded-xl border border-line bg-surface px-5 py-6 font-medium text-fg shadow-sm transition hover:border-accent hover:text-accent"
              >
                {category.name}
                <span className="mt-1 block text-sm font-normal text-fg-subtle">Browse →</span>
              </Link>
            ))}
          </div>
        </section>
      )}

      <section>
        <div className="mb-4 flex items-end justify-between">
          <h2 className="text-xl font-semibold text-fg">New arrivals</h2>
          <Link href="/products" className="text-sm font-medium text-accent hover:underline">
            View all
          </Link>
        </div>
        <ProductGrid products={newest.data} />
      </section>
    </div>
  );
}
