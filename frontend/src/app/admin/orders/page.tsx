import type { Metadata } from "next";
import { AdminOrders } from "./admin-orders";
import { PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Orders" };

export default function AdminOrdersPage() {
  return (
    <>
      <PageHeader title="Orders" description="Every customer's orders, newest first." />
      <AdminOrders />
    </>
  );
}
