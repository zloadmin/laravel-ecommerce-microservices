"use server";

import { cookies } from "next/headers";
import { redirect } from "next/navigation";
import { ApiError, describeError, type FieldErrors } from "@/lib/api/errors";
import { publicApi, serverApi } from "@/lib/api/server";
import type { Session } from "@/lib/api/types";
import { CART_COOKIE, REFRESH_COOKIE, clearSession, writeSession, writeUser } from "./cookies";

export type FormState = {
  error?: string;
  success?: string;
  fields?: FieldErrors;
  values?: Record<string, string>;
} | null;

const text = (form: FormData, name: string) => String(form.get(name) ?? "").trim();

/** Only same-site paths, so `?next=` cannot send people to another site. */
function safeNext(value: FormDataEntryValue | null): string {
  const next = typeof value === "string" ? value : "";
  return next.startsWith("/") && !next.startsWith("//") ? next : "/";
}

function failure(error: unknown, values?: Record<string, string>): FormState {
  if (error instanceof ApiError) {
    const message = error.code === "validation_failed" ? "Please correct the highlighted fields." : describeError(error);
    return { error: message, fields: error.fields, values };
  }
  console.error("Server action failed", error);
  return { error: describeError(error), values };
}

/** Stores the new session and moves any guest cart into the customer's cart. */
async function startSession(session: Session): Promise<void> {
  const jar = await cookies();
  writeSession(jar, session);

  const guestCartId = jar.get(CART_COOKIE)?.value;
  if (guestCartId) {
    const response = await publicApi()
      .POST("/cart/merge", {
        body: { guest_cart_id: guestCartId },
        headers: { Authorization: `Bearer ${session.access_token}` },
      })
      .catch(() => null);
    // Keep the guest cart if the merge failed for a temporary reason, so nothing is lost.
    if (response && (response.response.ok || response.response.status < 500)) {
      jar.delete(CART_COOKIE);
    }
  }
}

export async function login(_: FormState, form: FormData): Promise<FormState> {
  const email = text(form, "email");
  const password = String(form.get("password") ?? "");

  try {
    const { data, error, response } = await publicApi().POST("/auth/login", { body: { email, password } });
    if (!response.ok || !data) {
      const apiError = ApiError.from(response.status, error);
      if (apiError.code === "invalid_credentials") {
        return { error: "Wrong email or password.", values: { email } };
      }
      return failure(apiError, { email });
    }
    await startSession(data.data as Session);
  } catch (error) {
    return failure(error, { email });
  }

  redirect(safeNext(form.get("next")));
}

export async function register(_: FormState, form: FormData): Promise<FormState> {
  const values = { name: text(form, "name"), email: text(form, "email") };
  const password = String(form.get("password") ?? "");

  if (password !== String(form.get("password_confirmation") ?? "")) {
    return { error: "Please correct the highlighted fields.", fields: { password_confirmation: ["Passwords do not match."] }, values };
  }

  try {
    const { data, error, response } = await publicApi().POST("/auth/register", { body: { ...values, password } });
    if (!response.ok || !data) return failure(ApiError.from(response.status, error), values);
    await startSession(data.data as Session);
  } catch (error) {
    return failure(error, values);
  }

  redirect(safeNext(form.get("next")));
}

export async function logout(): Promise<void> {
  const jar = await cookies();
  const refreshToken = jar.get(REFRESH_COOKIE)?.value;
  if (refreshToken) {
    await publicApi()
      .POST("/auth/logout", { body: { refresh_token: refreshToken } })
      .catch(() => null);
  }
  clearSession(jar);
  redirect("/");
}

export async function updateName(_: FormState, form: FormData): Promise<FormState> {
  const name = text(form, "name");
  try {
    const api = await serverApi();
    const { data, error, response } = await api.PATCH("/users/me", { body: { name } as never });
    if (!response.ok || !data) return failure(ApiError.from(response.status, error), { name });
    writeUser(await cookies(), data.data);
    return { success: "Your name was updated.", values: { name } };
  } catch (error) {
    return failure(error, { name });
  }
}

export async function changePassword(_: FormState, form: FormData): Promise<FormState> {
  const current_password = String(form.get("current_password") ?? "");
  const password = String(form.get("password") ?? "");

  if (password !== String(form.get("password_confirmation") ?? "")) {
    return { error: "Please correct the highlighted fields.", fields: { password_confirmation: ["Passwords do not match."] } };
  }

  try {
    const api = await serverApi();
    const { error, response } = await api.PATCH("/users/me", { body: { password, current_password } as never });
    if (!response.ok) return failure(ApiError.from(response.status, error));
  } catch (error) {
    return failure(error);
  }

  // Changing the password signs out every session, including this one.
  clearSession(await cookies());
  redirect("/login?reason=password-changed&next=/account");
}
