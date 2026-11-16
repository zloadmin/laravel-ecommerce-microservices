import type { Metadata } from "next";
import { OrdersList } from "@/components/orders-list";
import { PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "My orders" };

export default function OrdersPage() {
  return (
    <>
      <PageHeader title="My orders" />
      <OrdersList />
    </>
  );
}
