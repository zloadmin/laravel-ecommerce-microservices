import type { Metadata } from "next";
import { unwrap } from "@/lib/api/errors";
import { serverApi } from "@/lib/api/server";
import type { User } from "@/lib/api/types";
import { formatDate } from "@/lib/format";
import { NameForm, PasswordForm } from "@/components/auth-forms";
import { Badge, Card, PageHeader } from "@/components/ui/misc";

export const metadata: Metadata = { title: "Account" };

export default async function AccountPage() {
  const api = await serverApi();
  const user = ((await unwrap(api.GET("/users/me"))) as { data: User }).data;

  return (
    <div className="mx-auto max-w-2xl">
      <PageHeader title="Account" description={`Member since ${formatDate(user.created_at)}`} />
      <div className="space-y-6">
        <Card className="p-6">
          <div className="mb-5 flex items-center justify-between">
            <div>
              <h2 className="font-semibold text-fg">Profile</h2>
              <p className="text-sm text-fg-muted">{user.email}</p>
            </div>
            {user.role === "admin" && <Badge tone="info">Admin</Badge>}
          </div>
          <NameForm name={user.name} />
        </Card>
        <Card className="p-6">
          <h2 className="mb-5 font-semibold text-fg">Password</h2>
          <PasswordForm />
        </Card>
      </div>
    </div>
  );
}
