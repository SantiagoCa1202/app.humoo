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
  const isDesktop = width >= theme.breakpoints.lg;

  return (
    <Screen>
      <View
        style={{
          flex: 1,
          justifyContent: "center",
          paddingVertical: theme.spacing[6],
        }}
      >
        <View
          style={{
            alignItems: "stretch",
            flexDirection: isDesktop ? "row" : "column",
            gap: theme.spacing[5],
            marginHorizontal: "auto",
            maxWidth: theme.layout.content.maxWidth,
            width: "100%",
          }}
        >
          <Card
            style={{
              backgroundColor: theme.components.heroPanel.background,
              borderColor: theme.components.heroPanel.border,
              flex: isDesktop ? 1.05 : undefined,
              gap: theme.spacing[4],
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
            <View
              style={{
                gap: theme.spacing[2],
                marginTop: "auto",
                maxWidth: theme.layout.content.reading,
              }}
            >
              <AppText
                variant="hero"
                style={{ color: theme.components.heroPanel.text }}
              >
                {title}
              </AppText>
              <AppText
                variant="bodyMedium"
                style={{ color: theme.components.heroPanel.text }}
              >
                {description}
              </AppText>
            </View>
          </Card>
          <Card
            style={{
              alignSelf: "center",
              gap: theme.spacing[4],
              maxWidth: theme.layout.content.form,
              padding: theme.spacing[6],
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
            <View style={{ gap: theme.spacing[2] }}>
              <AppText variant="title">{title}</AppText>
              <AppText muted variant="bodyMedium">
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
