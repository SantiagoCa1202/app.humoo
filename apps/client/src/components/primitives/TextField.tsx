import { useState } from "react";
import { Pressable, TextInput, View, type TextInputProps } from "react-native";

import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type TextFieldProps = TextInputProps & {
  label: string;
  hint?: string;
  error?: string;
  secure?: boolean;
};

export function TextField({
  label,
  hint,
  error,
  secure,
  editable = true,
  style,
  ...props
}: TextFieldProps) {
  const { theme } = useAppTheme();
  const [isSecure, setIsSecure] = useState(Boolean(secure));
  const [isFocused, setIsFocused] = useState(false);
  const inputTokens = theme.components.input;
  const isDisabled = editable === false;
  const borderColor = error
    ? inputTokens.errorBorder
    : isFocused
    ? inputTokens.focusBorder
    : inputTokens.border;

  return (
    <View style={{ gap: theme.spacing[2] }}>
      <AppText variant="label">{label}</AppText>
      <View
        style={{
          alignItems: "center",
          backgroundColor: isDisabled
            ? inputTokens.disabledBackground
            : inputTokens.background,
          borderCurve: "continuous",
          borderColor,
          borderRadius: theme.radius.md,
          borderWidth: 1,
          flexDirection: "row",
          minHeight: theme.layout.controlHeight,
          paddingHorizontal: theme.spacing[4],
        }}
      >
        <TextInput
          editable={editable}
          onBlur={(event) => {
            setIsFocused(false);
            props.onBlur?.(event);
          }}
          onFocus={(event) => {
            setIsFocused(true);
            props.onFocus?.(event);
          }}
          placeholderTextColor={inputTokens.placeholder}
          selectionColor={inputTokens.selection}
          secureTextEntry={isSecure}
          style={[
            {
              color: isDisabled ? inputTokens.disabledText : inputTokens.text,
              flex: 1,
              ...theme.typography.styles.body,
              minHeight: theme.layout.controlHeight,
            },
            style,
          ]}
          {...props}
        />
        {secure ? (
          <Pressable
            disabled={isDisabled}
            onPress={() => setIsSecure((value) => !value)}
          >
            <AppText
              style={{
                color: isDisabled
                  ? inputTokens.disabledText
                  : theme.colors.text.secondary,
              }}
              variant="bodySmall"
            >
              {isSecure ? "Show" : "Hide"}
            </AppText>
          </Pressable>
        ) : null}
      </View>
      {error ? (
        <AppText style={{ color: inputTokens.errorText }}>{error}</AppText>
      ) : null}
      {!error && hint ? <AppText muted>{hint}</AppText> : null}
    </View>
  );
}
