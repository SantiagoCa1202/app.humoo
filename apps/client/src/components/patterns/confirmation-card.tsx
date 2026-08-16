import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ConfirmationDetail = {
  id?: string;
  label: React.ReactNode;
  value: React.ReactNode;
};

export type ConfirmationCardProps = {
  accessibilityLabel?: string;
  cancelLabel?: string;
  confirmLabel?: string;
  description?: React.ReactNode;
  destructive?: boolean;
  details?: ConfirmationDetail[];
  disabled?: boolean;
  loading?: boolean;
  onCancel?: () => void | Promise<void>;
  onConfirm?: () => void | Promise<void>;
  title?: React.ReactNode;
};

function renderNode(node: React.ReactNode, variant: "bodySmall" | "caption" = "bodySmall") {
  if (typeof node === "string" || typeof node === "number") {
    return (
      <Text selectable variant={variant}>
        {node}
      </Text>
    );
  }

  return node;
}

export function ConfirmationCard({
  accessibilityLabel,
  cancelLabel,
  confirmLabel,
  description,
  destructive = false,
  details = [],
  disabled = false,
  loading = false,
  onCancel,
  onConfirm,
  title,
}: ConfirmationCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ?? t("chat.operations.confirmation.accessibilityLabel")
      }
      disabled={disabled}
      padding="md"
      radius="lg"
      variant="elevated"
    >
      <CardHeader
        padding="none"
        subtitle={description ?? t("chat.operations.confirmation.description")}
        title={title ?? t("chat.operations.confirmation.title")}
      />
      {details.length ? (
        <CardContent padding="none" topDivider>
          <View style={{ gap: theme.spacing[2] }}>
            {details.map((detail, index) => (
              <View
                key={detail.id ?? `confirmation-detail-${index}`}
                style={{
                  alignItems: "flex-start",
                  flexDirection: "row",
                  flexWrap: "wrap",
                  gap: theme.spacing[3],
                  justifyContent: "space-between",
                }}
              >
                <View style={{ flex: 1, minWidth: 120 }}>
                  {renderNode(detail.label, "caption")}
                </View>
                <View style={{ flex: 1, minWidth: 160 }}>
                  {renderNode(detail.value)}
                </View>
              </View>
            ))}
          </View>
        </CardContent>
      ) : null}
      {onConfirm || onCancel ? (
        <CardFooter align="right" divider={details.length > 0} padding="none">
          {onCancel ? (
            <Button
              accessibilityLabel={cancelLabel ?? t("chat.operations.confirmation.cancel")}
              disabled={disabled || loading}
              label={cancelLabel ?? t("chat.operations.confirmation.cancel")}
              onPress={onCancel}
              size="sm"
              variant="secondary"
            />
          ) : null}
          {onConfirm ? (
            <Button
              accessibilityLabel={
                confirmLabel ??
                (destructive
                  ? t("chat.operations.confirmation.confirmDestructive")
                  : t("chat.operations.confirmation.confirm"))
              }
              disabled={disabled || loading}
              label={
                confirmLabel ??
                (destructive
                  ? t("chat.operations.confirmation.confirmDestructive")
                  : t("chat.operations.confirmation.confirm"))
              }
              loading={loading}
              onPress={onConfirm}
              size="sm"
              variant={destructive ? "destructive" : "primary"}
            />
          ) : null}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
