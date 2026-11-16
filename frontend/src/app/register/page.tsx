import type { Metadata } from "next";
import { RegisterForm } from "@/components/auth-forms";
import { Card } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Create account" };

export default async function RegisterPage({ searchParams }: { searchParams: Promise<{ next?: string }> }) {
  const { next } = await searchParams;
  return (
    <div className="mx-auto max-w-sm">
      <h1 className="mb-2 text-center text-2xl font-semibold tracking-tight text-fg">Create your account</h1>
      <p className="mb-8 text-center text-sm text-fg-muted">Your cart comes with you when you sign up.</p>
      <Card className="p-6">
        <RegisterForm next={next ?? "/"} />
      </Card>
    </div>
  );
}
