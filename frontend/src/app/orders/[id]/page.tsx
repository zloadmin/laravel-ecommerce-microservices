import type { Metadata } from "next";
import { OrderView } from "./order-view";

export const metadata: Metadata = { title: "Order" };

export default async function OrderPage({
  params,
  searchParams,
}: {
  params: Promise<{ id: string }>;
  searchParams: Promise<{ placed?: string }>;
}) {
  const [{ id }, { placed }] = await Promise.all([params, searchParams]);
  return <OrderView id={id} justPlaced={placed === "1"} />;
}
