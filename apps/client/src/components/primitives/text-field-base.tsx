import { useMemo, useState } from "react";
import {
  Pressable,
  TextInput,
  View,
  type StyleProp,
  type TextInputProps,
  type TextStyle,
  type ViewStyle,
} from "react-native";
import { useTranslation } from "react-i18next";

import { FieldLabel } from "@/components/primitives/field-label";
import { FieldMessage } from "@/components/primitives/field-message";
import { Text } from "@/components/primitives/text";
import { getTypographyStyle } from "@/theme/typography";
import { useAppTheme } from "@/theme/ThemeProvider";

type BaseTextFieldProps = TextInputProps & {
  autoGrow?: boolean;
  containerStyle?: StyleProp<ViewStyle>;
  count?: number;
  error?: string;
  helperText?: string;
  inputStyle?: StyleProp<TextStyle>;
  label?: string;
  leftAdornment?: React.ReactNode;
  maxLength?: number;
  minHeight?: number;
  optional?: boolean;
  required?: boolean;
  rightAdornment?: React.ReactNode;
};

function clampHeight(value: number, minHeight: number) {
  return value < minHeight ? minHeight : value;
}

export function TextFieldBase({
  autoGrow = false,
  containerStyle,
  count,
  error,
  helperText,
  inputStyle,
  label,
  leftAdornment,
  maxLength,
  minHeight,
  multiline = false,
  onBlur,
  onContentSizeChange,
  onFocus,
  optional = false,
  required = false,
  rightAdornment,
  style,
  value,
  editable,
  ...props
}: BaseTextFieldProps) {
  const { theme } = useAppTheme();
  const [isFocused, setIsFocused] = useState(false);
  const [dynamicHeight, setDynamicHeight] = useState<number | undefined>(
    undefined
  );
  const inputTokens = theme.components.input;
  const isDisabled = editable === false;
  const currentCount = count ?? value?.length ?? 0;
  const controlMinHeight = minHeight ?? theme.layout.controlHeight;
  const borderColor = error
    ? inputTokens.errorBorder
    : isFocused
    ? theme.colors.brand.primary
    : inputTokens.border;
  const textInputStyle = useMemo(
    () => [
      getTypographyStyle("body"),
      {
        color: isDisabled ? inputTokens.disabledText : inputTokens.text,
        flex: 1,
        minHeight: multiline ? controlMinHeight : theme.layout.controlHeight,
        paddingVertical: multiline ? theme.spacing[3] : 0,
        textAlignVertical: multiline ? ("top" as const) : ("center" as const),
      },
      style,
      inputStyle,
      autoGrow && dynamicHeight
        ? {
            height: clampHeight(dynamicHeight, controlMinHeight),
          }
        : null,
    ],
    [
      autoGrow,
      controlMinHeight,
      dynamicHeight,
      inputStyle,
      inputTokens.disabledText,
      inputTokens.text,
      isDisabled,
      multiline,
      style,
      theme.layout.controlHeight,
      theme.spacing,
    ]
  );

  return (
    <View style={[{ gap: theme.spacing[2] }, containerStyle]}>
      {label ? (
        <FieldLabel label={label} optional={optional} required={required} />
      ) : null}
      <View
        style={{
          alignItems: multiline ? "flex-start" : "center",
          backgroundColor: isDisabled
            ? inputTokens.disabledBackground
            : inputTokens.background,
          borderColor,
          borderCurve: "continuous",
          borderRadius: theme.radius.md,
          borderWidth: 1,
          flexDirection: "row",
          gap: theme.spacing[2],
          minHeight: controlMinHeight,
          paddingHorizontal: theme.spacing[4],
        }}
      >
        {leftAdornment ? (
          <View
            style={{
              alignItems: "center",
              justifyContent: "center",
              minHeight: theme.layout.controlHeight,
            }}
          >
            {leftAdornment}
          </View>
        ) : null}
        <TextInput
          accessibilityState={{ disabled: isDisabled }}
          editable={!isDisabled}
          multiline={multiline}
          onBlur={(event) => {
            setIsFocused(false);
            onBlur?.(event);
          }}
          onContentSizeChange={(event) => {
            if (autoGrow) {
              setDynamicHeight(event.nativeEvent.contentSize.height);
            }
            onContentSizeChange?.(event);
          }}
          onFocus={(event) => {
            setIsFocused(true);
            onFocus?.(event);
          }}
          placeholderTextColor={inputTokens.placeholder}
          selectionColor={inputTokens.selection}
          style={textInputStyle}
          textAlignVertical={multiline ? "top" : undefined}
          value={value}
          {...props}
        />
        {rightAdornment ? (
          <View
            style={{
              alignItems: "center",
              justifyContent: "center",
              minHeight: theme.layout.controlHeight,
            }}
          >
            {rightAdornment}
          </View>
        ) : null}
      </View>
      <FieldMessage
        count={currentCount}
        error={error}
        helperText={helperText}
        maxLength={maxLength}
      />
    </View>
  );
}

type InputGlyphProps = {
  label: string;
};

export function InputGlyph({ label }: InputGlyphProps) {
  const { theme } = useAppTheme();

  return (
    <Text
      accessibilityElementsHidden
      tone="muted"
      variant="bodySmall"
      style={{
        color: theme.colors.text.muted,
        fontSize: theme.iconSizes.md,
        lineHeight: theme.iconSizes.md,
      }}
    >
      {label}
    </Text>
  );
}

type ToggleAdornmentProps = {
  disabled?: boolean;
  secure: boolean;
  onPress: () => void;
};

export function ToggleAdornment({
  disabled = false,
  secure,
  onPress,
}: ToggleAdornmentProps) {
  const { t } = useTranslation("common");

  return (
    <Pressable
      accessibilityLabel={secure ? t("showPassword") : t("hidePassword")}
      accessibilityRole="button"
      disabled={disabled}
      onPress={onPress}
    >
      <Text tone={disabled ? "muted" : "secondary"} variant="caption">
        {secure ? t("show") : t("hide")}
      </Text>
    </Pressable>
  );
}
