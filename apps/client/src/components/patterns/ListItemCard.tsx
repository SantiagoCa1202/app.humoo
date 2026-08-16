import { View, type ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { AppText } from "@/components/primitives/AppText";

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
          gap: 10,
          padding: 16,
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
          gap: 10,
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: 6, minWidth: 180 }}>
          <AppText variant="subtitle">{title}</AppText>
          {subtitle ? <AppText muted>{subtitle}</AppText> : null}
        </View>
        {aside}
      </View>
      {meta.length ? (
        <View style={{ gap: 4 }}>
          {meta.map((line) => (
            <AppText key={line} muted>
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
