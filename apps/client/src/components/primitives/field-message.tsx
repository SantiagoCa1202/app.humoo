import { View } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

export type FieldMessageProps = {
  count?: number;
  error?: string;
  helperText?: string;
  maxLength?: number;
};

export function FieldMessage({
  count,
  error,
  helperText,
  maxLength,
}: FieldMessageProps) {
  const { theme } = useAppTheme();
  const showCounter = typeof count === "number" || typeof maxLength === "number";
  const counterValue = typeof count === "number" ? count : 0;
  const isAtLimit =
    typeof maxLength === "number" ? counterValue >= maxLength : false;

  if (!error && !helperText && !showCounter) {
    return null;
  }

  return (
    <View
      style={{
        alignItems: "flex-start",
        flexDirection: "row",
        gap: theme.spacing[2],
        justifyContent: "space-between",
      }}
    >
      <View style={{ flex: 1 }}>
        {error ? (
          <Text
            accessibilityLiveRegion="polite"
            tone="danger"
            variant="caption"
          >
            {error}
          </Text>
        ) : helperText ? (
          <Text tone="muted" variant="caption">
            {helperText}
          </Text>
        ) : null}
      </View>
      {showCounter ? (
        <Text
          tone={isAtLimit ? "danger" : "muted"}
          variant="caption"
          style={{
            fontVariant: ["tabular-nums"],
          }}
        >
          {typeof maxLength === "number"
            ? `${counterValue}/${maxLength}`
            : `${counterValue}`}
        </Text>
      ) : null}
    </View>
  );
}
