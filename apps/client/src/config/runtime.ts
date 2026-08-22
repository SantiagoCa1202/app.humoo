const appEnv = (process.env.EXPO_PUBLIC_APP_ENV ?? "development").trim();

export const runtimeConfig = {
  apiUrl: (process.env.EXPO_PUBLIC_API_URL ?? "").trim(),
  apiPathPrefix: (process.env.EXPO_PUBLIC_API_PATH_PREFIX ?? "/api/v1").trim(),
  apiIdempotencyHeaderName: (
    process.env.EXPO_PUBLIC_API_IDEMPOTENCY_HEADER ?? ""
  ).trim(),
  appEnv,
  enableLocalAuthFallback:
    appEnv === "development" &&
    (process.env.EXPO_PUBLIC_ENABLE_LOCAL_AUTH_FALLBACK ?? "false").trim() !==
      "false",
  realtimeAuthUrl: (process.env.EXPO_PUBLIC_REALTIME_AUTH_URL ?? "").trim(),
  realtimeKey: (process.env.EXPO_PUBLIC_REALTIME_KEY ?? "").trim(),
  realtimeUrl: (process.env.EXPO_PUBLIC_REALTIME_URL ?? "").trim(),
};

export const isApiConfigured = runtimeConfig.apiUrl.length > 0;
