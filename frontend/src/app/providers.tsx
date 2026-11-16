"use client";

import { MutationCache, QueryCache, QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState, type ReactNode } from "react";
import { ApiError } from "@/lib/api/errors";
import { ToastProvider } from "@/components/toast";

/** A 401 from the API means the session ended (e.g. signed out elsewhere): send the visitor to sign in. */
function onError(error: unknown) {
  if (error instanceof ApiError && error.status === 401) {
    const next = window.location.pathname + window.location.search;
    window.location.assign(`/login?next=${encodeURIComponent(next)}`);
  }
}

function makeClient() {
  return new QueryClient({
    queryCache: new QueryCache({ onError }),
    mutationCache: new MutationCache({ onError }),
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        refetchOnWindowFocus: false,
        // Client errors will not fix themselves; only retry server and network failures.
        retry: (count, error) => count < 2 && !(error instanceof ApiError && error.status < 500),
      },
    },
  });
}

/**
 * The root layout keys this component by user id, so signing in or out starts with an empty
 * cache and nobody sees the previous visitor's cart or orders.
 */
export function Providers({ children }: { children: ReactNode }) {
  const [client] = useState(makeClient);
  return (
    <QueryClientProvider client={client}>
      <ToastProvider>{children}</ToastProvider>
    </QueryClientProvider>
  );
}
