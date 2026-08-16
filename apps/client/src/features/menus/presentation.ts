import { formatEventDateRange } from "@/features/events";
import type {
  MenuItemRecord,
  MenuEventReference,
  MenuRecord,
  MenuSectionRecord,
  MenuSectionSummary,
  MenuStatus,
  MenuTagValue,
} from "@/features/menus/types";

export type MenuDisplayRecord = MenuRecord;

export function getMenuTagLabel(tag: MenuTagValue) {
  return typeof tag === "string" ? tag : tag.label;
}

export function getMenuSectionLabel(section: MenuSectionSummary) {
  return section.translationKey ?? section.name?.trim() ?? null;
}

export function formatMenuSectionPreview(section: MenuSectionSummary, t: (key: string, options?: Record<string, unknown>) => string) {
  const label = getMenuSectionLabel(section);

  if (!label) {
    return null;
  }

  if (typeof section.itemCount === "number") {
    return `${label} · ${t("menus.metrics.items", { count: section.itemCount })}`;
  }

  return label;
}

export function formatMenuMetricCount(
  key: "allergens" | "guests" | "items" | "recipes" | "sections",
  count: number,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  return t(`menus.metrics.${key}`, { count });
}

export function formatMenuEventSummary(
  event?: MenuEventReference | null,
  locale?: string
) {
  if (!event?.name?.trim()) {
    return null;
  }

  if (event.startsAt && event.timezone) {
    return `${event.name.trim()} · ${formatEventDateRange(
      {
        endsAt: event.endsAt ?? null,
        startsAt: event.startsAt,
        timezone: event.timezone,
      },
      locale
    )}`;
  }

  return event.name.trim();
}

export function getMenuSummary(menu: MenuDisplayRecord) {
  return menu.summary?.trim() || menu.description?.trim() || null;
}

export function getMenuStatus(menu: MenuDisplayRecord): MenuStatus | null {
  return menu.status ?? null;
}

export function getMenuItemRecipeName(item: MenuItemRecord) {
  return item.recipe?.name?.trim() || null;
}

export function getMenuSectionItems(section: MenuSectionRecord | MenuSectionSummary) {
  return "items" in section ? section.items : [];
}
