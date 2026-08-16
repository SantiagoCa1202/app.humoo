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
    <View style={{ gap: 8 }}>
      <AppText variant="subtitle">{label}</AppText>
      <View
        style={{
          alignItems: "center",
          backgroundColor: isDisabled
            ? inputTokens.disabledBackground
            : inputTokens.background,
          borderColor,
          borderRadius: theme.radius.pill,
          borderWidth: 1,
          flexDirection: "row",
          minHeight: theme.layout.controlHeight,
          paddingHorizontal: 18,
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
              fontFamily: theme.typography.family.interfaceRegular,
              fontSize: theme.typography.size.body,
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
              variant="caption"
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
