import { View, type ViewProps } from "react-native";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { StatusBadge } from "@/components/primitives/status-badge";
import { Text, type TextTone } from "@/components/primitives/text";
import type {
  AppOperationalStatus,
  StatusConfigNamespace,
} from "@/theme/status-config";
import { useAppTheme } from "@/theme/ThemeProvider";

export type EntityCardMetadataItem = {
  label: React.ReactNode;
  tone?: TextTone;
  value: React.ReactNode;
};

export type EntityCardProps = Omit<BaseCardProps, "children"> &
  Omit<ViewProps, "style"> & {
    accessibilityLabel?: string;
    eyebrow?: React.ReactNode;
    leading?: React.ReactNode;
    metadata?: EntityCardMetadataItem[];
    selected?: boolean;
    status?: AppOperationalStatus;
    statusNamespace?: StatusConfigNamespace;
    subtitle?: React.ReactNode;
    title: React.ReactNode;
    trailing?: React.ReactNode;
  };

export function EntityCard({
  accessibilityLabel,
  disabled = false,
  eyebrow,
  leading,
  metadata = [],
  onLongPress,
  onPress,
  radius = "lg",
  selected = false,
  status,
  statusNamespace,
  style,
  subtitle,
  title,
  trailing,
  variant = "default",
  ...props
}: EntityCardProps) {
  const { theme } = useAppTheme();

  return (
    <BaseCard
      accessibilityLabel={accessibilityLabel}
      disabled={disabled}
      onLongPress={onLongPress}
      onPress={onPress}
      padding="lg"
      radius={radius}
      selected={selected}
      style={style}
      variant={variant}
      {...props}
    >
      <CardHeader
        eyebrow={eyebrow}
        leading={leading}
        subtitle={subtitle}
        title={title}
        trailing={
          trailing ?? status ? (
            <View
              style={{
                alignItems: "flex-end",
                gap: theme.spacing[2],
              }}
            >
              {trailing}
              {status ? (
                <StatusBadge namespace={statusNamespace} status={status} />
              ) : null}
            </View>
          ) : undefined
        }
      />
      {metadata.length ? (
        <CardContent topDivider>
          <View
            style={{
              flexDirection: "row",
              flexWrap: "wrap",
              gap: theme.spacing[3],
            }}
          >
            {metadata.map((item, index) => (
              <View
                key={`entity-meta-${index}`}
                style={{
                  flexGrow: 1,
                  gap: theme.spacing[1],
                  minWidth: 120,
                }}
              >
                {typeof item.label === "string" || typeof item.label === "number" ? (
                  <Text tone="muted" variant="caption">
                    {item.label}
                  </Text>
                ) : (
                  item.label
                )}
                {typeof item.value === "string" || typeof item.value === "number" ? (
                  <Text tone={item.tone ?? "default"} variant="bodySmall">
                    {item.value}
                  </Text>
                ) : (
                  item.value
                )}
              </View>
            ))}
          </View>
        </CardContent>
      ) : null}
    </BaseCard>
  );
}
