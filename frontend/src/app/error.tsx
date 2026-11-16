"use client";

import { Button } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/misc";

export default function ErrorPage({ reset }: { error: Error & { digest?: string }; reset(): void }) {
  return (
    <EmptyState
      title="Something went wrong"
      description="The store couldn't load this page. It may be temporarily unavailable."
      action={<Button onClick={reset}>Try again</Button>}
    />
  );
}
