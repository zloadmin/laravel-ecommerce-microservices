import { ButtonLink } from "@/components/ui/button";
import { EmptyState } from "@/components/ui/misc";

export default function NotFound() {
  return (
    <EmptyState
      title="Page not found"
      description="The page you're looking for doesn't exist or has been moved."
      action={<ButtonLink href="/products">Browse products</ButtonLink>}
    />
  );
}
