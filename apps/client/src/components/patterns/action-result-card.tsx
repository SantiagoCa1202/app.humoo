import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { Badge } from "@/components/primitives/badge";
import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type ActionResultStatus = "success" | "failure" | "partial";

export type ActionResultDetail = {
  id?: string;
  label: React.ReactNode;
  value: React.ReactNode;
};

export type ActionResultCardProps = {
  accessibilityLabel?: string;
  actionLabel?: string;
  description?: React.ReactNode;
  details?: ActionResultDetail[];
  onAction?: () => void | Promise<void>;
  status: ActionResultStatus;
  title?: React.ReactNode;
};

function getBadgeVariant(status: ActionResultStatus) {
  if (status === "failure") {
    return "danger" as const;
  }

  if (status === "partial") {
    return "warning" as const;
  }

  return "success" as const;
}

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

export function ActionResultCard({
  accessibilityLabel,
  actionLabel,
  description,
  details = [],
  onAction,
  status,
  title,
}: ActionResultCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ?? t("chat.operations.result.accessibilityLabel")
      }
      padding="md"
      radius="lg"
      variant="elevated"
    >
      <CardHeader
        padding="none"
        subtitle={description ?? t(`chat.operations.result.description.${status}`)}
        title={title ?? t(`chat.operations.result.title.${status}`)}
        trailing={
          <Badge
            dot
            label={t(`chat.operations.result.status.${status}`)}
            size="sm"
            variant={getBadgeVariant(status)}
          />
        }
      />
      {details.length ? (
        <CardContent padding="none" topDivider>
          <View style={{ gap: theme.spacing[2] }}>
            {details.map((detail, index) => (
              <View
                key={detail.id ?? `result-detail-${index}`}
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
      {onAction && actionLabel ? (
        <CardFooter align="right" divider={details.length > 0} padding="none">
          <Button
            accessibilityLabel={actionLabel}
            label={actionLabel}
            onPress={onAction}
            size="sm"
            variant={status === "failure" ? "secondary" : "primary"}
          />
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
