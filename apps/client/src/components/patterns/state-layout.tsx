import { View } from "react-native";

import { BaseCard } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import type { ButtonVariant } from "@/components/primitives/button-base";
import type { AppStateTone } from "@/theme/status-config";
import { getAppStateAppearance } from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

type StateAction = {
  accessibilityHint?: string;
  accessibilityLabel?: string;
  label: string;
  onPress: () => void | Promise<void>;
  variant?: ButtonVariant;
};

export type StateLayoutProps = {
  accessibilityHint?: string;
  accessibilityLabel?: string;
  compact?: boolean;
  description?: React.ReactNode;
  detail?: React.ReactNode;
  primaryAction?: StateAction;
  secondaryAction?: StateAction;
  title: React.ReactNode;
  tone: AppStateTone;
  visual?: React.ReactNode;
};

export function StateLayout({
  accessibilityHint,
  accessibilityLabel,
  compact = false,
  description,
  detail,
  primaryAction,
  secondaryAction,
  title,
  tone,
  visual,
}: StateLayoutProps) {
  const { theme } = useAppTheme();
  const appearance = getAppStateAppearance(theme, tone);
  const bodyGap = compact ? theme.spacing[3] : theme.spacing[4];
  const actionsGap = compact ? theme.spacing[2] : theme.spacing[3];
  const buttonSize = compact ? "sm" : "md";
  const descriptionNode =
    typeof description === "string" || typeof description === "number" ? (
      <Text tone="secondary" variant={compact ? "bodySmall" : "body"}>
        {description}
      </Text>
    ) : (
      description
    );
  const detailNode =
    typeof detail === "string" || typeof detail === "number" ? (
      <Text tone="secondary" variant="bodySmall">
        {detail}
      </Text>
    ) : (
      detail
    );

  return (
    <BaseCard
      accessibilityHint={accessibilityHint}
      accessibilityLabel={accessibilityLabel}
      padding={compact ? "md" : "lg"}
      radius={compact ? "md" : "lg"}
      style={{
        backgroundColor: appearance.background,
        borderColor: appearance.border,
      }}
      variant="muted"
    >
      <View style={{ gap: bodyGap }}>
        {visual ? (
          <View style={{ alignItems: "flex-start" }}>
            {visual}
          </View>
        ) : null}
        <View style={{ gap: theme.spacing[2] }}>
          <Heading
            level={compact ? "h4" : "h3"}
            title={title}
          />
          {descriptionNode}
          {detailNode}
        </View>
        {primaryAction || secondaryAction ? (
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: actionsGap,
            }}
          >
            {primaryAction ? (
              <Button
                accessibilityHint={primaryAction.accessibilityHint}
                accessibilityLabel={primaryAction.accessibilityLabel ?? primaryAction.label}
                label={primaryAction.label}
                onPress={primaryAction.onPress}
                size={buttonSize}
                variant={primaryAction.variant ?? "primary"}
              />
            ) : null}
            {secondaryAction ? (
              <Button
                accessibilityHint={secondaryAction.accessibilityHint}
                accessibilityLabel={secondaryAction.accessibilityLabel ?? secondaryAction.label}
                label={secondaryAction.label}
                onPress={secondaryAction.onPress}
                size={buttonSize}
                variant={secondaryAction.variant ?? "secondary"}
              />
            ) : null}
          </View>
        ) : null}
      </View>
    </BaseCard>
  );
}
