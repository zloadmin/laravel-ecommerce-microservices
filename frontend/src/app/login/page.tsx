import type { Metadata } from "next";
import { LoginForm } from "@/components/auth-forms";
import { Card } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Sign in" };

const NOTICES: Record<string, string> = {
  "password-changed": "Your password was changed. Sign in with your new password.",
};

export default async function LoginPage({ searchParams }: { searchParams: Promise<{ next?: string; reason?: string }> }) {
  const { next, reason } = await searchParams;
  return (
    <div className="mx-auto max-w-sm">
      <h1 className="mb-2 text-center text-2xl font-semibold tracking-tight text-fg">Welcome back</h1>
      <p className="mb-8 text-center text-sm text-fg-muted">Sign in to check out and track your orders.</p>
      <Card className="p-6">
        <LoginForm next={next ?? "/"} notice={reason ? NOTICES[reason] : undefined} />
      </Card>
    </div>
  );
}
