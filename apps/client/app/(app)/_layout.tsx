import { Stack } from "expo-router";

import { RouteGroupGate } from "@/navigation/route-access";

export default function PrivateLayout() {
  return (
    <RouteGroupGate mode="app">
      <Stack screenOptions={{ headerShown: false }}>
        <Stack.Screen name="chat" />
        <Stack.Screen name="directory" />
        <Stack.Screen name="operations" />
        <Stack.Screen name="team" />
        <Stack.Screen name="stations" />
        <Stack.Screen name="availability" />
        <Stack.Screen name="shifts" />
        <Stack.Screen name="events" />
        <Stack.Screen name="menus" />
        <Stack.Screen name="prep" />
        <Stack.Screen name="tasks" />
        <Stack.Screen name="documents" />
        <Stack.Screen name="recipes" />
        <Stack.Screen name="clients" />
        <Stack.Screen name="contacts" />
        <Stack.Screen name="venues" />
        <Stack.Screen name="calendar" />
        <Stack.Screen name="billing" />
        <Stack.Screen name="audit" />
        <Stack.Screen name="settings" />
        <Stack.Screen name="search" />
        <Stack.Screen name="notifications" />
        <Stack.Screen name="profile" />
        <Stack.Screen name="index" />
      </Stack>
    </RouteGroupGate>
  );
}
