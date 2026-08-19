import { runtimeConfig } from "@/config/runtime";

export type ApiQueryValue =
  | string
  | number
  | boolean
  | null
  | undefined
  | Array<string | number | boolean | null | undefined>;

export type ApiQueryParams = Record<string, ApiQueryValue>;

export function getApiBaseUrl(): string {
  return runtimeConfig.apiUrl.replace(/\/+$/, "");
}

export function getApiPathPrefix(): string {
  const normalized = runtimeConfig.apiPathPrefix.trim();

  if (!normalized) {
    return "";
  }

  return normalized.startsWith("/") ? normalized : `/${normalized}`;
}

export function buildApiPath(path: string): string {
  const trimmedPath = path.trim();
  const normalizedPath = trimmedPath.startsWith("/")
    ? trimmedPath
    : `/${trimmedPath}`;
  const prefix = getApiPathPrefix();

  if (
    !prefix ||
    normalizedPath === prefix ||
    normalizedPath.startsWith(`${prefix}/`)
  ) {
    return normalizedPath;
  }

  return `${prefix}${normalizedPath}`;
}

export function buildApiUrl(path: string, query?: ApiQueryParams): string {
  const baseUrl = getApiBaseUrl();
  const nextPath = buildApiPath(path);
  const search = serializeQuery(query);

  return `${baseUrl}${nextPath}${search}`;
}

export function serializeQuery(query?: ApiQueryParams): string {
  if (!query) {
    return "";
  }

  const params = new URLSearchParams();

  for (const [key, value] of Object.entries(query)) {
    appendQueryValue(params, key, value);
  }

  const serialized = params.toString();

  return serialized ? `?${serialized}` : "";
}

function appendQueryValue(
  params: URLSearchParams,
  key: string,
  value: ApiQueryValue
) {
  if (value == null) {
    return;
  }

  if (Array.isArray(value)) {
    for (const item of value) {
      appendQueryValue(params, key, item);
    }

    return;
  }

  params.append(key, String(value));
}
