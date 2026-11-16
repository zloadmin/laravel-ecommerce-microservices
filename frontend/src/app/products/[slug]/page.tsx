import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";
import { getProduct } from "@/lib/catalog";
import { formatMoney } from "@/lib/format";
import { ProductImage } from "@/components/product-image";
import { AddToCart } from "@/components/add-to-cart";
import { Badge } from "@/components/ui/misc";

type Props = { params: Promise<{ slug: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const product = await getProduct((await params).slug);
  return { title: product?.name ?? "Product not found", description: product?.description ?? undefined };
}

export default async function ProductPage({ params }: Props) {
  const product = await getProduct((await params).slug);
  if (!product) notFound();

  return (
    <div>
      <nav className="mb-6 text-sm text-fg-subtle" aria-label="Breadcrumb">
        <Link href="/products" className="hover:text-fg">
          Products
        </Link>
        {product.category && (
          <>
            {" / "}
            <Link href={`/products?category=${product.category.slug}`} className="hover:text-fg">
              {product.category.name}
            </Link>
          </>
        )}
      </nav>

      <div className="grid gap-10 md:grid-cols-2">
        <ProductImage id={product.id} name={product.name} className="w-full rounded-2xl" />

        <div className="flex flex-col">
          {!product.is_active && (
            <div className="mb-3">
              <Badge tone="warning">Inactive · only visible to admins</Badge>
            </div>
          )}
          <h1 className="text-3xl font-semibold tracking-tight text-fg">{product.name}</h1>
          <p className="mt-1 text-sm text-fg-subtle">SKU {product.sku}</p>
          <p className="mt-6 text-3xl font-semibold text-fg">{formatMoney(product.price)}</p>

          <p className="mt-2 text-sm">
            {product.stock === 0 ? (
              <span className="text-red-600 dark:text-red-400">Out of stock</span>
            ) : product.stock <= 5 ? (
              <span className="text-amber-600 dark:text-amber-400">Only {product.stock} left in stock</span>
            ) : (
              <span className="text-emerald-600 dark:text-emerald-400">In stock</span>
            )}
          </p>

          <div className="mt-8">
            <AddToCart productId={product.id} stock={product.stock} />
          </div>

          {product.description && (
            <div className="mt-10 border-t border-line pt-6">
              <h2 className="text-sm font-semibold text-fg">Description</h2>
              <p className="mt-2 leading-relaxed whitespace-pre-line text-fg-muted">{product.description}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
