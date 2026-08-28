import { buildApiUrl, type ApiQueryParams } from "@/api/config";
import { ApiError, type ApiFieldErrors, type ApiRequestContext } from "@/api/types";
import { getAuthCredential, hydrateAuthCredential, notifySessionExpired } from "@/auth/auth-transport";
import { runtimeConfig } from "@/config/runtime";
import i18n from "@/i18n";
import { canWriteRemotely } from "@/network/network-state";

type RequestOptions = Omit<RequestInit, "signal"> & {
  authToken?: string | null;
  idempotencyHeaderName?: string | null;
  idempotencyKey?: string | null;
  query?: ApiQueryParams;
  signal?: AbortSignal | null;
  timeoutMs?: number;
  workspaceId?: string | null;
};

const DEFAULT_TIMEOUT_MS = 12000;

export async function apiRequest<T>(
  path: string,
  options: RequestOptions = {}
): Promise<T> {
  const method = (options.method ?? "GET").toUpperCase();
  const url = buildApiUrl(path, options.query);
  const context = buildRequestContext(method, path, url);

  if (!runtimeConfig.apiUrl) {
    throw new ApiError({
      code: "API_NOT_CONFIGURED",
      context,
      kind: "server",
      message: i18n.t("network.errors.apiNotConfigured"),
      status: 503,
    });
  }

  if (!["GET", "HEAD", "OPTIONS"].includes(method) && !canWriteRemotely()) {
    throw new ApiError({
      code: "NETWORK_OFFLINE",
      context,
      kind: "network",
      message: i18n.t("network.errors.offline"),
      status: 0,
    });
  }

  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options.timeoutMs ?? DEFAULT_TIMEOUT_MS
  );
  const cleanupAbort = bindAbortSignal(options.signal, controller);

  try {
    await hydrateAuthCredential();

    const headers = new Headers(options.headers);
    const transportCredential =
      options.authToken === undefined ? getAuthCredential() : null;
    const authToken = options.authToken ?? transportCredential?.token ?? null;
    const idempotencyHeaderName =
      options.idempotencyHeaderName ?? runtimeConfig.apiIdempotencyHeaderName;

    headers.set("Accept", "application/json");
    headers.set("Accept-Language", i18n.language);

    if (!(options.body instanceof FormData)) {
      const contentType = headers.get("Content-Type");

      if (!contentType) {
        headers.set("Content-Type", "application/json; charset=UTF-8");
      } else if (
        contentType.toLowerCase().startsWith("application/json") &&
        !contentType.toLowerCase().includes("charset=")
      ) {
        headers.set("Content-Type", `${contentType}; charset=UTF-8`);
      }
    }

    if (authToken) {
      headers.set("Authorization", `Bearer ${authToken}`);
    }

    if (options.workspaceId) {
      headers.set("X-Workspace-ID", options.workspaceId);
    }

    if (idempotencyHeaderName && options.idempotencyKey) {
      headers.set(idempotencyHeaderName, options.idempotencyKey);
    }

    const response = await fetch(url, {
      ...options,
      headers,
      signal: controller.signal,
    });
    const payload = await parsePayload(response);

    if (!response.ok) {
      const error = normalizeApiError(
        response.status,
        payload,
        response.headers,
        context
      );

      if (error.status === 401 && authToken) {
        notifySessionExpired(context);
      }

      throw error;
    }

    return payload as T;
  } catch (error) {
    if ((error as Error).name === "AbortError") {
      if (options.signal?.aborted) {
        throw new ApiError({
          code: "REQUEST_ABORTED",
          context,
          cause: error,
          kind: "aborted",
          message: i18n.t("network.errors.aborted"),
          status: 499,
        });
      }

      throw new ApiError({
        code: "REQUEST_TIMEOUT",
        context,
        cause: error,
        kind: "timeout",
        message: i18n.t("network.errors.timeout"),
        status: 408,
      });
    }

    if (error instanceof ApiError) {
      throw error;
    }

    throw new ApiError({
      code: "NETWORK_ERROR",
      context,
      cause: error,
      kind: "network",
      message: i18n.t("network.errors.offline"),
      status: 0,
    });
  } finally {
    cleanupAbort();
    clearTimeout(timeout);
  }
}

function normalizeApiError(
  status: number,
  payload: unknown,
  headers: Headers,
  context: ApiRequestContext
): ApiError {
  if (payload && typeof payload === "object") {
    const record = payload as Record<string, unknown>;

    return new ApiError({
      code: typeof record.code === "string" ? record.code : undefined,
      context,
      details: record.data ?? payload,
      fieldErrors: normalizeFieldErrors(record.errors),
      kind: mapStatusToKind(status),
      message: resolveErrorMessage(record, status),
      requestId: headers.get("X-Request-Id") ?? undefined,
      retryAfter: parseRetryAfter(headers.get("Retry-After")),
      status,
    });
  }

  if (typeof payload === "string" && payload.trim()) {
    return new ApiError({
      context,
      kind: mapStatusToKind(status),
      message: payload,
      requestId: headers.get("X-Request-Id") ?? undefined,
      retryAfter: parseRetryAfter(headers.get("Retry-After")),
      status,
    });
  }

  return new ApiError({
    context,
    kind: mapStatusToKind(status),
    message: fallbackMessageForStatus(status),
    requestId: headers.get("X-Request-Id") ?? undefined,
    retryAfter: parseRetryAfter(headers.get("Retry-After")),
    status,
  });
}

async function parsePayload(response: Response): Promise<unknown> {
  if (response.status === 204 || response.status === 205) {
    return null;
  }

  const text = await response.text();

  if (!text.trim()) {
    return null;
  }

  try {
    return JSON.parse(text);
  } catch {
    return text;
  }
}

function buildRequestContext(
  method: string,
  path: string,
  url: string
): ApiRequestContext {
  return {
    method,
    path,
    url,
  };
}

function bindAbortSignal(
  signal: AbortSignal | null | undefined,
  controller: AbortController
) {
  if (!signal) {
    return () => undefined;
  }

  if (signal.aborted) {
    controller.abort();
    return () => undefined;
  }

  const handleAbort = () => controller.abort();
  signal.addEventListener("abort", handleAbort);

  return () => signal.removeEventListener("abort", handleAbort);
}

function normalizeFieldErrors(value: unknown): ApiFieldErrors | undefined {
  if (!value || typeof value !== "object") {
    return undefined;
  }

  const nextErrors: ApiFieldErrors = {};

  for (const [key, entry] of Object.entries(value)) {
    if (!Array.isArray(entry)) {
      continue;
    }

    const messages = entry.filter(
      (item): item is string => typeof item === "string" && item.trim().length > 0
    );

    if (messages.length > 0) {
      nextErrors[key] = messages;
    }
  }

  return Object.keys(nextErrors).length > 0 ? nextErrors : undefined;
}

function resolveErrorMessage(
  payload: Record<string, unknown>,
  status: number
): string {
  if (typeof payload.message === "string" && payload.message.trim()) {
    return payload.message;
  }

  return fallbackMessageForStatus(status);
}

function fallbackMessageForStatus(status: number): string {
  switch (status) {
    case 401:
      return i18n.t("network.errors.unauthorized");
    case 403:
      return i18n.t("network.errors.forbidden");
    case 404:
      return i18n.t("network.errors.notFound");
    case 409:
      return i18n.t("network.errors.conflict");
    case 422:
      return i18n.t("network.errors.validation");
    case 429:
      return i18n.t("network.errors.rateLimited");
    default:
      if (status >= 500) {
        return i18n.t("network.errors.server");
      }

      return i18n.t("network.errors.unknown");
    }
}

function mapStatusToKind(status: number) {
  switch (status) {
    case 400:
      return "bad_request" as const;
    case 401:
      return "unauthorized" as const;
    case 403:
      return "forbidden" as const;
    case 404:
      return "not_found" as const;
    case 409:
      return "conflict" as const;
    case 422:
      return "validation" as const;
    case 429:
      return "rate_limit" as const;
    default:
      return status >= 500 ? ("server" as const) : ("unknown" as const);
  }
}

function parseRetryAfter(value: string | null): number | null {
  if (!value) {
    return null;
  }

  const seconds = Number(value);

  return Number.isFinite(seconds) ? seconds : null;
}
