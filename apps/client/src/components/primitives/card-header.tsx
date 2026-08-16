import { View } from "react-native";

import { Heading } from "@/components/primitives/heading";
import { Text } from "@/components/primitives/text";
import { useAppTheme } from "@/theme/ThemeProvider";

type CardSectionPadding = "none" | "sm" | "md" | "lg" | "xl";

export type CardHeaderProps = {
  children?: React.ReactNode;
  eyebrow?: React.ReactNode;
  leading?: React.ReactNode;
  padding?: CardSectionPadding;
  subtitle?: React.ReactNode;
  title?: React.ReactNode;
  trailing?: React.ReactNode;
};

function getPaddingValue(
  theme: ReturnType<typeof useAppTheme>["theme"],
  padding: CardSectionPadding
) {
  if (padding === "none") {
    return theme.spacing[0];
  }

  if (padding === "sm") {
    return theme.spacing[3];
  }

  if (padding === "md") {
    return theme.spacing[4];
  }

  if (padding === "xl") {
    return theme.spacing[8];
  }

  return theme.spacing[6];
}

export function CardHeader({
  children,
  eyebrow,
  leading,
  padding = "none",
  subtitle,
  title,
  trailing,
}: CardHeaderProps) {
  const { theme } = useAppTheme();
  const paddingValue = getPaddingValue(theme, padding);

  return (
    <View
      style={{
        gap: theme.spacing[3],
        padding: paddingValue,
      }}
    >
      <View
        style={{
          alignItems: "flex-start",
          flexDirection: "row",
          flexWrap: "wrap",
          gap: theme.spacing[3],
          justifyContent: "space-between",
        }}
      >
        {leading ? <View>{leading}</View> : null}
        <View style={{ flex: 1, gap: theme.spacing[1], minWidth: 0 }}>
          {typeof title === "string" ||
          typeof subtitle === "string" ||
          typeof eyebrow === "string" ? (
            <Heading
              eyebrow={eyebrow}
              level="h4"
              subtitle={subtitle}
              title={title}
            />
          ) : (
            <>
              {eyebrow ? (
                <Text tone="primary" variant="overline">
                  {eyebrow}
                </Text>
              ) : null}
              {title ? <View>{title}</View> : null}
              {subtitle ? (
                <Text tone="secondary" variant="bodySmall">
                  {subtitle}
                </Text>
              ) : null}
            </>
          )}
        </View>
        {trailing ? <View>{trailing}</View> : null}
      </View>
      {children}
    </View>
  );
}
