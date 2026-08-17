import { View, type ViewProps } from "react-native";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text, type TextTone } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type SummaryMetric = {
  label: React.ReactNode;
  tone?: Exclude<TextTone, "secondary" | "muted" | "inverse">;
  value: React.ReactNode;
};

export type SummaryCardProps = Omit<BaseCardProps, "children"> &
  Omit<ViewProps, "style"> & {
    children?: React.ReactNode;
    metrics: SummaryMetric[];
    style?: BaseCardProps["style"];
    subtitle?: React.ReactNode;
    title: React.ReactNode;
    trailing?: React.ReactNode;
  };

export function SummaryCard({
  children,
  metrics,
  style,
  subtitle,
  title,
  trailing,
  variant = "default",
  ...props
}: SummaryCardProps) {
  const { theme } = useAppTheme();

  return (
    <BaseCard padding="lg" style={style} variant={variant} {...props}>
      <CardHeader subtitle={subtitle} title={title} trailing={trailing} />
      <CardContent topDivider>
        <View
          style={{
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[4],
          }}
        >
          {metrics.map((metric, index) => (
            <View
              key={`summary-metric-${index}`}
              style={{
                flexGrow: 1,
                gap: theme.spacing[1],
                minWidth: 120,
              }}
            >
              {typeof metric.label === "string" || typeof metric.label === "number" ? (
                <Text tone="muted" variant="overline">
                  {metric.label}
                </Text>
              ) : (
                metric.label
              )}
              {typeof metric.value === "string" || typeof metric.value === "number" ? (
                <Text tone={metric.tone ?? "default"} variant="display">
                  {metric.value}
                </Text>
              ) : (
                metric.value
              )}
            </View>
          ))}
        </View>
        {children ? <View style={{ marginTop: theme.spacing[3] }}>{children}</View> : null}
      </CardContent>
    </BaseCard>
  );
}
