import type { ConfigContext, ExpoConfig } from "expo/config";

export default ({ config }: ConfigContext): ExpoConfig => ({
  ...config,
  ios: {
    ...config.ios,
    bundleIdentifier:
      process.env.EXPO_PUBLIC_IOS_BUNDLE_IDENTIFIER ?? "com.humoo.client",
  },
  android: {
    ...config.android,
    package:
      process.env.EXPO_PUBLIC_ANDROID_PACKAGE ?? "com.humoo.client",
  },
  extra: {
    ...config.extra,
    humoo: {
      appEnv: process.env.EXPO_PUBLIC_APP_ENV ?? "development",
      apiUrl: process.env.EXPO_PUBLIC_API_URL ?? "",
      realtimeUrl: process.env.EXPO_PUBLIC_REALTIME_URL ?? "",
    },
  },
});
