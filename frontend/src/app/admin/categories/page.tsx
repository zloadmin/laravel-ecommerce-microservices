import type { Metadata } from "next";
import { CategoriesManager } from "./categories-manager";
import { PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Categories" };

export default function AdminCategoriesPage() {
  return (
    <>
      <PageHeader title="Categories" description="A category can only be deleted once it has no products." />
      <CategoriesManager />
    </>
  );
}
