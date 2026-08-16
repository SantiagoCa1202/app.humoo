import { View, type ViewProps } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { spacing } from "@/theme";

type FormSectionProps = ViewProps & {
  title?: string;
  description?: string;
  footer?: React.ReactNode;
  children: React.ReactNode;
};

export function FormSection({
  title,
  description,
  footer,
  children,
  style,
  ...props
}: FormSectionProps) {
  return (
    <View
      style={[
        {
          gap: spacing[3],
        },
        style,
      ]}
      {...props}
    >
      {title || description ? (
        <View style={{ gap: spacing[1] }}>
          {title ? <AppText variant="h4">{title}</AppText> : null}
          {description ? <AppText muted>{description}</AppText> : null}
        </View>
      ) : null}
      <View style={{ gap: spacing[3] }}>{children}</View>
      {footer}
    </View>
  );
}
