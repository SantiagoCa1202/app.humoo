import { useQuery } from "@tanstack/react-query";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { apiRequest } from "@/api/client";
import { AppShell } from "@/components/patterns/AppShell";
import { Card } from "@/components/patterns/Card";
import { LanguageSelector } from "@/components/patterns/LanguageSelector";
import { ThemeToggle } from "@/components/patterns/ThemeToggle";
import { AppText } from "@/components/primitives/AppText";
import { isApiConfigured, runtimeConfig } from "@/config/runtime";

type HealthPayload = {
  data: {
    status: string;
    app: string;
    environment: string;
    php: string;
    database: {
      driver: string;
      connected: boolean;
    };
  };
  meta: {
    request_id: string;
  };
};

export default function SettingsScreen() {
  const { t } = useTranslation("app");
  const healthQuery = useQuery({
    queryKey: ["api-health"],
    queryFn: () => apiRequest<HealthPayload>("/health"),
    enabled: isApiConfigured,
    retry: 1,
  });

  return (
    <AppShell
      title={t("settingsTitle")}
      subtitle="Theme, language, and runtime diagnostics live here."
    >
      <View style={{ gap: 18 }}>
        <Card style={{ gap: 16 }}>
          <LanguageSelector />
          <ThemeToggle />
        </Card>
        <Card style={{ gap: 8 }}>
          <AppText variant="overline">Runtime</AppText>
          <AppText muted>API URL: {runtimeConfig.apiUrl || "not configured"}</AppText>
          <AppText muted>
            Local fallback: {runtimeConfig.enableLocalAuthFallback ? "enabled" : "disabled"}
          </AppText>
          <AppText muted>Environment: {runtimeConfig.appEnv}</AppText>
          {isApiConfigured ? (
            <>
              <AppText muted>
                API health:{" "}
                {healthQuery.isLoading
                  ? "checking"
                  : healthQuery.data?.data.status ?? "unavailable"}
              </AppText>
              <AppText muted>
                API database:{" "}
                {healthQuery.data?.data.database.connected ? "connected" : "not connected"}
              </AppText>
              <AppText muted>
                API PHP: {healthQuery.data?.data.php ?? "unknown"}
              </AppText>
            </>
          ) : null}
        </Card>
      </View>
    </AppShell>
  );
}
