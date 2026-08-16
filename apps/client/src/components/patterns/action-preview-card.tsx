import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ActionPreviewChange = {
  after?: React.ReactNode;
  before?: React.ReactNode;
  id?: string;
  label: React.ReactNode;
};

export type ActionPreviewMetadataItem = {
  id?: string;
  label: React.ReactNode;
  value: React.ReactNode;
};

export type ActionPreviewCardProps = {
  accessibilityLabel?: string;
  action?: React.ReactNode;
  changes?: ActionPreviewChange[];
  description?: React.ReactNode;
  destructive?: boolean;
  impact?: React.ReactNode;
  metadata?: ActionPreviewMetadataItem[];
  title?: React.ReactNode;
  type?: React.ReactNode;
};

function renderTextNode(node: React.ReactNode, variant: "bodySmall" | "caption" = "bodySmall") {
  if (typeof node === "string" || typeof node === "number") {
    return (
      <Text selectable variant={variant}>
        {node}
      </Text>
    );
  }

  return node;
}

export function ActionPreviewCard({
  accessibilityLabel,
  action,
  changes = [],
  description,
  destructive = false,
  impact,
  metadata = [],
  title,
  type,
}: ActionPreviewCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ?? t("chat.operations.preview.accessibilityLabel")
      }
      padding="md"
      radius="lg"
      variant="elevated"
    >
      <CardHeader
        padding="none"
        subtitle={description ?? t("chat.operations.preview.description")}
        title={title ?? t("chat.operations.preview.title")}
        trailing={
          <Badge
            dot
            label={
              typeof type === "string" || typeof type === "number"
                ? String(type)
                : t("chat.operations.preview.badge")
            }
            size="sm"
            variant={destructive ? "danger" : "primary"}
          />
        }
      >
        {action ? (
          renderTextNode(action)
        ) : (
          <Text selectable tone="secondary" variant="bodySmall">
            {t("chat.operations.preview.actionLabel")}
          </Text>
        )}
      </CardHeader>
      <CardContent padding="none" topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {changes.length ? (
            <View style={{ gap: theme.spacing[3] }}>
              {changes.map((change, index) => (
                <View
                  key={change.id ?? `preview-change-${index}`}
                  style={{ gap: theme.spacing[2] }}
                >
                  {renderTextNode(change.label, "caption")}
                  {change.before !== undefined || change.after !== undefined ? (
                    <View
                      style={{
                        flexDirection: "row",
                        flexWrap: "wrap",
                        gap: theme.spacing[3],
                      }}
                    >
                      {change.before !== undefined ? (
                        <View
                          style={{
                            backgroundColor: theme.colors.status.dangerSoft,
                            borderColor: theme.colors.status.danger,
                            borderCurve: "continuous",
                            borderRadius: theme.radius.md,
                            borderWidth: 1,
                            flex: 1,
                            gap: theme.spacing[2],
                            minWidth: 180,
                            padding: theme.spacing[3],
                          }}
                        >
                          <Text tone="danger" variant="overline">
                            {t("cards.comparison.before")}
                          </Text>
                          {renderTextNode(change.before)}
                        </View>
                      ) : null}
                      {change.after !== undefined ? (
                        <View
                          style={{
                            backgroundColor: destructive
                              ? theme.colors.status.warningSoft
                              : theme.colors.status.successSoft,
                            borderColor: destructive
                              ? theme.colors.status.warning
                              : theme.colors.status.success,
                            borderCurve: "continuous",
                            borderRadius: theme.radius.md,
                            borderWidth: 1,
                            flex: 1,
                            gap: theme.spacing[2],
                            minWidth: 180,
                            padding: theme.spacing[3],
                          }}
                        >
                          <Text
                            tone={destructive ? "warning" : "success"}
                            variant="overline"
                          >
                            {t("cards.comparison.after")}
                          </Text>
                          {renderTextNode(change.after)}
                        </View>
                      ) : null}
                    </View>
                  ) : (
                    renderTextNode(change.after ?? change.before ?? null)
                  )}
                  {index < changes.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              ))}
            </View>
          ) : null}
          {metadata.length ? (
            <View style={{ gap: theme.spacing[2] }}>
              <Text tone="secondary" variant="overline">
                {t("chat.operations.preview.metadata")}
              </Text>
              {metadata.map((item, index) => (
                <View
                  key={item.id ?? `preview-meta-${index}`}
                  style={{
                    alignItems: "flex-start",
                    flexDirection: "row",
                    flexWrap: "wrap",
                    gap: theme.spacing[3],
                    justifyContent: "space-between",
                  }}
                >
                  <View style={{ flex: 1, minWidth: 120 }}>
                    {renderTextNode(item.label, "caption")}
                  </View>
                  <View style={{ flex: 1, minWidth: 160 }}>
                    {renderTextNode(item.value)}
                  </View>
                </View>
              ))}
            </View>
          ) : null}
          {impact ? (
            <View style={{ gap: theme.spacing[2] }}>
              <Text tone="secondary" variant="overline">
                {t("chat.operations.preview.impact")}
              </Text>
              {renderTextNode(impact)}
            </View>
          ) : null}
          {!changes.length && !metadata.length && !impact && !action ? (
            <Text selectable tone="secondary" variant="bodySmall">
              {t("chat.operations.preview.empty")}
            </Text>
          ) : null}
        </View>
      </CardContent>
    </BaseCard>
  );
}
