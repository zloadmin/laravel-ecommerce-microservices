import type { Metadata } from "next";
import { ProductsTable } from "./products-table";
import { ButtonLink } from "@/components/ui/button";
import { PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Products" };

export default function AdminProductsPage() {
  return (
    <>
      <PageHeader
        title="Products"
        description="Includes inactive products, which shoppers can't see."
        actions={<ButtonLink href="/admin/products/new">New product</ButtonLink>}
      />
      <ProductsTable />
    </>
  );
}
