import type { OrderStatus } from "@/lib/api/types";
import { STATUS_LABELS } from "@/lib/orders";
import { Badge } from "./ui/misc";

const TONES = { pending: "info", confirmed: "success", cancelled: "danger" } as const;

export function OrderStatusBadge({ status }: { status: OrderStatus }) {
  return <Badge tone={TONES[status]}>{STATUS_LABELS[status]}</Badge>;
}
