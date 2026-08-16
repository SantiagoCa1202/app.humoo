import { Pressable, View, type ViewProps } from "react-native";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { Button } from "@/components/primitives/button";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text } from "@/components/primitives/text";
import type {
  AppOperationalStatus,
  StatusConfigNamespace,
} from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ListCardItem = {
  disabled?: boolean;
  id: string;
  leading?: React.ReactNode;
  status?: AppOperationalStatus;
  statusNamespace?: StatusConfigNamespace;
  subtitle?: React.ReactNode;
  title: React.ReactNode;
  trailing?: React.ReactNode;
};

export type ListCardProps = Omit<BaseCardProps, "children"> &
  Omit<ViewProps, "style"> & {
    action?: React.ReactNode;
    actionLabel?: string;
    emptyContent?: React.ReactNode;
    items: ListCardItem[];
    onActionPress?: () => void;
    onItemPress?: (item: ListCardItem) => void;
    style?: BaseCardProps["style"];
    subtitle?: React.ReactNode;
    title: React.ReactNode;
  };

export function ListCard({
  action,
  actionLabel,
  emptyContent,
  items,
  onActionPress,
  onItemPress,
  style,
  subtitle,
  title,
  variant = "default",
  ...props
}: ListCardProps) {
  const { theme } = useAppTheme();

  return (
    <BaseCard padding="lg" style={style} variant={variant} {...props}>
      <CardHeader
        subtitle={subtitle}
        title={title}
        trailing={
          action ??
          (actionLabel && onActionPress ? (
            <Button label={actionLabel} onPress={onActionPress} size="sm" variant="ghost" />
          ) : undefined)
        }
      />
      <CardContent topDivider>
        {items.length === 0 ? (
          emptyContent
        ) : (
          <View style={{ gap: theme.spacing[3] }}>
            {items.map((item, index) => {
              const content = (
                <View
                  style={{
                    alignItems: "center",
                    flexDirection: "row",
                    gap: theme.spacing[3],
                    justifyContent: "space-between",
                    opacity: item.disabled ? 0.65 : 1,
                  }}
                >
                  {item.leading ? <View>{item.leading}</View> : null}
                  <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 0 }}>
                    {typeof item.title === "string" || typeof item.title === "number" ? (
                      <Text selectable variant="bodyMedium">
                        {item.title}
                      </Text>
                    ) : (
                      item.title
                    )}
                    {item.subtitle ? (
                      typeof item.subtitle === "string" ||
                      typeof item.subtitle === "number" ? (
                        <Text tone="muted" variant="bodySmall">
                          {item.subtitle}
                        </Text>
                      ) : (
                        item.subtitle
                      )
                    ) : null}
                  </View>
                  <View
                    style={{
                      alignItems: "flex-end",
                      gap: theme.spacing[2],
                    }}
                  >
                    {item.trailing}
                    {item.status ? (
                      <StatusBadge
                        namespace={item.statusNamespace}
                        showDot
                        size="sm"
                        status={item.status}
                      />
                    ) : null}
                  </View>
                </View>
              );

              return (
                <View key={item.id} style={{ gap: theme.spacing[3] }}>
                  {onItemPress ? (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityState={{ disabled: item.disabled }}
                      disabled={item.disabled}
                      onPress={() => onItemPress(item)}
                      style={({ hovered, pressed }) => ({
                        backgroundColor:
                          item.disabled
                            ? "transparent"
                            : pressed
                            ? theme.colors.background.pressed
                            : hovered
                            ? theme.colors.background.subtle
                            : "transparent",
                        borderCurve: "continuous",
                        borderRadius: theme.radius.md,
                        padding: theme.spacing[2],
                      })}
                    >
                      {content}
                    </Pressable>
                  ) : (
                    content
                  )}
                  {index < items.length - 1 ? <Divider spacing="none" /> : null}
                </View>
              );
            })}
          </View>
        )}
      </CardContent>
    </BaseCard>
  );
}
