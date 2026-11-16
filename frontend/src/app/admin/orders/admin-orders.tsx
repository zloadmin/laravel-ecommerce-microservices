"use client";

import { useState, type FormEvent } from "react";
import { OrdersList } from "@/components/orders-list";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/field";

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

export function AdminOrders() {
  const [draft, setDraft] = useState("");
  const [userId, setUserId] = useState<string | undefined>();
  const valid = !draft.trim() || UUID.test(draft.trim());

  const submit = (event: FormEvent) => {
    event.preventDefault();
    if (valid) setUserId(draft.trim() || undefined);
  };

  return (
    <div className="space-y-5">
      <form onSubmit={submit} className="flex max-w-lg gap-2">
        <Input
          value={draft}
          onChange={(e) => setDraft(e.target.value)}
          placeholder="Filter by customer id (UUID)"
          aria-label="Customer id"
          aria-invalid={!valid}
          className="font-mono"
        />
        <Button type="submit" variant="secondary" disabled={!valid}>
          Filter
        </Button>
      </form>
      <OrdersList admin userId={userId} key={userId ?? "all"} />
    </div>
  );
}
