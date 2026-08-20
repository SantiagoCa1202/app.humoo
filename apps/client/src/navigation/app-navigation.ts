import type { Href } from "expo-router";

import { routes } from "@/navigation/routes";

export type AppNavigationItemId =
  | "chat"
  | "operations"
  | "calendar"
  | "settings"
  | "profile";

export type AppNavigationItemGroup = "primary" | "secondary";

export type AppNavigationItem = {
  accessibilityKey: string;
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
    id: "chat",
    matchPrefix: "/(app)/chat",
    titleKey: "navigation.chat",
  },
  {
    accessibilityKey: "navigation.accessibility.operations",
    group: "primary",
    href: routes.app.operations,
    id: "operations",
    extraMatchPrefixes: ["/(app)/clients", "/(app)/contacts", "/(app)/venues", "/(app)/events"],
    matchPrefix: "/(app)/operations",
    titleKey: "navigation.operations",
  },
  {
    accessibilityKey: "navigation.accessibility.calendar",
    group: "primary",
    href: routes.app.calendar,
    id: "calendar",
    extraMatchPrefixes: ["/(app)/events/calendar"],
    matchPrefix: "/(app)/calendar",
    titleKey: "navigation.calendar",
  },
  {
    accessibilityKey: "navigation.accessibility.settings",
    group: "secondary",
    href: routes.app.settings,
    id: "settings",
    matchPrefix: "/(app)/settings",
    titleKey: "navigation.settings",
  },
  {
    accessibilityKey: "navigation.accessibility.profile",
    group: "secondary",
    href: routes.app.profile,
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

  const matches = APP_NAVIGATION_ITEMS.flatMap((item) => {
    const prefixes = [item.matchPrefix, ...(item.extraMatchPrefixes ?? [])].filter(
      (prefix): prefix is string => Boolean(prefix)
    );

    return prefixes
      .filter(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`)
      )
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
