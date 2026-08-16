import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ErrorRecoveryCardProps = {
  accessibilityLabel?: string;
  alternativeLabel?: string;
  description?: React.ReactNode;
  errorCode?: React.ReactNode;
  onAlternative?: () => void | Promise<void>;
  onRetry?: () => void | Promise<void>;
  retryLabel?: string;
  safeDetail?: React.ReactNode;
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

export function ErrorRecoveryCard({
  accessibilityLabel,
  alternativeLabel,
  description,
  errorCode,
  onAlternative,
  onRetry,
  retryLabel,
  safeDetail,
  title,
}: ErrorRecoveryCardProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={
        accessibilityLabel ?? t("chat.operations.recovery.accessibilityLabel")
      }
      padding="md"
      radius="lg"
      style={{
        backgroundColor: theme.colors.status.dangerSoft,
        borderColor: theme.colors.status.danger,
      }}
      variant="muted"
    >
      <CardHeader
        padding="none"
        subtitle={description ?? t("chat.operations.recovery.description")}
        title={title ?? t("chat.operations.recovery.title")}
      />
      {safeDetail || errorCode ? (
        <CardContent padding="none" topDivider>
          <View style={{ gap: theme.spacing[2] }}>
            {safeDetail ? (
              <View style={{ gap: theme.spacing[1] }}>
                <Text tone="secondary" variant="caption">
                  {t("chat.operations.recovery.safeDetail")}
                </Text>
                {renderNode(safeDetail)}
              </View>
            ) : null}
            {errorCode ? (
              <View style={{ gap: theme.spacing[1] }}>
                <Text tone="secondary" variant="caption">
                  {t("chat.operations.recovery.errorCode")}
                </Text>
                {renderNode(errorCode, "caption")}
              </View>
            ) : null}
          </View>
        </CardContent>
      ) : null}
      {onRetry || onAlternative ? (
        <CardFooter
          align="right"
          divider={Boolean(safeDetail || errorCode)}
          padding="none"
        >
          {onAlternative ? (
            <Button
              accessibilityLabel={
                alternativeLabel ?? t("chat.operations.recovery.alternative")
              }
              label={alternativeLabel ?? t("chat.operations.recovery.alternative")}
              onPress={onAlternative}
              size="sm"
              variant="secondary"
            />
          ) : null}
          {onRetry ? (
            <Button
              accessibilityLabel={retryLabel ?? t("chat.operations.recovery.retry")}
              label={retryLabel ?? t("chat.operations.recovery.retry")}
              onPress={onRetry}
              size="sm"
              variant="destructive"
            />
          ) : null}
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
