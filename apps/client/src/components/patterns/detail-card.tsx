import { View, type ViewProps } from "react-native";

import { BaseCard, type BaseCardProps } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Divider } from "@/components/primitives/divider";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type DetailCardRow = {
  label: React.ReactNode;
  renderValue?: () => React.ReactNode;
  value?: React.ReactNode;
};

export type DetailCardProps = Omit<BaseCardProps, "children"> &
  Omit<ViewProps, "style"> & {
    rows: DetailCardRow[];
    style?: BaseCardProps["style"];
    subtitle?: React.ReactNode;
    title: React.ReactNode;
    trailing?: React.ReactNode;
  };

export function DetailCard({
  rows,
  style,
  subtitle,
  title,
  trailing,
  variant = "default",
  ...props
}: DetailCardProps) {
  const { theme } = useAppTheme();

  return (
    <BaseCard padding="lg" style={style} variant={variant} {...props}>
      <CardHeader subtitle={subtitle} title={title} trailing={trailing} />
      <CardContent topDivider>
        <View style={{ gap: theme.spacing[3] }}>
          {rows.map((row, index) => (
            <View key={`detail-row-${index}`} style={{ gap: theme.spacing[3] }}>
              <View
                style={{
                  alignItems: "flex-start",
                  flexDirection: "row",
                  flexWrap: "wrap",
                  gap: theme.spacing[3],
                  justifyContent: "space-between",
                }}
              >
                <View style={{ flex: 1, minWidth: 120 }}>
                  {typeof row.label === "string" || typeof row.label === "number" ? (
                    <Text tone="muted" variant="caption">
                      {row.label}
                    </Text>
                  ) : (
                    row.label
                  )}
                </View>
                <View style={{ flex: 1, minWidth: 160 }}>
                  {row.renderValue ? (
                    row.renderValue()
                  ) : typeof row.value === "string" || typeof row.value === "number" ? (
                    <Text selectable variant="bodySmall">
                      {row.value}
                    </Text>
                  ) : (
                    row.value
                  )}
                </View>
              </View>
              {index < rows.length - 1 ? <Divider spacing="none" /> : null}
            </View>
          ))}
        </View>
      </CardContent>
    </BaseCard>
  );
}
