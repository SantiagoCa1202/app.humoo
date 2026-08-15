import { Redirect, Stack } from "expo-router";

import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import { useAuth } from "@/auth/useAuth";

export default function PublicLayout() {
  const { isBootstrapping, session } = useAuth();

  if (isBootstrapping) {
    return <FullScreenLoader label="Loading Humoo..." />;
  }

  if (session?.currentWorkspace) {
    return <Redirect href="/(app)/chat" />;
  }

  if (session) {
    return <Redirect href="/(onboarding)/organization" />;
  }

  return <Stack screenOptions={{ headerShown: false }} />;
}
