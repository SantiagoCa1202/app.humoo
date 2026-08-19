import { Stack } from "expo-router";

import { RouteGroupGate } from "@/navigation/route-access";

export default function OnboardingLayout() {
  return (
    <RouteGroupGate mode="onboarding">
      <Stack screenOptions={{ headerShown: false }} />
    </RouteGroupGate>
  );
}
