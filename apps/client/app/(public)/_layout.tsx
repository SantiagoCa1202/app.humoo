import { Stack } from "expo-router";

import { RouteGroupGate } from "@/navigation/route-access";

export default function PublicLayout() {
  return (
    <RouteGroupGate mode="public">
      <Stack screenOptions={{ headerShown: false }} />
    </RouteGroupGate>
  );
}
