import { Stack } from "expo-router";

import { RouteGroupGate } from "@/navigation/route-access";

export default function PrivateLayout() {
  return (
    <RouteGroupGate mode="app">
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="chat" />
        <Stack.Screen name="operations" />
        <Stack.Screen name="events" />
        <Stack.Screen name="clients" />
        <Stack.Screen name="contacts" />
        <Stack.Screen name="venues" />
        <Stack.Screen name="calendar" />
        <Stack.Screen name="settings" />
        <Stack.Screen name="profile" />
        <Stack.Screen name="index" />
      </Stack>
    </RouteGroupGate>
  );
}
