import "server-only";

/** Base URL of the API gateway, reachable from the Next.js server (never from the browser). */
export const GATEWAY_URL = (process.env.GATEWAY_URL ?? "http://localhost:8080").replace(/\/+$/, "");

export const API_BASE = `${GATEWAY_URL}/api/v1`;
