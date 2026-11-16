import Link from "next/link";
import type { Product } from "@/lib/api/types";
import { formatMoney } from "@/lib/format";
import { ProductImage } from "./product-image";

export function ProductCard({ product }: { product: Product }) {
  return (
    <Link
      href={`/products/${product.slug}`}
      className="group flex flex-col rounded-xl border border-line bg-surface p-3 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
    >
      <ProductImage id={product.id} name={product.name} className="w-full" />
      <div className="mt-3 flex flex-1 flex-col px-1">
        {product.category && <p className="text-xs text-fg-subtle">{product.category.name}</p>}
        <h3 className="mt-0.5 line-clamp-2 font-medium text-fg group-hover:text-accent">{product.name}</h3>
        <div className="mt-auto flex items-center justify-between pt-3">
          <span className="font-semibold text-fg">{formatMoney(product.price)}</span>
          {product.stock === 0 ? (
            <span className="text-xs text-red-600 dark:text-red-400">Out of stock</span>
          ) : product.stock <= 5 ? (
            <span className="text-xs text-amber-600 dark:text-amber-400">Only {product.stock} left</span>
          ) : null}
        </div>
      </div>
    </Link>
  );
}

export function ProductGrid({ products }: { products: Product[] }) {
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      {products.map((product) => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}
