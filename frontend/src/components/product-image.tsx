import { cn } from "@/lib/format";

function hue(seed: string): number {
  let hash = 0;
  for (let i = 0; i < seed.length; i++) hash = (hash * 31 + seed.charCodeAt(i)) | 0;
  return Math.abs(hash) % 360;
}

/** Products have no images in the API, so each gets a stable colour and its initials. */
export function ProductImage({ id, name, className }: { id: string; name?: string; className?: string }) {
  const h = hue(id);
  const initials = (name ?? "?")
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word[0]?.toUpperCase())
    .join("");

  return (
    <div
      aria-hidden="true"
      className={cn("flex aspect-square items-center justify-center overflow-hidden rounded-lg select-none", className)}
      style={{ background: `linear-gradient(135deg, hsl(${h} 70% 88%), hsl(${(h + 45) % 360} 65% 72%))` }}
    >
      <span className="text-[min(2.5rem,30cqw)] font-semibold text-black/40" style={{ containerType: "inline-size" }}>
        {initials}
      </span>
    </div>
  );
}
