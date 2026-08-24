import { Feather } from "@expo/vector-icons";
import { router, usePathname } from "expo-router";
import { useEffect } from "react";
import { Platform, Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AppLogo } from "@/components/patterns/AppLogo";
import { AppText } from "@/components/primitives/AppText";
import { ChoiceChip } from "@/components/primitives/ChoiceChip";
import {
  useChatHistory,
  useChatSelection,
} from "@/features/chat/hooks";
import { useNotificationUnreadCount } from "@/features/notifications/hooks";
import {
  getNavigationItemByPath,
  getNavigationItems,
} from "@/navigation/app-navigation";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppShellProps = {
  fillContent?: boolean;
  title: string;
  subtitle: string;
  children: React.ReactNode;
};

const navItems = getNavigationItems("primary");

function chatHistoryLabel(title: string | null | undefined) {
  const normalized = title?.trim() || "Humoo AI";

  return normalized.length > 30
    ? `${normalized.slice(0, 30).trimEnd()}...`
    : normalized;
}

export function AppShell({
  children,
  fillContent = false,
  subtitle,
  title,
}: AppShellProps) {
  const { width } = useWindowDimensions();
  const { theme } = useAppTheme();
  const { t } = useTranslation(["app", "common"]);
  const pathname = usePathname();

  const { session, signOut } = useAuth();
  const chatHistoryQuery = useChatHistory();
  const chatSelection = useChatSelection();
  const unreadNotificationsQuery = useNotificationUnreadCount();
  const activeNavigationItem = getNavigationItemByPath(pathname);

  const isDesktop = width >= theme.breakpoints.lg;

  const activeWorkspace = session?.currentWorkspace;

  const userName = session?.user.name ?? "";
  const userEmail = session?.user.email ?? "";

  const initials = userName
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join("");

  const handleSignOut = async () => {
    await signOut();
    router.replace("/(public)/login");
  };

  const handleNavigation = (item: (typeof navItems)[number]) => {
    router.push(item.href);
  };

  useEffect(() => {
    if (Platform.OS !== "web") {
      return;
    }

    const handleShortcut = (event: globalThis.KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
        event.preventDefault();

        if (pathname !== routes.app.search) {
          router.push(routes.app.search);
        }
      }
    };

    window.addEventListener("keydown", handleShortcut);

    return () => window.removeEventListener("keydown", handleShortcut);
  }, [pathname]);

  const renderSearchTrigger = () => (
    <Pressable
      accessibilityLabel={t("globalSearchTrigger")}
      accessibilityRole="button"
      onPress={() => router.push(routes.app.search)}
      style={({ pressed }) => ({
        alignItems: "center",
        borderColor: theme.components.navigation.sidebar.border,
        borderRadius: theme.radius.md,
        borderWidth: 1,
        flexDirection: "row",
        gap: theme.spacing[2],
        opacity: pressed ? 0.72 : 1,
        paddingHorizontal: theme.spacing[3],
        paddingVertical: theme.spacing[2],
      })}
    >
      <Feather color={theme.colors.text.secondary} name="search" size={17} />
      <AppText muted variant="bodySmall">
        {t("globalSearchTrigger")}
      </AppText>
      <AppText muted variant="caption">
        {t("globalSearchShortcut")}
      </AppText>
    </Pressable>
  );

  const renderNavItem = (item: (typeof navItems)[number]) => {
    const active = activeNavigationItem.id === item.id;

    const textColor = active
      ? theme.colors.text.primary
      : theme.colors.text.secondary;

    return (
      <View key={item.id} style={{ gap: theme.spacing[1] }}>
        <Pressable
          accessibilityLabel={t(item.accessibilityKey)}
          accessibilityRole="button"
          onPress={() => handleNavigation(item)}
          style={({ pressed }) => ({
            alignItems: "center",

            backgroundColor: active
              ? theme.components.navigation.sidebarItem.activeBackground
              : pressed
                ? theme.components.navigation.sidebarItem.activeBackground
                : "transparent",

            borderCurve: "continuous",
            borderRadius: theme.radius.md,

            flexDirection: "row",

            gap: theme.spacing[3],

            paddingHorizontal: theme.spacing[3],
            paddingVertical: theme.spacing[3],
          })}
        >
          <Feather
            color={textColor}
            name={item.icon as keyof typeof Feather.glyphMap}
            size={18}
          />

          <AppText
            variant="bodyMedium"
            style={{
              color: textColor,
              flex: 1,
            }}
          >
            {t(item.titleKey)}
          </AppText>

          {item.id === "notifications" && unreadNotificationsQuery.data ? (
            <View
              style={{
                alignItems: "center",
                backgroundColor: theme.colors.brand.primary,
                borderRadius: 999,
                minWidth: 22,
                paddingHorizontal: theme.spacing[1],
                paddingVertical: 2,
              }}
            >
              <AppText
                variant="caption"
                style={{
                  color: theme.colors.text.inverse,
                  fontVariant: ["tabular-nums"],
                  fontWeight: "700",
                }}
              >
                {unreadNotificationsQuery.data > 99 ? "99+" : unreadNotificationsQuery.data}
              </AppText>
            </View>
          ) : null}
        </Pressable>

        {item.id === "chat" && chatHistoryQuery.data?.length ? (
          <View
            style={{
              gap: theme.spacing[1],
              marginLeft: theme.spacing[6],
            }}
          >
            {chatHistoryQuery.data.map((conversation) => (
              <ChoiceChip
                active={conversation.id === chatSelection.activeConversationId}
                accessibilityState={{
                  selected: conversation.id === chatSelection.activeConversationId,
                }}
                key={conversation.id}
                label={chatHistoryLabel(
                  conversation.title ?? conversation.preview,
                )}
                onPress={() => {
                  chatSelection.selectConversation(conversation.id);
                  handleNavigation(item);
                }}
              />
            ))}
          </View>
        ) : null}
      </View>
    );
  };

  const sidebar = (
    <View
      style={{
        backgroundColor: theme.components.navigation.sidebar.background,

        borderColor: theme.components.navigation.sidebar.border,

        borderRightWidth: isDesktop ? 1 : 0,

        flex: 1,

        paddingHorizontal: theme.spacing[2],
        paddingVertical: theme.spacing[3],
      }}
    >
      {/* =====================================================
          LOGO
      ===================================================== */}

      <View
        style={{
          paddingHorizontal: theme.spacing[2],
          paddingVertical: theme.spacing[2],
        }}
      >
        <AppLogo width={145} />
      </View>

      {/* =====================================================
          WORKSPACE SELECTOR
      ===================================================== */}

      <Pressable
        accessibilityRole="button"
        style={({ pressed }) => ({
          alignItems: "center",

          borderColor: theme.components.navigation.sidebar.border,

          borderRadius: theme.radius.md,
          borderWidth: 1,

          flexDirection: "row",

          gap: theme.spacing[2],

          marginBottom: theme.spacing[4],
          marginTop: theme.spacing[2],

          opacity: pressed ? 0.75 : 1,

          paddingHorizontal: theme.spacing[3],
          paddingVertical: theme.spacing[3],
        })}
      >
        <Feather color={theme.colors.text.primary} name="home" size={17} />

        <AppText
          numberOfLines={1}
          variant="bodyMedium"
          style={{
            flex: 1,
          }}
        >
          {activeWorkspace?.name ?? t("workspacePending")}
        </AppText>

        <Feather
          color={theme.colors.text.primary}
          name="chevron-down"
          size={16}
        />
      </Pressable>

      {/* =====================================================
          NAVIGATION
      ===================================================== */}

      <ScrollView
        contentContainerStyle={{
          gap: theme.spacing[1],
          paddingBottom: theme.spacing[4],
        }}
        showsVerticalScrollIndicator={false}
        style={{
          flex: 1,
        }}
      >
        {navItems.map(renderNavItem)}
      </ScrollView>

      {/* =====================================================
          USER
      ===================================================== */}

      <View
        style={{
          borderColor: theme.components.navigation.sidebar.border,

          borderTopWidth: 1,

          paddingTop: theme.spacing[3],
        }}
      >
        <Pressable
          accessibilityRole="button"
          onPress={() => router.push("/(app)/profile")}
          style={({ pressed }) => ({
            alignItems: "center",

            borderCurve: "continuous",
            borderRadius: theme.radius.md,

            flexDirection: "row",

            gap: theme.spacing[3],

            opacity: pressed ? 0.7 : 1,

            paddingHorizontal: theme.spacing[2],
            paddingVertical: theme.spacing[2],
          })}
        >
          {/* Avatar */}

          <View
            style={{
              alignItems: "center",

              backgroundColor: theme.colors.brand.primary,

              borderRadius: 999,

              height: 38,

              justifyContent: "center",

              width: 38,
            }}
          >
            <AppText
              variant="caption"
              style={{
                color: theme.colors.text.inverse,
                fontWeight: "700",
              }}
            >
              {initials}
            </AppText>
          </View>

          {/* User info */}

          <View
            style={{
              flex: 1,
              minWidth: 0,
            }}
          >
            <AppText numberOfLines={1} variant="bodyMedium" style={{}}>
              {userName}
            </AppText>

            <AppText muted numberOfLines={1} variant="caption">
              {userEmail}
            </AppText>
          </View>

          <Feather
            color={theme.colors.text.primary}
            name="chevron-down"
            size={16}
          />
        </Pressable>

        {/* Footer actions */}

        <View
          style={{
            gap: theme.spacing[1],
            marginTop: theme.spacing[2],
          }}
        >
          <Pressable
            accessibilityRole="button"
            onPress={() => router.push("/(app)/settings")}
            style={({ pressed }) => ({
              alignItems: "center",

              borderRadius: theme.radius.md,

              flexDirection: "row",

              gap: theme.spacing[3],

              opacity: pressed ? 0.7 : 1,

              paddingHorizontal: theme.spacing[3],
              paddingVertical: theme.spacing[2],
            })}
          >
            <Feather
              color={theme.colors.text.primary}
              name="settings"
              size={17}
            />

            <AppText variant="bodyMedium" style={{}}>
              {t("settingsTitle")}
            </AppText>
          </Pressable>

          <Pressable
            accessibilityRole="button"
            onPress={handleSignOut}
            style={({ pressed }) => ({
              alignItems: "center",

              borderRadius: theme.radius.md,

              flexDirection: "row",

              gap: theme.spacing[3],

              opacity: pressed ? 0.7 : 1,

              paddingHorizontal: theme.spacing[3],
              paddingVertical: theme.spacing[2],
            })}
          >
            <Feather
              color={theme.colors.text.primary}
              name="log-out"
              size={17}
            />

            <AppText variant="bodyMedium" style={{}}>
              {t("signOut")}
            </AppText>
          </Pressable>
        </View>
      </View>
    </View>
  );

  /* =========================================================
     DESKTOP
  ========================================================= */

  if (isDesktop) {
    return (
      <View
        style={{
          backgroundColor: theme.colors.background.surface,

          flex: 1,
          flexDirection: "row",
        }}
      >
        {/* SIDEBAR */}

        <View
          style={{
            flexShrink: 0,
            width: theme.layout.sidebarWidth,
          }}
        >
          {sidebar}
        </View>

        {/* MAIN */}

        <View
          style={{
            backgroundColor: theme.colors.background.app,

            flex: 1,
            minWidth: 0,
          }}
        >
          <ScrollView
            scrollEnabled={!fillContent}
            contentContainerStyle={{
              flexGrow: 1,
              paddingHorizontal: theme.spacing[6],
              paddingVertical: theme.spacing[5],
            }}
            style={{ flex: 1 }}
          >
            <View
              style={{
                alignSelf: "center",

                flex: fillContent ? 1 : undefined,

                gap: theme.spacing[5],

                maxWidth: theme.layout.content.maxWidth,

                width: "100%",
              }}
            >
              {/* HEADER */}

              <View
                style={{
                  alignItems: "flex-start",
                  flexDirection: "row",
                  gap: theme.spacing[2],
                  justifyContent: "space-between",
                }}
              >
                <View style={{ flex: 1, gap: theme.spacing[2], minWidth: 0 }}>
                  <AppText variant="hero">{title}</AppText>

                  <AppText muted variant="bodyMedium">
                    {subtitle}
                  </AppText>
                </View>

                {renderSearchTrigger()}
              </View>

              {/* CONTENT */}

              <View
                style={{
                  flex: 1,
                  minWidth: 0,
                }}
              >
                {children}
              </View>
            </View>
          </ScrollView>
        </View>
      </View>
    );
  }

  /* =========================================================
     MOBILE
  ========================================================= */

  return (
    <ScrollView
      scrollEnabled={!fillContent}
      contentContainerStyle={{
        backgroundColor: theme.colors.background.app,
        flexGrow: 1,
      }}
      style={{ flex: 1 }}
    >
      {/* Por ahora mantenemos navegación visible arriba.
          Después podemos convertir esto en drawer/hamburger. */}

      <View style={fillContent ? { flex: 1 } : undefined}>
        <View
          style={{
            minHeight: 0,
          }}
        >
          {sidebar}
        </View>

        <View
          style={{
            ...(fillContent ? { flex: 1, minHeight: 0 } : {}),

            gap: theme.spacing[4],

            paddingHorizontal: theme.spacing[4],
            paddingVertical: theme.spacing[5],
          }}
        >
          <View
            style={{
              alignItems: "flex-start",
              flexDirection: "row",
              gap: theme.spacing[2],
              justifyContent: "space-between",
            }}
          >
            <View style={{ flex: 1, gap: theme.spacing[2], minWidth: 0 }}>
              <AppText variant="hero">{title}</AppText>

              <AppText muted variant="bodyMedium">
                {subtitle}
              </AppText>
            </View>

            {renderSearchTrigger()}
          </View>

          <View style={fillContent ? { flex: 1, minHeight: 0 } : undefined}>
            {children}
          </View>
        </View>
      </View>
    </ScrollView>
  );
}
