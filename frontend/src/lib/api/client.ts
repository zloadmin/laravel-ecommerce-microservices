import createClient from "openapi-fetch";
import type { paths } from "./schema";

/**
 * The browser's API client. Requests go to this app's /api proxy, which adds the visitor's
 * credentials from httpOnly cookies and forwards them to the gateway.
 */
export const api = createClient<paths>({ baseUrl: "/api", headers: { Accept: "application/json" } });
