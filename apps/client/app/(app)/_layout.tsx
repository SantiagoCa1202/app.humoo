import { Redirect, Stack } from "expo-router";

import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import { useAuth } from "@/auth/useAuth";

export default function PrivateLayout() {
  const { isBootstrapping, session } = useAuth();

  if (isBootstrapping) {
    return <FullScreenLoader label="Loading Humoo..." />;
  }

  if (!session) {
    return <Redirect href="/(public)/welcome" />;
  }

  if (!session.currentWorkspace) {
    return <Redirect href="/(onboarding)/organization" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}
