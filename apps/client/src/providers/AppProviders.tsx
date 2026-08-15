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
import { ActivityIndicator, Image, View } from "react-native";

import { AuthProvider } from "@/auth/AuthProvider";
import { hydrateStoredLanguage } from "@/i18n";
import { humooAssets, humooPalette } from "@/theme/brand";
import { ThemeProvider } from "@/theme/ThemeProvider";

const queryClient = new QueryClient();

export function AppProviders({ children }: { children: React.ReactNode }) {
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
    return (
      <View
        style={{
          alignItems: "center",
          backgroundColor: humooPalette.warmCream,
          flex: 1,
          gap: 18,
          justifyContent: "center",
        }}
      >
        <Image
          resizeMode="contain"
          source={humooAssets.logoLight}
          style={{ height: 54, width: 180 }}
        />
        <ActivityIndicator color={humooPalette.emberOrange} size="large" />
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
