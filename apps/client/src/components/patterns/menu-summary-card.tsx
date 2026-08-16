import { useTranslation } from "react-i18next";

import { SummaryCard, type SummaryMetric } from "@/components/patterns/summary-card";
import { MenuStatusBadge } from "@/components/patterns/menu-status-badge";
import {
  formatMenuEventSummary,
  getMenuStatus,
  type MenuDisplayRecord,
} from "@/features/menus";

export type MenuSummaryMetric = SummaryMetric & {
  id?: string;
};

export type MenuSummaryCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  menu: MenuDisplayRecord;
  metrics?: MenuSummaryMetric[];
};

export function MenuSummaryCard({
  accessibilityLabel,
  compact = false,
  menu,
  metrics,
}: MenuSummaryCardProps) {
  const { t, i18n } = useTranslation("common");
  const status = getMenuStatus(menu);
  const eventSummary = formatMenuEventSummary(menu.event, i18n.language);
  const resolvedMetrics =
    metrics?.length
      ? metrics
      : [
          typeof menu.sectionCount === "number"
            ? {
                id: "sections",
                label: t("menus.labels.sections"),
                value: new Intl.NumberFormat(i18n.language).format(menu.sectionCount),
              }
            : null,
          typeof menu.itemCount === "number"
            ? {
                id: "items",
                label: t("menus.labels.items"),
                value: new Intl.NumberFormat(i18n.language).format(menu.itemCount),
              }
            : null,
          typeof menu.recipeCount === "number"
            ? {
                id: "recipes",
                label: t("menus.labels.recipes"),
                value: new Intl.NumberFormat(i18n.language).format(menu.recipeCount),
              }
            : null,
          typeof menu.allergenCount === "number"
            ? {
                id: "allergens",
                label: t("menus.labels.allergens"),
                value: new Intl.NumberFormat(i18n.language).format(menu.allergenCount),
              }
            : null,
          typeof menu.guestCount === "number"
            ? {
                id: "guests",
                label: t("menus.labels.guests"),
                value: new Intl.NumberFormat(i18n.language).format(menu.guestCount),
              }
            : null,
        ].filter(Boolean) as MenuSummaryMetric[];

  return (
    <SummaryCard
      accessibilityLabel={accessibilityLabel ?? t("menus.summary.accessibilityLabel")}
      metrics={compact ? resolvedMetrics.slice(0, 3) : resolvedMetrics}
      subtitle={eventSummary ?? menu.summary ?? menu.description ?? undefined}
      title={menu.name}
      trailing={status ? <MenuStatusBadge size="sm" status={status} /> : undefined}
      variant="elevated"
    />
  );
}
