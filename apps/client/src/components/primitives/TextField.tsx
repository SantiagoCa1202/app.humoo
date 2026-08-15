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
  style,
  ...props
}: TextFieldProps) {
  const { theme } = useAppTheme();
  const [isSecure, setIsSecure] = useState(Boolean(secure));

  return (
    <View style={{ gap: 8 }}>
      <AppText variant="subtitle">{label}</AppText>
      <View
        style={{
          alignItems: "center",
          backgroundColor: theme.colors.surface,
          borderColor: error ? theme.colors.danger : theme.colors.border,
          borderRadius: theme.radius.pill,
          borderWidth: 1,
          flexDirection: "row",
          minHeight: theme.layout.controlHeight,
          paddingHorizontal: 18,
        }}
      >
        <TextInput
          placeholderTextColor={theme.colors.textMuted}
          secureTextEntry={isSecure}
          style={[
            {
              color: theme.colors.text,
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
          <Pressable onPress={() => setIsSecure((value) => !value)}>
            <AppText muted variant="caption">
              {isSecure ? "Show" : "Hide"}
            </AppText>
          </Pressable>
        ) : null}
      </View>
      {error ? (
        <AppText style={{ color: theme.colors.danger }}>{error}</AppText>
      ) : null}
      {!error && hint ? <AppText muted>{hint}</AppText> : null}
    </View>
  );
}
