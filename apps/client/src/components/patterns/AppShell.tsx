import { Feather } from "@expo/vector-icons";
import { router, usePathname, type Href } from "expo-router";
import { Pressable, ScrollView, View, useWindowDimensions } from "react-native";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { AppLogo } from "@/components/patterns/AppLogo";
import { AppText } from "@/components/primitives/AppText";
import { useNotificationUnreadCount } from "@/features/notifications/hooks";
import { routes } from "@/navigation/routes";
import { useAppTheme } from "@/theme/ThemeProvider";

type AppShellProps = {
  title: string;
  subtitle: string;
  children: React.ReactNode;
};

type NavItem = {
  labelKey: string;
  icon: keyof typeof Feather.glyphMap;
  href?: Href;
  enabled?: boolean;
};

/*
 * Las rutas que ya existen quedan habilitadas.
 *
 * Las futuras aparecen visualmente para poder construir
 * el sidebar completo sin inventar navegación inexistente.
 */
const navItems: NavItem[] = [
  {
    labelKey: "chatTitle",
    icon: "message-circle",
    href: "/(app)/chat",
    enabled: true,
  },
  {
    labelKey: "operationsTitle",
    icon: "clipboard",
    href: "/(app)/operations",
    enabled: true,
  },
  {
    labelKey: "calendarTitle",
    icon: "calendar",
    href: "/(app)/calendar",
    enabled: true,
  },
  {
    labelKey: "eventsTitle",
    icon: "calendar",
    enabled: false,
  },
  {
    labelKey: "menusTitle",
    icon: "book-open",
    enabled: false,
  },
  {
    labelKey: "recipesTitle",
    icon: "coffee",
    enabled: false,
  },
  {
    labelKey: "teamTitle",
    icon: "users",
    enabled: false,
  },
  {
    labelKey: "filesTitle",
    icon: "folder",
    enabled: false,
  },
  {
    labelKey: "notificationsTitle",
    icon: "bell",
    href: routes.app.notifications,
    enabled: true,
  },
];

export function AppShell({ title, subtitle, children }: AppShellProps) {
  const { width } = useWindowDimensions();
  const { theme } = useAppTheme();
  const { t } = useTranslation(["app", "common"]);
  const pathname = usePathname();

  const { session, signOut } = useAuth();
  const unreadNotificationsQuery = useNotificationUnreadCount();

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

  const handleNavigation = (item: NavItem) => {
    if (!item.enabled || !item.href) {
      return;
    }

    router.push(item.href);
  };

  const renderNavItem = (item: NavItem) => {
    const active = item.href ? pathname === item.href : false;

    const textColor = active
      ? theme.colors.text.primary
      : theme.colors.text.secondary;

    return (
      <Pressable
        key={item.labelKey}
        accessibilityRole="button"
        disabled={!item.enabled}
        onPress={() => handleNavigation(item)}
        style={({ pressed }) => ({
          alignItems: "center",

          backgroundColor: active
            ? theme.components.navigation.sidebarItem.activeBackground
            : pressed && item.enabled
              ? theme.components.navigation.sidebarItem.activeBackground
              : "transparent",

          borderCurve: "continuous",
          borderRadius: theme.radius.md,

          flexDirection: "row",

          gap: theme.spacing[3],

          opacity: item.enabled ? 1 : 0.45,

          paddingHorizontal: theme.spacing[3],
          paddingVertical: theme.spacing[3],
        })}
      >
        <Feather color={textColor} name={item.icon} size={18} />

        <AppText
          variant="bodyMedium"
          style={{
            color: textColor,
            flex: 1,
          }}
        >
          {t(item.labelKey)}
        </AppText>

        {item.labelKey === "notificationsTitle" && unreadNotificationsQuery.data ? (
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
            contentContainerStyle={{
              flexGrow: 1,
              paddingHorizontal: theme.spacing[6],
              paddingVertical: theme.spacing[5],
            }}
          >
            <View
              style={{
                alignSelf: "center",

                gap: theme.spacing[5],

                maxWidth: theme.layout.content.maxWidth,

                width: "100%",
              }}
            >
              {/* HEADER */}

              <View
                style={{
                  gap: theme.spacing[2],
                }}
              >
                <AppText variant="hero">{title}</AppText>

                <AppText muted variant="bodyMedium">
                  {subtitle}
                </AppText>
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
      contentContainerStyle={{
        backgroundColor: theme.colors.background.app,
        flexGrow: 1,
      }}
    >
      {/* Por ahora mantenemos navegación visible arriba.
          Después podemos convertir esto en drawer/hamburger. */}

      <View>
        <View
          style={{
            minHeight: 0,
          }}
        >
          {sidebar}
        </View>

        <View
          style={{
            gap: theme.spacing[4],

            paddingHorizontal: theme.spacing[4],
            paddingVertical: theme.spacing[5],
          }}
        >
          <View
            style={{
              gap: theme.spacing[2],
            }}
          >
            <AppText variant="hero">{title}</AppText>

            <AppText muted variant="bodyMedium">
              {subtitle}
            </AppText>
          </View>

          {children}
        </View>
      </View>
    </ScrollView>
  );
}
