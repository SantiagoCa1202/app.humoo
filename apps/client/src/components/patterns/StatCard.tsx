import type { ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

type StatCardProps = ViewProps & {
  label: string;
  value: string;
  caption?: string;
};

export function StatCard({
  label,
  value,
  caption,
  style,
  ...props
}: StatCardProps) {
  return (
    <Card
      style={[
        {
          flex: 1,
          gap: spacing[2],
          minWidth: 220,
        },
        style,
      ]}
      {...props}
    >
      <AppText variant="overline">{label}</AppText>
      <AppText variant="display">{value}</AppText>
      {caption ? <AppText muted>{caption}</AppText> : null}
    </Card>
  );
}
