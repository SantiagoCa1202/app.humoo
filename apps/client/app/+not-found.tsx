import { router } from "expo-router";
import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EmptyState } from "@/components/patterns/empty-state";
import { useRouteAccessState, resolveBootstrapHref } from "@/navigation/route-access";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

export default function NotFoundRoute() {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const accessState = useRouteAccessState();
  const safeRoute =
    accessState.sessionStatus === "authenticated"
      ? resolveBootstrapHref(accessState)
      : routes.public.welcome;

  return (
    <View
      style={{
        alignItems: "center",
        backgroundColor: theme.colors.background.app,
        flex: 1,
        justifyContent: "center",
        padding: theme.spacing[6],
      }}
    >
      <EmptyState
        accessibilityLabel={t("routing.notFound.accessibilityLabel")}
        actionLabel={t("routing.notFound.goHome")}
        description={t("routing.notFound.description")}
        onAction={() => router.replace(safeRoute)}
        title={t("routing.notFound.title")}
      />
    </View>
  );
}
