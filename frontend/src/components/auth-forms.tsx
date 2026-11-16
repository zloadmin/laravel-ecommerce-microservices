"use client";

import Link from "next/link";
import { useActionState } from "react";
import { changePassword, login, register, updateName, type FormState } from "@/lib/auth/actions";
import { Button } from "./ui/button";
import { Alert, Field, Input } from "./ui/field";

function invalid(state: FormState, name: string) {
  return state?.fields?.[name] ? true : undefined;
}

export function LoginForm({ next, notice }: { next: string; notice?: string }) {
  const [state, action, pending] = useActionState(login, null);
  return (
    <form action={action} className="space-y-5" noValidate>
      {notice && !state && <Alert tone="info">{notice}</Alert>}
      {state?.error && <Alert>{state.error}</Alert>}
      <input type="hidden" name="next" value={next} />
      <Field label="Email" htmlFor="email" error={state?.fields?.email}>
        <Input id="email" name="email" type="email" autoComplete="email" required defaultValue={state?.values?.email} aria-invalid={invalid(state, "email")} />
      </Field>
      <Field label="Password" htmlFor="password" error={state?.fields?.password}>
        <Input id="password" name="password" type="password" autoComplete="current-password" required aria-invalid={invalid(state, "password")} />
      </Field>
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Sign in
      </Button>
      <p className="text-center text-sm text-fg-muted">
        New here?{" "}
        <Link href={`/register?next=${encodeURIComponent(next)}`} className="font-medium text-accent hover:underline">
          Create an account
        </Link>
      </p>
    </form>
  );
}

export function RegisterForm({ next }: { next: string }) {
  const [state, action, pending] = useActionState(register, null);
  return (
    <form action={action} className="space-y-5" noValidate>
      {state?.error && <Alert>{state.error}</Alert>}
      <input type="hidden" name="next" value={next} />
      <Field label="Name" htmlFor="name" error={state?.fields?.name}>
        <Input id="name" name="name" autoComplete="name" required defaultValue={state?.values?.name} aria-invalid={invalid(state, "name")} />
      </Field>
      <Field label="Email" htmlFor="email" error={state?.fields?.email}>
        <Input id="email" name="email" type="email" autoComplete="email" required defaultValue={state?.values?.email} aria-invalid={invalid(state, "email")} />
      </Field>
      <Field label="Password" htmlFor="password" error={state?.fields?.password} hint="At least 8 characters.">
        <Input id="password" name="password" type="password" autoComplete="new-password" required minLength={8} aria-invalid={invalid(state, "password")} />
      </Field>
      <Field label="Confirm password" htmlFor="password_confirmation" error={state?.fields?.password_confirmation}>
        <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required aria-invalid={invalid(state, "password_confirmation")} />
      </Field>
      <Button type="submit" size="lg" className="w-full" loading={pending}>
        Create account
      </Button>
      <p className="text-center text-sm text-fg-muted">
        Already have an account?{" "}
        <Link href={`/login?next=${encodeURIComponent(next)}`} className="font-medium text-accent hover:underline">
          Sign in
        </Link>
      </p>
    </form>
  );
}

export function NameForm({ name }: { name: string }) {
  const [state, action, pending] = useActionState(updateName, null);
  return (
    <form action={action} className="space-y-4">
      {state?.error && <Alert>{state.error}</Alert>}
      {state?.success && <Alert tone="success">{state.success}</Alert>}
      <Field label="Name" htmlFor="name" error={state?.fields?.name}>
        <Input id="name" name="name" required defaultValue={state?.values?.name ?? name} aria-invalid={invalid(state, "name")} />
      </Field>
      <Button type="submit" loading={pending}>
        Save name
      </Button>
    </form>
  );
}

export function PasswordForm() {
  const [state, action, pending] = useActionState(changePassword, null);
  return (
    <form action={action} className="space-y-4">
      {state?.error && <Alert>{state.error}</Alert>}
      <Field label="Current password" htmlFor="current_password" error={state?.fields?.current_password}>
        <Input id="current_password" name="current_password" type="password" autoComplete="current-password" required aria-invalid={invalid(state, "current_password")} />
      </Field>
      <Field label="New password" htmlFor="new_password" error={state?.fields?.password} hint="At least 8 characters.">
        <Input id="new_password" name="password" type="password" autoComplete="new-password" required minLength={8} aria-invalid={invalid(state, "password")} />
      </Field>
      <Field label="Confirm new password" htmlFor="password_confirmation" error={state?.fields?.password_confirmation}>
        <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" required aria-invalid={invalid(state, "password_confirmation")} />
      </Field>
      <p className="text-sm text-fg-subtle">Changing your password signs you out on every device.</p>
      <Button type="submit" variant="secondary" loading={pending}>
        Change password
      </Button>
    </form>
  );
}
