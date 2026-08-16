import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { EntityCard } from "@/components/patterns/entity-card";
import { Badge } from "@/components/primitives/badge";
import { Button } from "@/components/primitives/button";
import { Text } from "@/components/primitives/text";
import {
  formatMenuDateTime,
  formatMenuVersionLabel,
  type MenuVersionRecord,
} from "@/features/menus";
import { useAppTheme } from "@/theme/ThemeProvider";

export type MenuVersionCardProps = {
  accessibilityLabel?: string;
  compact?: boolean;
  onPress?: () => void | Promise<void>;
  onRestore?: () => void | Promise<void>;
  version: MenuVersionRecord;
};

export function MenuVersionCard({
  accessibilityLabel,
  compact = false,
  onPress,
  onRestore,
  version,
}: MenuVersionCardProps) {
  const { t, i18n } = useTranslation("common");
  const { theme } = useAppTheme();
  const createdAt = formatMenuDateTime(version.createdAt, i18n.language);
  const changeSummary = version.changeSummary?.trim() || version.notes?.trim() || null;

  return (
    <EntityCard
      accessibilityLabel={accessibilityLabel ?? t("menus.version.accessibilityLabel")}
      eyebrow={version.isCurrent ? t("menus.version.currentLabel") : t("menus.version.label")}
      metadata={[
        {
          label: t("menus.version.fields.createdAt"),
          value: createdAt ?? t("menus.version.emptyValue"),
        },
        {
          label: t("menus.version.fields.createdBy"),
          value: version.createdBy?.name?.trim() || t("menus.version.emptyValue"),
        },
      ]}
      onPress={onPress}
      subtitle={changeSummary ?? undefined}
      title={formatMenuVersionLabel(version, t)}
      trailing={
        <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
          <Badge
            label={t(version.isCurrent ? "menus.version.currentLabel" : "menus.version.previousLabel")}
            size="sm"
            variant={version.isCurrent ? "primary" : "neutral"}
          />
          {onRestore && !version.isCurrent ? (
            <Button
              label={t("menus.version.actions.restore")}
              onPress={onRestore}
              size="sm"
              variant="ghost"
            />
          ) : null}
        </View>
      }
      variant={compact ? "default" : "elevated"}
    />
  );
}
