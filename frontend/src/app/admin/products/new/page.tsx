import type { Metadata } from "next";
import { ProductForm } from "../product-form";
import { PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "New product" };

export default function NewProductPage() {
  return (
    <>
      <PageHeader title="New product" />
      <ProductForm />
    </>
  );
}
