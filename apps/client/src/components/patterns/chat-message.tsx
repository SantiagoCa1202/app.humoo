import { View, useWindowDimensions, type ImageSourcePropType, type StyleProp, type ViewStyle } from "react-native";
import { useTranslation } from "react-i18next";

import { Avatar } from "@/components/primitives/avatar";
import { IconButton } from "@/components/primitives/icon-button";
import { Spinner } from "@/components/primitives/spinner";
import { Text, type TextTone } from "@/components/primitives/text";
import { Tooltip } from "@/components/primitives/tooltip";
import { humooContentWidths } from "@/theme";
import { useAppTheme } from "@/theme/ThemeProvider";

export type ChatMessageAction = {
  accessibilityHint?: string;
  accessibilityLabel: string;
  icon: React.ReactNode;
  key: string;
  onPress: () => void | Promise<void>;
  tooltip: string;
};

export type ChatMessageProps = {
  accessibilityLabel?: string;
  actions?: ChatMessageAction[];
  avatarSource?: ImageSourcePropType;
  children: React.ReactNode;
  error?: boolean;
  footer?: React.ReactNode;
  maxWidth?: number;
  name?: React.ReactNode;
  role: "user" | "assistant";
  showActions?: boolean;
  showAvatar?: boolean;
  showHeader?: boolean;
  streaming?: boolean;
  timestamp?: React.ReactNode;
};

function renderTextContent(content: React.ReactNode, tone: TextTone, color: string) {
  if (typeof content === "string" || typeof content === "number") {
    return (
      <Text
        selectable
        tone={tone}
        variant="body"
        style={{ color }}
      >
        {content}
      </Text>
    );
  }

  return content;
}

function renderTimestamp(timestamp?: React.ReactNode, tone?: TextTone, color?: string) {
  if (timestamp === undefined || timestamp === null || timestamp === "") {
    return null;
  }

  if (typeof timestamp === "string" || typeof timestamp === "number") {
    return (
      <Text
        selectable
        tone={tone ?? "muted"}
        variant="caption"
        style={color ? { color } : undefined}
      >
        {timestamp}
      </Text>
    );
  }

  return timestamp;
}

function ActionGlyph({ children }: { children: React.ReactNode }) {
  return <Text variant="caption">{children}</Text>;
}

export function ChatMessage({
  accessibilityLabel,
  actions,
  avatarSource,
  children,
  error = false,
  footer,
  maxWidth,
  name,
  role,
  showActions = true,
  showAvatar = role === "assistant",
  showHeader = true,
  streaming = false,
  timestamp,
}: ChatMessageProps) {
  const { t } = useTranslation("common");
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const isUser = role === "user";
  const availableWidth = Math.max(theme.spacing[16], width - theme.spacing[12]);
  const resolvedMaxWidth = Math.min(maxWidth ?? humooContentWidths.chat, availableWidth);
  const bubbleBackground = isUser
    ? theme.colors.chat.userBackground
    : error
    ? theme.colors.status.dangerSoft
    : "transparent";
  const bubbleBorder = error
    ? theme.colors.status.danger
    : isUser
    ? theme.colors.chat.userBackground
    : "transparent";
  const contentColor = isUser
    ? theme.colors.chat.userText
    : theme.colors.chat.assistantText;
  const headerTone: TextTone = isUser ? "inverse" : "secondary";
  const timestampTone: TextTone = isUser ? "inverse" : "muted";
  const statusColor = error ? theme.colors.status.danger : theme.colors.text.secondary;
  const showActionRow = showActions && Boolean(actions?.length);

  return (
    <View
      accessibilityLabel={
        accessibilityLabel ??
        t("chat.message.accessibilityLabel", {
          role: isUser ? t("chat.roles.user") : t("chat.roles.assistant"),
        })
      }
      style={{
        alignItems: isUser ? "flex-end" : "flex-start",
        width: "100%",
      }}
    >
      <View
        style={{
          alignItems: "flex-end",
          flexDirection: isUser ? "row-reverse" : "row",
          gap: theme.spacing[3],
          maxWidth: resolvedMaxWidth,
          width: "100%",
        }}
      >
        {showAvatar ? (
          <Avatar
            name={typeof name === "string" ? name : undefined}
            showBorder
            size="sm"
            source={avatarSource}
            variant={isUser ? "neutral" : "primary"}
          />
        ) : null}
        <View
          style={{
            alignItems: isUser ? "flex-end" : "flex-start",
            flex: showAvatar ? 1 : undefined,
            gap: theme.spacing[2],
            maxWidth: showAvatar ? undefined : resolvedMaxWidth,
          }}
        >
          {showHeader && (name || timestamp) ? (
            <View
              style={{
                alignItems: "center",
                flexDirection: "row",
                flexWrap: "wrap",
                gap: theme.spacing[2],
                justifyContent: isUser ? "flex-end" : "flex-start",
              }}
            >
              {name ? (
                typeof name === "string" || typeof name === "number" ? (
                  <Text
                    selectable
                    tone={headerTone}
                    variant="label"
                    style={isUser ? { color: contentColor } : undefined}
                  >
                    {name}
                  </Text>
                ) : (
                  name
                )
              ) : null}
              {renderTimestamp(timestamp, timestampTone, isUser ? contentColor : undefined)}
            </View>
          ) : null}
          <View
            style={[
              {
                alignSelf: isUser ? "flex-end" : "stretch",
                backgroundColor: bubbleBackground,
                borderColor: bubbleBorder,
                borderCurve: "continuous",
                borderRadius: theme.radius.lg,
                borderWidth: error || isUser ? 1 : 0,
                gap: theme.spacing[2],
                maxWidth: resolvedMaxWidth,
                paddingHorizontal: isUser || error ? theme.spacing[4] : theme.spacing[0],
                paddingVertical: isUser || error ? theme.spacing[3] : theme.spacing[0],
              },
            ]}
          >
            {renderTextContent(children, isUser ? "inverse" : "default", contentColor)}
            {footer}
          </View>
          {streaming ? (
            <View
              accessibilityLabel={t("chat.streaming.accessibilityLabel")}
              style={{
                alignItems: "center",
                flexDirection: "row",
                gap: theme.spacing[2],
              }}
            >
              <Spinner
                accessibilityLabel={t("chat.streaming.accessibilityLabel")}
                size="sm"
                variant="neutral"
              />
              <Text tone="secondary" variant="caption">
                {t("chat.streaming.label")}
              </Text>
            </View>
          ) : null}
          {error ? (
            <Text
              selectable
              tone="danger"
              variant="caption"
              style={{ color: statusColor }}
            >
              {t("chat.error.label")}
            </Text>
          ) : null}
          {showActionRow ? (
            <View
              style={{
                alignItems: "center",
                flexDirection: "row",
                flexWrap: "wrap",
                gap: theme.spacing[2],
                justifyContent: isUser ? "flex-end" : "flex-start",
              }}
            >
              {actions?.map((action) => (
                <Tooltip
                  content={action.tooltip}
                  key={action.key}
                >
                  <IconButton
                    accessibilityHint={action.accessibilityHint}
                    accessibilityLabel={action.accessibilityLabel}
                    icon={action.icon}
                    onPress={action.onPress}
                    size="sm"
                    variant="ghost"
                  />
                </Tooltip>
              ))}
            </View>
          ) : null}
        </View>
      </View>
    </View>
  );
}

export function createChatActionIcon(glyph: React.ReactNode) {
  return <ActionGlyph>{glyph}</ActionGlyph>;
}
