import { router, usePathname } from "expo-router";
import { useEffect, useMemo, useState } from "react";
import { Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AppLogo } from "@/components/patterns/AppLogo";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { WorkspaceSwitcher, useWorkspace } from "@/features/workspace";
import {
  getNavigationItemByPath,
  getNavigationItems,
} from "@/navigation/app-navigation";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppShellProps = {
  children: React.ReactNode;
  subtitle?: string;
  title?: string;
};

export function AppShell({ children, subtitle, title }: AppShellProps) {
  const { width } = useWindowDimensions();
  const { theme } = useAppTheme();
  const { t } = useTranslation(["app", "common"]);
  const pathname = usePathname();
  const { session, signOut } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const [menuOpen, setMenuOpen] = useState(false);
  const isDesktop = width >= theme.breakpoints.lg;
  const primaryNavigationItems = getNavigationItems("primary");
  const secondaryNavigationItems = getNavigationItems("secondary");
  const activeItem = useMemo(() => getNavigationItemByPath(pathname), [pathname]);
  const resolvedTitle = title ?? t(activeItem.id === "chat" ? "chatTitle" : activeItem.id === "operations" ? "operationsTitle" : activeItem.id === "calendar" ? "calendarTitle" : activeItem.id === "settings" ? "settingsTitle" : "profileTitle");
  const resolvedSubtitle =
    subtitle ??
    t(
      activeItem.id === "chat"
        ? "chatSubtitle"
        : activeItem.id === "operations"
        ? "operationsSubtitle"
        : activeItem.id === "calendar"
        ? "calendarSubtitle"
        : activeItem.id === "settings"
        ? "settingsSubtitle"
        : "profileSubtitle"
    );

  useEffect(() => {
    if (!isDesktop) {
      setMenuOpen(false);
    }
  }, [isDesktop, pathname]);

  const navigationItems = (
    items: ReturnType<typeof getNavigationItems>
  ) => (
    <View style={{ gap: theme.spacing[2] }}>
      {items.map((item) => {
        const active = activeItem.id === item.id;

        return (
          <Pressable
            accessibilityLabel={t(item.accessibilityKey)}
            accessibilityRole="button"
            accessibilityState={{ selected: active }}
            key={item.id}
            onPress={() => {
              if (pathname !== item.href) {
                router.push(item.href);
              }
            }}
            style={{
              backgroundColor: active
                ? theme.components.navigation.sidebarItem.activeBackground
                : theme.components.navigation.sidebarItem.background,
              borderColor: active
                ? theme.components.navigation.sidebarItem.activeBorder
                : theme.components.navigation.sidebarItem.border,
              borderCurve: "continuous",
              borderRadius: theme.radius.md,
              borderWidth: 1,
              paddingHorizontal: theme.spacing[3],
              paddingVertical: theme.spacing[3],
            }}
          >
            <Text
              variant="bodyMedium"
              style={{
                color: active
                  ? theme.components.navigation.sidebarItem.activeText
                  : theme.components.navigation.sidebarItem.text,
              }}
            >
              {t(item.titleKey)}
            </Text>
          </Pressable>
        );
      })}
    </View>
  );

  const navigation = (
    <BaseCard
      style={{
        backgroundColor: theme.components.navigation.sidebar.background,
        borderColor: theme.components.navigation.sidebar.border,
        gap: theme.spacing[3],
        minWidth: isDesktop ? theme.layout.sidebarWidth : undefined,
      }}
      variant="outlined"
    >
      <AppLogo width={152} />
      <Text selectable tone="secondary" variant="caption">
        {session?.user.name}
      </Text>
      <Text selectable tone="secondary" variant="caption">
        {t("shell.workspace")}: {activeWorkspace?.name ?? t("workspacePending")}
      </Text>
      <WorkspaceSwitcher />
      <View style={{ gap: theme.spacing[3], marginTop: theme.spacing[2] }}>
        <View style={{ gap: theme.spacing[2] }}>
          <Text tone="secondary" variant="overline">
            {t("shell.primaryNavigation")}
          </Text>
          {navigationItems(primaryNavigationItems)}
        </View>
        <Divider spacing="none" />
        <View style={{ gap: theme.spacing[2] }}>
          <Text tone="secondary" variant="overline">
            {t("shell.secondaryNavigation")}
          </Text>
          {navigationItems(secondaryNavigationItems)}
        </View>
      </View>
      <Button
        label={t("signOut")}
        onPress={() => signOut().then(() => router.replace(routes.public.welcome))}
        size="sm"
        variant="secondary"
      />
    </BaseCard>
  );

  return (
    <View style={{ backgroundColor: theme.colors.background.app, flex: 1 }}>
      <ScrollView
        contentContainerStyle={{
          flexGrow: 1,
          marginHorizontal: "auto",
          maxWidth: theme.layout.content.maxWidth,
          padding: theme.layout.screenPadding,
          width: "100%",
        }}
        contentInsetAdjustmentBehavior="automatic"
      >
        <View style={{ gap: theme.spacing[4] }}>
          <View
            style={{
              alignItems: "center",
              flexDirection: "row",
              justifyContent: "space-between",
            }}
          >
            <View style={{ flex: 1, gap: theme.spacing[2] }}>
              <Text tone="primary" variant="overline">
                {t("shell.kicker")}
              </Text>
              <Text selectable variant="display">
                {resolvedTitle}
              </Text>
              <Text selectable tone="secondary" variant="body">
                {resolvedSubtitle}
              </Text>
            </View>
            {!isDesktop ? (
              <Button
                label={menuOpen ? t("navigation.closeMenu") : t("navigation.openMenu")}
                onPress={() => setMenuOpen((currentValue) => !currentValue)}
                size="sm"
                variant="secondary"
              />
            ) : null}
          </View>
          {!isDesktop && menuOpen ? navigation : null}
          <View
            style={{
              flexDirection: isDesktop ? "row" : "column",
              gap: theme.spacing[4],
            }}
          >
            {isDesktop ? navigation : null}
            <View style={{ flex: 1, gap: theme.spacing[4] }}>{children}</View>
          </View>
        </View>
      </ScrollView>
    </View>
  );
}
