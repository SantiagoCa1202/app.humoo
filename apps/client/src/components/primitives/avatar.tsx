import { Image, View, type ImageSourcePropType } from "react-native";

import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";
import { getSemanticToneAppearance, type SemanticStatusTone } from "@/theme/status-config";

type AvatarSize = "xs" | "sm" | "md" | "lg" | "xl";
type AvatarShape = "circle" | "rounded";
type AvatarStatus = "online" | "away" | "busy" | "offline";
type AvatarVariant = SemanticStatusTone;

export type AvatarProps = {
  name?: string | null;
  shape?: AvatarShape;
  showBorder?: boolean;
  size?: AvatarSize;
  source?: ImageSourcePropType;
  status?: AvatarStatus;
  variant?: AvatarVariant;
};

function getInitials(name?: string | null) {
  if (!name?.trim()) {
    return "?";
  }

  const parts = name.trim().split(/\s+/).slice(0, 2);
  return parts.map((part) => part.charAt(0).toUpperCase()).join("");
}

function getAvatarMetrics(theme: ReturnType<typeof useAppTheme>["theme"], size: AvatarSize) {
  if (size === "xs") {
    return {
      container: theme.spacing[8],
      dot: theme.spacing[2],
      text: "caption" as const,
    };
  }

  if (size === "sm") {
    return {
      container: theme.spacing[10],
      dot: theme.spacing[2],
      text: "caption" as const,
    };
  }

  if (size === "lg") {
    return {
      container: theme.spacing[16],
      dot: theme.spacing[3],
      text: "title" as const,
    };
  }

  if (size === "xl") {
    return {
      container: theme.spacing[16],
      dot: theme.spacing[4],
      text: "h3" as const,
    };
  }

  return {
    container: theme.spacing[12],
    dot: theme.spacing[3],
    text: "label" as const,
  };
}

function getPresenceTone(status: AvatarStatus): SemanticStatusTone {
  if (status === "online") {
    return "success";
  }

  if (status === "away") {
    return "warning";
  }

  if (status === "busy") {
    return "danger";
  }

  return "neutral";
}

export function Avatar({
  name,
  shape = "circle",
  showBorder = false,
  size = "md",
  source,
  status,
  variant = "neutral",
}: AvatarProps) {
  const { theme } = useAppTheme();
  const metrics = getAvatarMetrics(theme, size);
  const appearance = getSemanticToneAppearance(theme, variant);
  const statusAppearance = status
    ? getSemanticToneAppearance(theme, getPresenceTone(status))
    : null;
  const borderRadius = shape === "circle" ? theme.radius.full : theme.radius.lg;
  const initials = getInitials(name);

  return (
    <View
      style={{
        alignSelf: "flex-start",
        position: "relative",
      }}
    >
      <View
        style={{
          alignItems: "center",
          backgroundColor: appearance.background,
          borderColor: showBorder ? theme.colors.background.surface : appearance.border,
          borderCurve: "continuous",
          borderRadius,
          borderWidth: showBorder ? 2 : 1,
          height: metrics.container,
          justifyContent: "center",
          overflow: "hidden",
          width: metrics.container,
        }}
      >
        {source ? (
          <Image
            resizeMode="cover"
            source={source}
            style={{
              height: "100%",
              width: "100%",
            }}
          />
        ) : (
          <Text
            tone="default"
            variant={metrics.text}
            style={{ color: appearance.accent }}
          >
            {initials}
          </Text>
        )}
      </View>
      {statusAppearance ? (
        <View
          style={{
            backgroundColor: statusAppearance.accent,
            borderColor: theme.colors.background.surface,
            borderRadius: theme.radius.full,
            borderWidth: 2,
            bottom: 0,
            height: metrics.dot + theme.spacing[1],
            position: "absolute",
            right: 0,
            width: metrics.dot + theme.spacing[1],
          }}
        />
      ) : null}
    </View>
  );
}
