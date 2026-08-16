import { formatEventDateRange } from "@/features/events";
import type {
  MenuAllergenRecord,
  MenuConflictType,
  MenuEventReference,
  MenuItemRecord,
  MenuRecipeSummaryRecord,
  MenuRecord,
  MenuSectionRecord,
  MenuSectionSummary,
  MenuStatus,
  MenuTagValue,
  MenuVersionRecord,
} from "@/features/menus/types";
import type { SemanticStatusTone } from "@/theme/status-config";

export type MenuDisplayRecord = MenuRecord;

export function getMenuTagLabel(tag: MenuTagValue) {
  return typeof tag === "string" ? tag : tag.label;
}

export function getMenuSectionLabel(section: MenuSectionSummary) {
  return section.translationKey ?? section.name?.trim() ?? null;
}

export function formatMenuSectionPreview(
  section: MenuSectionSummary,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  const label = getMenuSectionLabel(section);

  if (!label) {
    return null;
  }

  if (typeof section.itemCount === "number") {
    return `${label} - ${t("menus.metrics.items", { count: section.itemCount })}`;
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

export function formatMenuEventSummary(event?: MenuEventReference | null, locale?: string) {
  if (!event?.name?.trim()) {
    return null;
  }

  if (event.startsAt && event.timezone) {
    return `${event.name.trim()} - ${formatEventDateRange(
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

export function getMenuEventVenueName(event?: MenuEventReference | null) {
  if (!event?.venue) {
    return null;
  }

  if (typeof event.venue === "string") {
    return event.venue.trim() || null;
  }

  return event.venue.name?.trim() || null;
}

export function getMenuRecipeSummaryLabel(recipe: MenuRecipeSummaryRecord) {
  return recipe.name?.trim() || null;
}

export function getMenuAllergenLabel(
  allergen: MenuAllergenRecord,
  t: (key: string) => string
) {
  if (allergen.name?.trim()) {
    return allergen.name.trim();
  }

  if (allergen.translationKey) {
    return t(allergen.translationKey);
  }

  if (allergen.code?.trim()) {
    return allergen.code.trim();
  }

  return t("menus.allergens.unknown");
}

export function getMenuAllergenTone(
  allergen?: MenuAllergenRecord | null
): SemanticStatusTone {
  return allergen?.severity ?? "neutral";
}

export function hasMenuAllergenRisk(allergens: MenuAllergenRecord[]) {
  return allergens.some(
    (allergen) => allergen.severity === "warning" || allergen.severity === "danger"
  );
}

export function formatMenuVersionLabel(
  version: MenuVersionRecord,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  if (version.versionLabel?.trim()) {
    return version.versionLabel.trim();
  }

  if (version.versionNumber !== null && version.versionNumber !== undefined) {
    return t("menus.version.versionNumber", { value: version.versionNumber });
  }

  return t("menus.version.versionFallback");
}

export function formatMenuDateTime(value?: string | null, locale?: string) {
  if (!value) {
    return null;
  }

  const date = new Date(value);

  if (Number.isNaN(date.getTime())) {
    return value;
  }

  try {
    return new Intl.DateTimeFormat(locale, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  } catch {
    return new Intl.DateTimeFormat(undefined, {
      dateStyle: "medium",
      timeStyle: "short",
    }).format(date);
  }
}

export function getMenuDuplicateDefaultName(
  menu: Pick<MenuRecord, "name">,
  t: (key: string, options?: Record<string, unknown>) => string
) {
  return t("menus.duplicate.defaultName", { name: menu.name });
}

export function getMenuConflictDescriptionKey(conflictType?: MenuConflictType) {
  if (conflictType === "remote_update") {
    return "menus.conflict.types.remote_update";
  }

  if (conflictType === "section_changed") {
    return "menus.conflict.types.section_changed";
  }

  if (conflictType === "item_changed") {
    return "menus.conflict.types.item_changed";
  }

  if (conflictType === "stale_data") {
    return "menus.conflict.types.stale_data";
  }

  return "menus.conflict.types.version_conflict";
}
