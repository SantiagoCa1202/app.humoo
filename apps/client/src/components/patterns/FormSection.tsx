import { View, type ViewProps } from "react-native";

import { AppText } from "@/components/primitives/AppText";

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
          gap: 12,
        },
        style,
      ]}
      {...props}
    >
      {title || description ? (
        <View style={{ gap: 4 }}>
          {title ? <AppText variant="subtitle">{title}</AppText> : null}
          {description ? <AppText muted>{description}</AppText> : null}
        </View>
      ) : null}
      <View style={{ gap: 12 }}>{children}</View>
      {footer}
    </View>
  );
}
