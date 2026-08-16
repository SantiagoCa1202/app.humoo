import { View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type FieldLabelProps = {
  label: string;
  optional?: boolean;
  required?: boolean;
};

export function FieldLabel({
  label,
  optional = false,
  required = false,
}: FieldLabelProps) {
  const { theme } = useAppTheme();

  return (
    <View
      style={{
        alignItems: "center",
        flexDirection: "row",
        flexWrap: "wrap",
        gap: theme.spacing[1],
      }}
    >
      <Text variant="label">{label}</Text>
      {required ? (
        <Text tone="primary" variant="label">
          *
        </Text>
      ) : null}
      {!required && optional ? (
        <Text tone="muted" variant="caption">
          Optional
        </Text>
      ) : null}
    </View>
  );
}
