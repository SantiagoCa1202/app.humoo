import { Feather } from "@expo/vector-icons";
import { useState } from "react";
import {
  Platform,
  Pressable,
  TextInput,
  type TextStyle,
  View,
} from "react-native";
import { useTranslation } from "react-i18next";

import { Spinner } from "@/components/primitives/spinner";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type ComposerIconButtonProps = {
  accessibilityHint?: string;
  accessibilityLabel: string;
  children: React.ReactNode;
  disabled?: boolean;
  onPress?: () => void;
  primary?: boolean;
};

function ComposerIconButton({
  accessibilityHint,
  accessibilityLabel,
  children,
  disabled = false,
  onPress,
  primary = false,
}: ComposerIconButtonProps) {
  const { theme } = useAppTheme();

  return (
    <Pressable
      accessibilityHint={accessibilityHint}
      accessibilityLabel={accessibilityLabel}
      accessibilityRole="button"
      accessibilityState={{ disabled }}
      disabled={disabled}
      onPress={onPress}
      style={({ hovered, pressed }) => ({
        alignItems: "center",
        backgroundColor: primary
          ? disabled
            ? theme.colors.interaction.disabledBackground
            : pressed
              ? theme.colors.brand.pressed
              : hovered
                ? theme.colors.brand.hover
                : theme.colors.brand.primary
          : pressed || hovered
            ? theme.colors.interaction.hover
            : "transparent",
        borderCurve: "continuous",
        borderRadius: theme.radius.full,
        height: theme.layout.iconButtonMinSize,
        justifyContent: "center",
        opacity: disabled ? 0.58 : 1,
        width: theme.layout.iconButtonMinSize,
      })}
    >
      {children}
    </Pressable>
  );
}

export type ChatComposerProps = {
  disabled?: boolean;
  onChangeText: (value: string) => void;
  onSend: () => void;
  placeholder?: string;
  sending?: boolean;
  value: string;
};

export function ChatComposer({
  disabled = false,
  onChangeText,
  onSend,
  placeholder,
  sending = false,
  value,
}: ChatComposerProps) {
  const { t } = useTranslation("app");
  const { theme } = useAppTheme();
  const [focused, setFocused] = useState(false);
  const [inputHeight, setInputHeight] = useState<number>(
    theme.layout.controlHeight,
  );
  const canSend = value.trim().length > 0 && !disabled && !sending;
  const surfaceColor = theme.isDark
    ? theme.colors.background.muted
    : theme.colors.background.surface;
  const foregroundColor = theme.colors.text.primary;
  const mutedColor = theme.colors.text.muted;

  return (
    <View
      style={{
        alignSelf: "center",
        maxWidth: theme.layout.content.chat,
        width: "100%",
      }}
    >
      <View
        style={{
          alignItems: "flex-end",
          backgroundColor: surfaceColor,
          borderColor: focused
            ? theme.colors.brand.primary
            : theme.colors.border.subtle,
          borderCurve: "continuous",
          borderRadius: theme.radius["2xl"],
          borderWidth: focused ? 2 : 1,
          flexDirection: "row",
          gap: theme.spacing[1],
          minHeight: theme.layout.controlHeight + theme.spacing[2],
          padding: theme.spacing[1],
        }}
      >
        <ComposerIconButton
          accessibilityHint={t("chatComposerAttachmentsUnavailable")}
          accessibilityLabel={t("chatComposerAddAttachment")}
          disabled
        >
          <Feather color={mutedColor} name="plus" size={theme.iconSizes.md} />
        </ComposerIconButton>

        <TextInput
          accessibilityLabel={t("chatComposerTitle")}
          accessibilityState={{ disabled: disabled || sending }}
          editable={!disabled && !sending}
          multiline
          onBlur={() => setFocused(false)}
          onChangeText={onChangeText}
          onContentSizeChange={(event) => {
            const nextHeight = Math.max(
              theme.layout.controlHeight,
              Math.min(
                event.nativeEvent.contentSize.height,
                theme.spacing[16] * 3,
              ),
            );
            setInputHeight(nextHeight);
          }}
          onFocus={() => setFocused(true)}
          onSubmitEditing={() => {
            if (canSend) {
              onSend();
            }
          }}
          placeholder={placeholder ?? t("chatComposerPlaceholder")}
          placeholderTextColor={mutedColor}
          returnKeyType="send"
          selectionColor={theme.colors.brand.primary}
          style={{
            color: foregroundColor,
            flex: 1,
            fontFamily: theme.typography.family.interfaceRegular,
            fontSize: theme.typography.styles.body.fontSize,
            lineHeight: theme.typography.styles.body.lineHeight,
            maxHeight: theme.spacing[16] * 3,
            minHeight: theme.layout.controlHeight,
            ...(Platform.OS === "web"
              ? ({ outlineStyle: "none" } as unknown as TextStyle)
              : {}),
            opacity: disabled || sending ? 0.58 : 1,
            paddingHorizontal: theme.spacing[2],
            paddingVertical: theme.spacing[2],
            textAlignVertical: "center",
            ...(value
              ? { height: inputHeight, textAlignVertical: "top" as const }
              : {}),
          }}
          submitBehavior="submit"
          value={value}
        />

        <View
          style={{
            alignItems: "center",
            flexDirection: "row",
            gap: theme.spacing[1],
          }}
        >
          <ComposerIconButton
            accessibilityHint={t("chatComposerVoiceUnavailable")}
            accessibilityLabel={t("chatComposerMicrophone")}
            disabled
          >
            <Feather color={mutedColor} name="mic" size={theme.iconSizes.sm} />
          </ComposerIconButton>

          <ComposerIconButton
            accessibilityLabel={t("chatComposerSend")}
            disabled={!canSend}
            onPress={onSend}
            primary
          >
            {sending ? (
              <Spinner size="sm" variant="inverse" />
            ) : (
              <Feather
                color={theme.colors.text.inverse}
                name="arrow-up"
                size={theme.iconSizes.sm}
              />
            )}
          </ComposerIconButton>
        </View>
      </View>
      <Text
        selectable
        tone="muted"
        variant="caption"
        style={{ marginTop: theme.spacing[1], textAlign: "center" }}
      >
        {t("chatComposerHelper")}
      </Text>
    </View>
  );
}
