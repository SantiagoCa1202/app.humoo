import type { ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

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
  const { theme } = useAppTheme();

  return (
    <Card
      style={[
        {
          flex: 1,
          gap: theme.spacing[2],
          minWidth: 220,
        },
        style,
      ]}
      {...props}
    >
      <CardHeader eyebrow={label} />
      <CardContent>
        <Text variant="display">{value}</Text>
        {caption ? (
          <Text tone="muted" variant="bodySmall">
            {caption}
          </Text>
        ) : null}
      </CardContent>
    </Card>
  );
}
