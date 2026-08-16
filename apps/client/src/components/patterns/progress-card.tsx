import { View } from "react-native";

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

export type ProgressMetric = {
  label: React.ReactNode;
  tone?: TextTone;
  value: React.ReactNode;
};

export type ProgressCardProps = Omit<BaseCardProps, "children"> & {
  completed?: number;
  metrics?: ProgressMetric[];
  percentage?: number;
  status?: AppOperationalStatus;
  statusNamespace?: StatusConfigNamespace;
  subtitle?: React.ReactNode;
  title: React.ReactNode;
  total?: number;
  trailing?: React.ReactNode;
  value?: number;
};

function clampPercentage(value: number) {
  if (value < 0) {
    return 0;
  }

  if (value > 100) {
    return 100;
  }

  return value;
}

export function ProgressCard({
  completed,
  metrics = [],
  percentage,
  status,
  statusNamespace,
  subtitle,
  title,
  total,
  trailing,
  value,
  variant = "default",
  ...props
}: ProgressCardProps) {
  const { theme } = useAppTheme();
  const resolvedPercentage =
    typeof percentage === "number"
      ? clampPercentage(percentage)
      : typeof completed === "number" && typeof total === "number" && total > 0
      ? clampPercentage((completed / total) * 100)
      : typeof value === "number"
      ? clampPercentage(value)
      : 0;
  const progressTone =
    resolvedPercentage >= 100
      ? theme.colors.status.success
      : status === "blocked" || status === "cancelled" || status === "error"
      ? theme.colors.status.danger
      : theme.colors.brand.primary;

  return (
    <BaseCard padding="lg" variant={variant} {...props}>
      <CardHeader
        subtitle={subtitle}
        title={title}
        trailing={
          <View style={{ alignItems: "flex-end", gap: theme.spacing[2] }}>
            {trailing}
            {status ? (
              <StatusBadge namespace={statusNamespace} size="sm" status={status} />
            ) : null}
          </View>
        }
      />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          <View
            style={{
              alignItems: "center",
              flexDirection: "row",
              justifyContent: "space-between",
            }}
          >
            <Text selectable variant="display" style={{ fontVariant: ["tabular-nums"] }}>
              {Math.round(resolvedPercentage)}%
            </Text>
            {typeof completed === "number" && typeof total === "number" ? (
              <Text tone="muted" variant="bodySmall" style={{ fontVariant: ["tabular-nums"] }}>
                {completed}/{total}
              </Text>
            ) : null}
          </View>
          <View
            style={{
              backgroundColor: theme.colors.background.muted,
              borderRadius: theme.radius.full,
              height: theme.spacing[2],
              overflow: "hidden",
            }}
          >
            <View
              style={{
                backgroundColor: progressTone,
                borderRadius: theme.radius.full,
                height: "100%",
                width: `${resolvedPercentage}%`,
              }}
            />
          </View>
          {metrics.length ? (
            <View
              style={{
                flexDirection: "row",
                flexWrap: "wrap",
                gap: theme.spacing[3],
              }}
            >
              {metrics.map((metric, index) => (
                <View key={`progress-metric-${index}`} style={{ gap: theme.spacing[1], minWidth: 100 }}>
                  {typeof metric.label === "string" || typeof metric.label === "number" ? (
                    <Text tone="muted" variant="caption">
                      {metric.label}
                    </Text>
                  ) : (
                    metric.label
                  )}
                  {typeof metric.value === "string" || typeof metric.value === "number" ? (
                    <Text tone={metric.tone ?? "default"} variant="bodySmall">
                      {metric.value}
                    </Text>
                  ) : (
                    metric.value
                  )}
                </View>
              ))}
            </View>
          ) : null}
        </View>
      </CardContent>
    </BaseCard>
  );
}
