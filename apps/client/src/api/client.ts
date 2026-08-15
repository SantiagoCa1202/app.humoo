import i18n from "@/i18n";
import { runtimeConfig } from "@/config/runtime";
import type { ApiError } from "@/api/types";

type RequestOptions = RequestInit & {
  timeoutMs?: number;
  authToken?: string | null;
  workspaceId?: string | null;
};

const DEFAULT_TIMEOUT_MS = 12000;

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {}
): Promise<T> {
  if (!runtimeConfig.apiUrl) {
    throw {
      message: "API is not configured in this environment.",
      code: "API_NOT_CONFIGURED",
      status: 503,
    } satisfies ApiError;
  }

  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options.timeoutMs ?? DEFAULT_TIMEOUT_MS
  );

  try {
    const headers = new Headers(options.headers);

    headers.set("Accept", "application/json");
    headers.set("Accept-Language", i18n.language);

    if (!(options.body instanceof FormData) && !headers.has("Content-Type")) {
      headers.set("Content-Type", "application/json");
    }

    if (options.authToken) {
      headers.set("Authorization", `Bearer ${options.authToken}`);
    }

    if (options.workspaceId) {
      headers.set("X-Workspace-ID", options.workspaceId);
    }

    const response = await fetch(`${runtimeConfig.apiUrl}${path}`, {
      ...options,
      signal: controller.signal,
      headers,
    });

    const payload = await parsePayload(response);

    if (!response.ok) {
      throw normalizeApiError(response.status, payload, response.headers);
    }

    return payload as T;
  } catch (error) {
    if ((error as Error).name === "AbortError") {
      throw {
        message: "The request timed out.",
        code: "REQUEST_TIMEOUT",
        status: 408,
      } satisfies ApiError;
    }

    throw error;
  } finally {
    clearTimeout(timeout);
  }
}

function normalizeApiError(
  status: number,
  payload: unknown,
  headers: Headers
): ApiError {
  if (payload && typeof payload === "object") {
    const record = payload as Record<string, unknown>;

    return {
      message:
        typeof record.message === "string"
          ? record.message
          : "Request failed.",
      code: typeof record.code === "string" ? record.code : undefined,
      fieldErrors:
        typeof record.errors === "object" && record.errors
          ? (record.errors as Record<string, string[]>)
          : undefined,
      requestId: headers.get("X-Request-Id") ?? undefined,
      status,
    };
  }

  if (typeof payload === "string" && payload.trim()) {
    return {
      message: payload,
      status,
      requestId: headers.get("X-Request-Id") ?? undefined,
    };
  }

  return {
    message: "Request failed.",
    status,
    requestId: headers.get("X-Request-Id") ?? undefined,
  };
}

async function parsePayload(response: Response): Promise<unknown> {
  const text = await response.text();

  if (!text) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}
