import { View } from "react-native";
import { useTranslation } from "react-i18next";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { IconButton } from "@/components/primitives/icon-button";
import { Text } from "@/components/primitives/text";
import { getAlertAppearance, type AlertTone } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type AlertCardProps = Omit<BaseCardProps, "children"> & {
  actionLabel?: string;
  description?: React.ReactNode;
  dismissible?: boolean;
  icon?: React.ReactNode;
  message?: React.ReactNode;
  onAction?: () => void;
  onDismiss?: () => void;
  title: React.ReactNode;
  tone?: AlertTone;
};

export function AlertCard({
  actionLabel,
  description,
  dismissible = false,
  icon,
  message,
  onAction,
  onDismiss,
  title,
  tone = "info",
  variant = "muted",
  ...props
}: AlertCardProps) {
  const { theme } = useAppTheme();
  const { t } = useTranslation("common");
  const appearance = getAlertAppearance(theme, tone);
  const body = description ?? message;

  return (
    <BaseCard
      padding="lg"
      style={{
        backgroundColor: appearance.background,
        borderColor: appearance.border,
      }}
      variant={variant}
      {...props}
    >
      <CardHeader
        leading={icon}
        title={
          typeof title === "string" || typeof title === "number" ? (
            <Text selectable variant="h4">
              {title}
            </Text>
          ) : (
            title
          )
        }
        trailing={
          dismissible && onDismiss ? (
            <IconButton
              accessibilityLabel={t("cards.alert.dismiss")}
              icon={<Text variant="bodySmall">x</Text>}
              onPress={onDismiss}
              size="sm"
              variant="ghost"
            />
          ) : undefined
        }
      />
      {body ? (
        <CardContent topDivider>
          {typeof body === "string" || typeof body === "number" ? (
            <Text selectable tone="default" variant="bodySmall">
              {body}
            </Text>
          ) : (
            body
          )}
        </CardContent>
      ) : null}
      {onAction && actionLabel ? (
        <CardFooter align="right" divider>
          <Button label={actionLabel} onPress={onAction} size="sm" variant="secondary" />
        </CardFooter>
      ) : null}
    </BaseCard>
  );
}
