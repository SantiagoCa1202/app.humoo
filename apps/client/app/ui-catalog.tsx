import { Stack } from "expo-router";

import { UiCatalogScreen } from "@/features/ui-catalog/screens/UiCatalogScreen";

export default function UiCatalogRoute() {
  return (
    <>
      <Stack.Screen options={{ headerShown: false, title: "UI Catalog" }} />
      <UiCatalogScreen />
    </>
  );
}
