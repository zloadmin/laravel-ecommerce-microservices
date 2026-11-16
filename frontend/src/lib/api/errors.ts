export type FieldErrors = Record<string, string[]>;

/** An error response from the API, in its `{"error": {"code", "message", "details"}}` shape. */
export class ApiError extends Error {
  constructor(
    public readonly status: number,
    public readonly code: string,
    message: string,
    public readonly details: Record<string, unknown> = {},
  ) {
    super(message);
    this.name = "ApiError";
  }

  get fields(): FieldErrors {
    return (this.details.fields as FieldErrors | undefined) ?? {};
  }

  /** Builds an ApiError from a parsed error body, tolerating bodies that are not in the API's format. */
  static from(status: number, body: unknown): ApiError {
    const error = (body as { error?: { code?: unknown; message?: unknown; details?: unknown } } | null)?.error;
    if (error && typeof error.code === "string") {
      return new ApiError(
        status,
        error.code,
        typeof error.message === "string" ? error.message : error.code,
        (error.details as Record<string, unknown> | undefined) ?? {},
      );
    }
    return new ApiError(status, status >= 500 ? "service_unavailable" : "unknown_error", `Request failed (${status}).`);
  }

  static async fromResponse(response: Response): Promise<ApiError> {
    const body = await response.json().catch(() => null);
    return ApiError.from(response.status, body);
  }
}

const FRIENDLY: Record<string, string> = {
  too_many_requests: "Too many requests. Please wait a moment and try again.",
  service_unavailable: "The store is temporarily unavailable. Please try again shortly.",
  unauthenticated: "Your session has expired. Please sign in again.",
  forbidden: "You don't have permission to do that.",
  not_found: "We couldn't find what you were looking for.",
};

/** A message that is safe to show to a shopper for any thrown value. */
export function describeError(error: unknown): string {
  if (error instanceof ApiError) {
    return FRIENDLY[error.code] ?? error.message;
  }
  if (error instanceof TypeError) {
    return "Network error. Check your connection and try again.";
  }
  return "Something went wrong. Please try again.";
}

/** Resolves an openapi-fetch result to its data, throwing an ApiError for any non-2xx response. */
export async function unwrap<T>(request: Promise<{ data?: T; error?: unknown; response: Response }>): Promise<T> {
  const { data, error, response } = await request;
  if (!response.ok) {
    throw ApiError.from(response.status, error);
  }
  return data as T;
}
