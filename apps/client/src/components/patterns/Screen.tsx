import { ScrollView, View, type ViewStyle } from "react-native";
import { SafeAreaView } from "react-native-safe-area-context";

import { BrandOrbits } from "@/components/patterns/BrandOrbits";
import { useAppTheme } from "@/theme/ThemeProvider";

type ScreenProps = {
  children: React.ReactNode;
  scroll?: boolean;
  contentStyle?: ViewStyle;
};

export function Screen({ children, scroll = true, contentStyle }: ScreenProps) {
  const { theme } = useAppTheme();
  const content = (
    <View
      style={[
        {
          flex: 1,
          marginHorizontal: "auto",
          maxWidth: theme.layout.appMaxWidth,
          padding: theme.layout.screenPadding,
          width: "100%",
        },
        contentStyle,
      ]}
    >
      {children}
    </View>
  );

  return (
    <SafeAreaView style={{ backgroundColor: theme.colors.background, flex: 1 }}>
      <BrandOrbits align="right" compact />
      <BrandOrbits align="left" compact size={180} />
      {scroll ? (
        <ScrollView contentContainerStyle={{ flexGrow: 1 }}>{content}</ScrollView>
      ) : (
        content
      )}
    </SafeAreaView>
  );
}
