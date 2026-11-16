import type { Money } from "@/lib/api/types";

const formatters = new Map<string, Intl.NumberFormat>();

function formatter(currency: string): Intl.NumberFormat {
  let f = formatters.get(currency);
  if (!f) {
    f = new Intl.NumberFormat("en-US", { style: "currency", currency });
    formatters.set(currency, f);
  }
  return f;
}

/** Minor-unit digits of a currency: 2 for USD, 0 for JPY. */
export function currencyDigits(currency: string): number {
  return formatter(currency).resolvedOptions().maximumFractionDigits ?? 2;
}

/** Formats an API money value (integer minor units) for display. */
export function formatMoney(money: Money | null | undefined): string {
  if (!money) return "—";
  return formatter(money.currency).format(money.amount / 10 ** currencyDigits(money.currency));
}

/** "12.50" → 1250. Returns null for anything that is not a non-negative amount. */
export function toMinorUnits(value: string, currency = "USD"): number | null {
  const trimmed = value.trim();
  if (!/^\d+(\.\d+)?$/.test(trimmed)) return null;
  return Math.round(Number(trimmed) * 10 ** currencyDigits(currency));
}

/** 1250 → "12.50", for prefilling inputs. */
export function fromMinorUnits(amount: number, currency = "USD"): string {
  const digits = currencyDigits(currency);
  return (amount / 10 ** digits).toFixed(digits);
}

export function formatDate(iso: string): string {
  return new Date(iso).toLocaleString("en-US", { dateStyle: "medium", timeStyle: "short" });
}

/** Orders have UUIDs; customers recognise the first block. */
export function shortId(id: string): string {
  return id.slice(0, 8).toUpperCase();
}

export function cn(...classes: Array<string | false | null | undefined>): string {
  return classes.filter(Boolean).join(" ");
}
