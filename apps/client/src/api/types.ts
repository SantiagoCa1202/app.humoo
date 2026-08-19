export type ApiErrorKind =
  | "aborted"
  | "bad_request"
  | "conflict"
  | "forbidden"
  | "network"
  | "not_found"
  | "rate_limit"
  | "server"
  | "timeout"
  | "unauthorized"
  | "unknown"
  | "validation";

export type ApiFieldErrors = Record<string, string[]>;

export type ApiRequestContext = {
  method: string;
  path: string;
  url: string;
};

export type ApiErrorInit = {
  cause?: unknown;
  code?: string;
  context: ApiRequestContext;
  details?: unknown;
  fieldErrors?: ApiFieldErrors;
  kind: ApiErrorKind;
  message: string;
  requestId?: string;
  retryAfter?: number | null;
  status: number;
};

export class ApiError extends Error {
  cause?: unknown;
  code?: string;
  context: ApiRequestContext;
  details?: unknown;
  fieldErrors?: ApiFieldErrors;
  kind: ApiErrorKind;
  requestId?: string;
  retryAfter?: number | null;
  status: number;

  constructor(init: ApiErrorInit) {
    super(init.message);
    this.name = "ApiError";
    this.cause = init.cause;
    this.code = init.code;
    this.context = init.context;
    this.details = init.details;
    this.fieldErrors = init.fieldErrors;
    this.kind = init.kind;
    this.requestId = init.requestId;
    this.retryAfter = init.retryAfter ?? null;
    this.status = init.status;
  }
}

export function isApiError(value: unknown): value is ApiError {
  return value instanceof ApiError;
}
