import type { ViewProps } from "react-native";

import { BaseCard } from "@/components/primitives/base-card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";

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
    <BaseCard
      padding="lg"
      style={[
        {
          flex: 1,
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
    </BaseCard>
  );
}
