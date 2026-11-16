"use client";

import { cn } from "@/lib/format";

type Props = {
  value: number;
  onChange(value: number): void;
  min?: number;
  max?: number;
  disabled?: boolean;
  label?: string;
};

export function QuantityStepper({ value, onChange, min = 1, max = 100, disabled, label = "Quantity" }: Props) {
  const button =
    "flex size-9 items-center justify-center text-lg text-fg-muted hover:bg-muted hover:text-fg disabled:opacity-40 disabled:hover:bg-transparent";
  return (
    <div className={cn("inline-flex items-center rounded-lg border border-line bg-surface", disabled && "opacity-60")}>
      <button type="button" className={button} aria-label="Decrease quantity" disabled={disabled || value <= min} onClick={() => onChange(value - 1)}>
        −
      </button>
      <span aria-label={label} aria-live="polite" className="w-10 text-center text-sm font-medium tabular-nums text-fg">
        {value}
      </span>
      <button type="button" className={button} aria-label="Increase quantity" disabled={disabled || value >= max} onClick={() => onChange(value + 1)}>
        +
      </button>
    </div>
  );
}
