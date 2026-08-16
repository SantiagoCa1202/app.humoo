import { View, useWindowDimensions } from "react-native";

import { AppLogo } from "@/components/patterns/AppLogo";
import { BrandOrbits } from "@/components/patterns/BrandOrbits";
import { Card } from "@/components/patterns/Card";
import { Screen } from "@/components/patterns/Screen";
import { AppText } from "@/components/primitives/AppText";
import { useAppTheme } from "@/theme/ThemeProvider";

type AuthLayoutProps = {
  title: string;
  description: string;
  children: React.ReactNode;
};

export function AuthLayout({
  title,
  description,
  children,
}: AuthLayoutProps) {
  const { theme } = useAppTheme();
  const { width } = useWindowDimensions();
  const isDesktop = width >= 980;

  return (
    <Screen>
      <View
        style={{
          flex: 1,
          justifyContent: "center",
          paddingVertical: 24,
        }}
      >
        <View
          style={{
            alignItems: "stretch",
            flexDirection: isDesktop ? "row" : "column",
            gap: 20,
            marginHorizontal: "auto",
            maxWidth: theme.layout.authMaxWidth,
            width: "100%",
          }}
        >
          <Card
            style={{
              backgroundColor: theme.components.heroPanel.background,
              borderColor: theme.components.heroPanel.border,
              flex: isDesktop ? 1.05 : undefined,
              gap: 18,
              minHeight: isDesktop ? 640 : 260,
              overflow: "hidden",
            }}
          >
            <BrandOrbits size={300} />
            <AppText
              variant="overline"
              style={{ color: theme.components.heroPanel.text }}
            >
              Conversational AI
            </AppText>
            <AppLogo width={220} />
            <View style={{ gap: 10, marginTop: "auto", maxWidth: 420 }}>
              <AppText
                variant="hero"
                style={{ color: theme.components.heroPanel.text }}
              >
                {title}
              </AppText>
              <AppText
                variant="bodyLarge"
                style={{ color: theme.components.heroPanel.text }}
              >
                {description}
              </AppText>
            </View>
          </Card>
          <Card
            style={{
              alignSelf: "center",
              gap: 18,
              maxWidth: 560,
              padding: 28,
              width: "100%",
            }}
          >
            <AppText
              variant="overline"
              style={{ color: theme.colors.brand.primary }}
            >
              Brand identity
            </AppText>
            <AppLogo width={178} />
            <View style={{ gap: 10 }}>
              <AppText variant="title">{title}</AppText>
              <AppText muted variant="bodyLarge">
                {description}
              </AppText>
            </View>
            {children}
          </Card>
        </View>
      </View>
    </Screen>
  );
}
