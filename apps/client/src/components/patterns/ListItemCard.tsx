import { View, type ViewProps } from "react-native";

import { Card } from "@/components/patterns/Card";
import { CardContent } from "@/components/primitives/card-content";
import { CardFooter } from "@/components/primitives/card-footer";
import { CardHeader } from "@/components/primitives/card-header";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

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
  const { theme } = useAppTheme();

  return (
    <Card
      style={[
        {
          gap: theme.spacing[2],
          padding: theme.spacing[4],
        },
        style,
      ]}
      {...props}
    >
      <CardHeader subtitle={subtitle} title={title} trailing={aside} />
      <CardContent>
        {meta.length ? (
          <View style={{ gap: theme.spacing[1] }}>
            {meta.map((line) => (
              <Text key={line} tone="muted" variant="bodySmall">
                {line}
              </Text>
            ))}
          </View>
        ) : null}
        {children}
      </CardContent>
      {footer ? <CardFooter>{footer}</CardFooter> : null}
    </Card>
  );
}
