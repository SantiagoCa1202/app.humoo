import { View, type ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { CardContent } from "@/components/primitives/card-content";
import { CardHeader } from "@/components/primitives/card-header";
import { useAppTheme } from "@/theme/ThemeProvider";

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
  const { theme } = useAppTheme();

  return (
    <Card
      style={[
        {
          gap: theme.spacing[3],
        },
        style,
      ]}
      {...props}
    >
      <CardHeader
        eyebrow={eyebrow}
        subtitle={description}
        title={title}
        trailing={action}
      />
      <CardContent>{children}</CardContent>
    </Card>
  );
}
