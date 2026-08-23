import type { Href } from "expo-router";

import { routes } from "@/navigation/routes";

export type AppNavigationItemId =
  | "chat"
  | "operations"
  | "calendar"
  | "events"
  | "menus"
  | "recipes"
  | "prep"
  | "tasks"
  | "team"
  | "documents"
  | "notifications"
  | "settings"
  | "profile";

export type AppNavigationItemGroup = "primary" | "secondary";

export type AppNavigationItem = {
  accessibilityKey: string;
  icon: string;
  extraMatchPrefixes?: string[];
  group: AppNavigationItemGroup;
  href: Href;
  id: AppNavigationItemId;
  matchPrefix?: string;
  titleKey: string;
};

export const APP_NAVIGATION_ITEMS: readonly AppNavigationItem[] = [
  {
    accessibilityKey: "navigation.accessibility.chat",
    group: "primary",
    href: routes.app.chat,
    icon: "message-circle",
    id: "chat",
    matchPrefix: "/(app)/chat",
    titleKey: "navigation.chat",
  },
  {
    accessibilityKey: "navigation.accessibility.operations",
    group: "primary",
    href: routes.app.operations,
    icon: "clipboard",
    id: "operations",
    extraMatchPrefixes: ["/(app)/clients", "/(app)/contacts", "/(app)/venues"],
    matchPrefix: "/(app)/operations",
    titleKey: "navigation.operations",
  },
  {
    accessibilityKey: "navigation.accessibility.calendar",
    group: "primary",
    href: routes.app.calendar,
    icon: "calendar",
    id: "calendar",
    extraMatchPrefixes: ["/(app)/events/calendar"],
    matchPrefix: "/(app)/calendar",
    titleKey: "navigation.calendar",
  },
  {
    accessibilityKey: "navigation.accessibility.events",
    group: "primary",
    href: routes.app.events,
    icon: "calendar",
    id: "events",
    matchPrefix: "/(app)/events",
    titleKey: "navigation.events",
  },
  {
    accessibilityKey: "navigation.accessibility.menus",
    group: "primary",
    href: routes.app.menus,
    icon: "book-open",
    id: "menus",
    matchPrefix: "/(app)/menus",
    titleKey: "navigation.menus",
  },
  {
    accessibilityKey: "navigation.accessibility.recipes",
    group: "primary",
    href: routes.app.recipes,
    icon: "coffee",
    id: "recipes",
    matchPrefix: "/(app)/recipes",
    titleKey: "navigation.recipes",
  },
  {
    accessibilityKey: "navigation.accessibility.prep",
    group: "primary",
    href: routes.app.prep,
    icon: "clipboard",
    id: "prep",
    matchPrefix: "/(app)/prep",
    titleKey: "navigation.prep",
  },
  {
    accessibilityKey: "navigation.accessibility.tasks",
    group: "primary",
    href: routes.app.tasks,
    icon: "check-square",
    id: "tasks",
    matchPrefix: "/(app)/tasks",
    titleKey: "navigation.tasks",
  },
  {
    accessibilityKey: "navigation.accessibility.team",
    group: "primary",
    href: routes.app.teamRoster,
    icon: "users",
    id: "team",
    matchPrefix: "/(app)/team",
    titleKey: "navigation.team",
  },
  {
    accessibilityKey: "navigation.accessibility.documents",
    group: "primary",
    href: routes.app.documents,
    icon: "folder",
    id: "documents",
    matchPrefix: "/(app)/documents",
    titleKey: "navigation.documents",
  },
  {
    accessibilityKey: "navigation.accessibility.notifications",
    group: "primary",
    href: routes.app.notifications,
    icon: "bell",
    id: "notifications",
    matchPrefix: "/(app)/notifications",
    titleKey: "navigation.notifications",
  },
  {
    accessibilityKey: "navigation.accessibility.settings",
    group: "secondary",
    href: routes.app.settings,
    icon: "settings",
    id: "settings",
    matchPrefix: "/(app)/settings",
    titleKey: "navigation.settings",
  },
  {
    accessibilityKey: "navigation.accessibility.profile",
    group: "secondary",
    href: routes.app.profile,
    icon: "user",
    id: "profile",
    matchPrefix: "/(app)/profile",
    titleKey: "navigation.profile",
  },
] as const;

export function getNavigationItems(group?: AppNavigationItemGroup) {
  return group
    ? APP_NAVIGATION_ITEMS.filter((item) => item.group === group)
    : APP_NAVIGATION_ITEMS;
}

export function getNavigationItemByPath(pathname?: string | null) {
  if (!pathname) {
    return APP_NAVIGATION_ITEMS[0];
  }

  const normalizedPathname = pathname.replace(/^\/\(app\)/, "");

  const matches = APP_NAVIGATION_ITEMS.flatMap((item) => {
    const prefixes = [item.matchPrefix, ...(item.extraMatchPrefixes ?? [])].filter(
      (prefix): prefix is string => Boolean(prefix)
    );

    return prefixes
      .filter((prefix) => {
        const normalizedPrefix = prefix.replace(/^\/\(app\)/, "");

        return (
          normalizedPathname === normalizedPrefix ||
          normalizedPathname.startsWith(`${normalizedPrefix}/`)
        );
      })
      .map((prefix) => ({
        item,
        prefix,
      }));
  });

  if (matches.length === 0) {
    return APP_NAVIGATION_ITEMS[0];
  }

  return matches.sort((left, right) => right.prefix.length - left.prefix.length)[0].item;
}
