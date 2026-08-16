import { router, usePathname, type Href } from "expo-router";
import { Pressable, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AlertMessage } from "@/components/patterns/AlertMessage";
import { AppLogo } from "@/components/patterns/AppLogo";
import { AppButton } from "@/components/primitives/AppButton";
import { AppText } from "@/components/primitives/AppText";
import { Card } from "@/components/patterns/Card";
import { Screen } from "@/components/patterns/Screen";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppShellProps = {
  title: string;
  subtitle: string;
  children: React.ReactNode;
};

const navItems: Array<{ labelKey: string; href: Href }> = [
  { labelKey: "chatTitle", href: "/(app)/chat" },
  { labelKey: "operationsTitle", href: "/(app)/operations" },
  { labelKey: "settingsTitle", href: "/(app)/settings" },
  { labelKey: "profileTitle", href: "/(app)/profile" },
];

export function AppShell({ title, subtitle, children }: AppShellProps) {
  const { width } = useWindowDimensions();
  const { theme } = useAppTheme();
  const { t } = useTranslation(["app", "common"]);
  const pathname = usePathname();
  const { session, signOut } = useAuth();
  const isDesktop = width >= 960;
  const activeWorkspace = session?.currentWorkspace;

  const navigation = (
    <Card
      style={{
        backgroundColor: theme.components.navigation.sidebar.background,
        borderColor: theme.components.navigation.sidebar.border,
        gap: 14,
        minWidth: isDesktop ? theme.layout.sidebarWidth : undefined,
      }}
    >
      <AppLogo width={152} />
      <AppText
        variant="caption"
        style={{ color: theme.components.navigation.sidebar.text }}
      >
        {session?.user.name}
      </AppText>
      <AppText
        variant="caption"
        style={{ color: theme.components.navigation.sidebar.text }}
      >
        {t("workspace")}: {activeWorkspace?.name ?? t("workspacePending")}
      </AppText>
      <View style={{ gap: 8, marginTop: 8 }}>
        {navItems.map((item) => {
          const active = pathname === item.href;

          return (
            <Pressable
              key={item.labelKey}
              onPress={() => router.push(item.href)}
              style={{
                backgroundColor: active
                  ? theme.components.navigation.sidebarItem.activeBackground
                  : theme.components.navigation.sidebarItem.background,
                borderColor: active
                  ? theme.components.navigation.sidebarItem.activeBorder
                  : theme.components.navigation.sidebarItem.border,
                borderRadius: theme.radius.pill,
                borderWidth: 1,
                paddingHorizontal: 14,
                paddingVertical: 12,
              }}
            >
              <AppText
                variant="subtitle"
                style={{
                  color: active
                    ? theme.components.navigation.sidebarItem.activeText
                    : theme.components.navigation.sidebarItem.text,
                }}
              >
                {t(item.labelKey)}
              </AppText>
            </Pressable>
          );
        })}
      </View>
      <AppButton
        label={t("signOut")}
        onPress={() => signOut().then(() => router.replace("/(public)/welcome"))}
        variant="secondary"
      />
    </Card>
  );

  return (
    <Screen>
      <View style={{ gap: 18 }}>
        {session?.mode === "local-fallback" ? (
          <AlertMessage message={t("common:localModeBanner")} />
        ) : null}
        <View style={{ gap: 8 }}>
          <AppText
            variant="overline"
            style={{ color: theme.colors.brand.primary }}
          >
            Kitchen operations
          </AppText>
          <AppText variant="hero">
            {title}
          </AppText>
          <AppText muted variant="bodyLarge">
            {subtitle}
          </AppText>
        </View>
        <View
          style={{
            flexDirection: isDesktop ? "row" : "column",
            gap: 18,
          }}
        >
          {navigation}
          <View style={{ flex: 1, gap: 18 }}>{children}</View>
        </View>
      </View>
    </Screen>
  );
}
