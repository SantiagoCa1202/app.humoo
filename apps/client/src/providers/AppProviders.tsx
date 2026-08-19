import "@/i18n";

import { useEffect } from "react";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useFonts } from "expo-font";
import {
  Manrope_600SemiBold,
  Manrope_700Bold,
} from "@expo-google-fonts/manrope";
import {
  PlusJakartaSans_400Regular,
  PlusJakartaSans_500Medium,
  PlusJakartaSans_600SemiBold,
} from "@expo-google-fonts/plus-jakarta-sans";
import { ActivityIndicator, Image, View, useColorScheme } from "react-native";

import { isApiError } from "@/api/types";
import { AuthProvider } from "@/auth/AuthProvider";
import { hydrateStoredLanguage } from "@/i18n";
import { humooAssets } from "@/theme/brand";
import { spacing } from "@/theme";
import { ThemeProvider } from "@/theme/ThemeProvider";
import { resolveTheme } from "@/theme/tokens";

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      refetchOnWindowFocus: false,
      retry(failureCount, error) {
        if (
          isApiError(error) &&
          [401, 403, 404, 409, 422].includes(error.status)
        ) {
          return false;
        }

        return failureCount < 1;
      },
    },
    mutations: {
      retry: false,
    },
  },
});

export function AppProviders({ children }: { children: React.ReactNode }) {
  const systemScheme = useColorScheme();
  const normalizedSystemScheme =
    systemScheme === "dark" || systemScheme === "light" ? systemScheme : null;
  const [fontsLoaded] = useFonts({
    Manrope_600SemiBold,
    Manrope_700Bold,
    PlusJakartaSans_400Regular,
    PlusJakartaSans_500Medium,
    PlusJakartaSans_600SemiBold,
  });

  useEffect(() => {
    hydrateStoredLanguage();
  }, []);

  if (!fontsLoaded) {
    const splashTheme = resolveTheme("system", normalizedSystemScheme);

    return (
      <View
        style={{
          alignItems: "center",
          backgroundColor: splashTheme.colors.background.app,
          flex: 1,
          gap: spacing[4],
          justifyContent: "center",
        }}
      >
        <Image
          resizeMode="contain"
          source={splashTheme.isDark ? humooAssets.logoDark : humooAssets.logoLight}
          style={{ height: 54, width: 180 }}
        />
        <ActivityIndicator
          color={splashTheme.colors.brand.primary}
          size="large"
        />
      </View>
    );
  }

  return (
    <QueryClientProvider client={queryClient}>
      <ThemeProvider>
        <AuthProvider>{children}</AuthProvider>
      </ThemeProvider>
    </QueryClientProvider>
  );
}
