import { View, type ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

type ListItemCardProps = ViewProps & {
  title: string;
  subtitle?: string;
  meta?: string[];
  aside?: React.ReactNode;
  footer?: React.ReactNode;
};

export function ListItemCard({
  title,
  subtitle,
  meta = [],
  aside,
  footer,
  style,
  children,
  ...props
}: ListItemCardProps) {
  return (
    <Card
      style={[
        {
          gap: spacing[2],
          padding: spacing[4],
        },
        style,
      ]}
      {...props}
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: spacing[2],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: spacing[1], minWidth: 180 }}>
          <AppText variant="h4">{title}</AppText>
          {subtitle ? <AppText muted>{subtitle}</AppText> : null}
        </View>
        {aside}
      </View>
      {meta.length ? (
        <View style={{ gap: spacing[1] }}>
          {meta.map((line) => (
            <AppText key={line} muted variant="bodySmall">
              {line}
            </AppText>
          ))}
        </View>
      ) : null}
      {children}
      {footer}
    </Card>
  );
}
