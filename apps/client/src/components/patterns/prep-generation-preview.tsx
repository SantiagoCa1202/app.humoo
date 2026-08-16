import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { ActionPreviewCard } from "@/components/patterns/action-preview-card";
import { AlertCard } from "@/components/patterns/alert-card";
import { ConfirmationCard } from "@/components/patterns/confirmation-card";
import { DetailCard } from "@/components/patterns/detail-card";
import { PrepItemList } from "@/components/patterns/prep-item-list";
import { PrepSummaryCard } from "@/components/patterns/prep-summary-card";
import {
  type PrepDisplayRecord,
  type PrepGenerationOptionsRecord,
  type PrepGenerationPreviewRecord,
} from "@/features/prep";
import { useAppTheme } from "@/theme/ThemeProvider";

export type PrepGenerationPreviewProps = {
  accessibilityLabel?: string;
  estimatedItems?: number | null;
  event?: PrepGenerationPreviewRecord["event"] | null;
  loading?: boolean;
  menu?: string | null;
  onCancel?: () => void | Promise<void>;
  onConfirm?: () => void | Promise<void>;
  options: PrepGenerationOptionsRecord;
  preview: PrepGenerationPreviewRecord;
};

export function PrepGenerationPreview({
  accessibilityLabel,
  estimatedItems,
  event,
  loading = false,
  menu,
  onCancel,
  onConfirm,
  options,
  preview,
}: PrepGenerationPreviewProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const previewEvent = event ?? preview.event ?? null;
  const prepList: PrepDisplayRecord = preview.prepList ?? {
    id: "prep-preview",
    name: t("prep.generation.preview.fallbackName"),
  };

  return (
    <View
      accessibilityLabel={accessibilityLabel ?? t("prep.generation.preview.accessibilityLabel")}
      style={{ gap: theme.spacing[3] }}
    >
      <ActionPreviewCard
        action={t("prep.generation.preview.action")}
        description={preview.summary ?? t("prep.generation.preview.description")}
        impact={t("prep.generation.preview.impact")}
        metadata={
          [
            previewEvent?.name
              ? {
                  label: t("prep.generation.labels.event"),
                  value: previewEvent.name,
                }
              : null,
            menu ?? preview.menuLabel
              ? {
                  label: t("prep.generation.labels.menu"),
                  value: menu ?? preview.menuLabel ?? "",
                }
              : null,
            typeof (estimatedItems ?? preview.estimatedItems) === "number"
              ? {
                  label: t("prep.generation.labels.itemsToGenerate"),
                  value: t("prep.metrics.items", {
                    count: estimatedItems ?? preview.estimatedItems ?? 0,
                  }),
                }
              : null,
            typeof options.guestCount === "number"
              ? {
                  label: t("prep.generation.labels.guestCount"),
                  value: new Intl.NumberFormat().format(options.guestCount),
                }
              : null,
          ].filter(Boolean) as React.ComponentProps<typeof ActionPreviewCard>["metadata"]
        }
        title={t("prep.generation.preview.title")}
        type={t("prep.generation.preview.badge")}
      />
      {preview.warnings?.map((warning, index) => (
        <AlertCard
          key={warning.id ?? `prep-generation-warning-${index}`}
          description={warning.description ?? undefined}
          title={warning.title}
          tone={warning.tone === "danger" ? "error" : warning.tone ?? "warning"}
          variant="muted"
        />
      ))}
      <PrepSummaryCard
        compact
        prepList={prepList}
        progress={preview.progress}
      />
      {preview.metadata ? (
        <DetailCard
          rows={Object.entries(preview.metadata).map(([key, value]) => ({
            label: key,
            value: value === null || value === undefined ? t("prep.versionComparison.emptyValue") : String(value),
          }))}
          title={t("prep.generation.preview.metadataTitle")}
          variant="default"
        />
      ) : null}
      {preview.items?.length ? (
        <PrepItemList
          compact
          items={preview.items}
          loading={loading}
        />
      ) : null}
      {onConfirm || onCancel ? (
        <ConfirmationCard
          confirmLabel={t("prep.generation.preview.confirm")}
          description={t("prep.generation.preview.confirmDescription")}
          loading={loading}
          onCancel={onCancel}
          onConfirm={onConfirm}
          title={t("prep.generation.preview.confirmTitle")}
        />
      ) : null}
    </View>
  );
}
