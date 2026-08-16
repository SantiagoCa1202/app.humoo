import { Pressable, View } from "react-native";

import {
  Text,
  type TextProps,
  type TextTone,
} from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type HeadingLevel = "display" | "h1" | "h2" | "h3" | "h4";
type HeadingTone = "default" | "secondary" | "primary" | "inverse";
type HeadingAlign = "left" | "center" | "right";

export type HeadingProps = {
  level?: HeadingLevel;
  eyebrow?: React.ReactNode;
  title?: React.ReactNode;
  subtitle?: React.ReactNode;
  actionLabel?: string;
  onActionPress?: () => void;
  tone?: HeadingTone;
  align?: HeadingAlign;
  numberOfLines?: number;
  children?: React.ReactNode;
} & Pick<TextProps, "accessibilityLabel" | "selectable" | "testID">;

export function Heading({
  level = "h2",
  eyebrow,
  title,
  subtitle,
  actionLabel,
  onActionPress,
  tone = "default",
  align = "left",
  numberOfLines,
  children,
  accessibilityLabel,
  selectable,
  testID,
}: HeadingProps) {
  const { theme } = useAppTheme();
  const content = children ?? title;
  const titleTone: TextTone = tone;
  const subtitleTone: TextTone = tone === "inverse" ? "inverse" : "secondary";
  const eyebrowTone: TextTone = tone === "inverse" ? "inverse" : "primary";
  const actionTone: TextTone = tone === "inverse" ? "inverse" : "primary";
  const textAlign = align;
  const showAction = Boolean(actionLabel && onActionPress);

  return (
    <View
      style={{
        alignItems:
          align === "center"
            ? "center"
            : align === "right"
            ? "flex-end"
            : "flex-start",
        gap: theme.spacing[1],
      }}
      testID={testID}
    >
      {eyebrow ? (
        <Text
          accessibilityLabel={accessibilityLabel}
          selectable={selectable}
          tone={eyebrowTone}
          variant="overline"
          style={{ textAlign }}
        >
          {eyebrow}
        </Text>
      ) : null}
      {content ? (
        <View
          style={{
            alignItems:
              align === "center"
                ? "center"
                : align === "right"
                ? "flex-end"
                : "flex-start",
            flexDirection: "row",
            flexWrap: "wrap",
            gap: theme.spacing[2],
            justifyContent:
              align === "center"
                ? "center"
                : align === "right"
                ? "flex-end"
                : "space-between",
            width: "100%",
          }}
        >
          <Text
            accessibilityLabel={accessibilityLabel}
            numberOfLines={numberOfLines}
            selectable={selectable}
            tone={titleTone}
            variant={level}
            style={{
              flexShrink: 1,
              textAlign,
            }}
          >
            {content}
          </Text>
          {showAction ? (
            <Pressable accessibilityRole="button" onPress={onActionPress}>
              <Text tone={actionTone} variant="label">
                {actionLabel}
              </Text>
            </Pressable>
          ) : null}
        </View>
      ) : null}
      {subtitle ? (
        <Text
          numberOfLines={numberOfLines}
          selectable={selectable}
          tone={subtitleTone}
          variant="bodySmall"
          style={{ textAlign }}
        >
          {subtitle}
        </Text>
      ) : null}
    </View>
  );
}
