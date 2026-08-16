import { View, type ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

type SectionCardProps = ViewProps & {
  eyebrow?: string;
  title: string;
  description?: string;
  action?: React.ReactNode;
  children: React.ReactNode;
};

export function SectionCard({
  eyebrow,
  title,
  description,
  action,
  children,
  style,
  ...props
}: SectionCardProps) {
  return (
    <Card
      style={[
        {
          gap: spacing[3],
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
          gap: spacing[3],
          justifyContent: "space-between",
        }}
      >
        <View style={{ flex: 1, gap: spacing[1], minWidth: 220 }}>
          {eyebrow ? <AppText variant="overline">{eyebrow}</AppText> : null}
          <AppText variant="title">{title}</AppText>
          {description ? <AppText muted>{description}</AppText> : null}
        </View>
        {action}
      </View>
      {children}
    </Card>
  );
}
