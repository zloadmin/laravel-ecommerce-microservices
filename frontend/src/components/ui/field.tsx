import type { ComponentProps, ReactNode } from "react";
import { cn } from "@/lib/format";

const control =
  "w-full rounded-lg border border-line bg-surface px-3 text-sm text-fg shadow-sm placeholder:text-fg-subtle " +
  "focus:border-accent focus:ring-2 focus:ring-accent/20 focus:outline-none disabled:opacity-60 " +
  "aria-[invalid=true]:border-red-500 aria-[invalid=true]:focus:ring-red-500/20";

export function Input({ className, ...props }: ComponentProps<"input">) {
  return <input className={cn(control, "h-10", className)} {...props} />;
}

export function Textarea({ className, ...props }: ComponentProps<"textarea">) {
  return <textarea className={cn(control, "min-h-28 py-2", className)} {...props} />;
}

export function Select({ className, ...props }: ComponentProps<"select">) {
  return <select className={cn(control, "h-10 pr-8", className)} {...props} />;
}

type FieldProps = {
  label: string;
  htmlFor: string;
  error?: string | string[];
  hint?: ReactNode;
  children: ReactNode;
  className?: string;
};

/** A label, a control and its first error message. */
export function Field({ label, htmlFor, error, hint, children, className }: FieldProps) {
  const message = Array.isArray(error) ? error[0] : error;
  return (
    <div className={cn("space-y-1.5", className)}>
      <label htmlFor={htmlFor} className="block text-sm font-medium text-fg">
        {label}
      </label>
      {children}
      {message ? (
        <p className="text-sm text-red-600 dark:text-red-400">{message}</p>
      ) : hint ? (
        <p className="text-sm text-fg-subtle">{hint}</p>
      ) : null}
    </div>
  );
}

export function Alert({ tone = "error", children }: { tone?: "error" | "success" | "info"; children: ReactNode }) {
  const tones = {
    error: "border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/50 dark:text-red-200",
    success:
      "border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/50 dark:text-emerald-200",
    info: "border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/50 dark:text-sky-200",
  };
  return (
    <div role={tone === "error" ? "alert" : "status"} className={cn("rounded-lg border px-4 py-3 text-sm", tones[tone])}>
      {children}
    </div>
  );
}
