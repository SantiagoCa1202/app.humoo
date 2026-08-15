export const runtimeConfig = {
  apiUrl: (process.env.EXPO_PUBLIC_API_URL ?? "").trim(),
  appEnv: (process.env.EXPO_PUBLIC_APP_ENV ?? "development").trim(),
  enableLocalAuthFallback:
    (process.env.EXPO_PUBLIC_ENABLE_LOCAL_AUTH_FALLBACK ?? "false").trim() !==
    "false",
};

export const isApiConfigured = runtimeConfig.apiUrl.length > 0;
