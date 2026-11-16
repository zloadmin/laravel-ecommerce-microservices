import type { Metadata } from "next";
import { EditProduct } from "./edit-product";

export const metadata: Metadata = { title: "Edit product" };

export default async function EditProductPage({ params }: { params: Promise<{ id: string }> }) {
  return <EditProduct id={(await params).id} />;
}
