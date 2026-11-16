"use client";

import Link from "next/link";
import { describeError } from "@/lib/api/errors";
import { useAdminProduct } from "@/lib/admin";
import { ProductForm } from "../product-form";
import { Alert } from "@/components/ui/field";
import { PageHeader, Skeleton } from "@/components/ui/misc";

export function EditProduct({ id }: { id: string }) {
  const { data: product, isPending, error } = useAdminProduct(id);

  if (isPending) return <Skeleton className="h-96" />;
  if (error) return <Alert>{describeError(error)}</Alert>;

  return (
    <>
      <PageHeader
        title={product.name}
        description={
          <Link href={`/products/${product.slug}`} className="text-accent hover:underline">
            View in store →
          </Link>
        }
      />
      <ProductForm product={product} />
    </>
  );
}
