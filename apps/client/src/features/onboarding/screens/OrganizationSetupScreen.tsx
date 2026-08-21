import { router } from "expo-router";
import { useEffect, useState } from "react";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import {
  WorkspaceCreateScreen,
  WorkspaceSelectScreen,
  useWorkspace,
} from "@/features/workspace";
import { routes } from "@/navigation/routes";

export default function OrganizationSetupScreen() {
  const { t } = useTranslation("app");
  const { session, signOut } = useAuth();
  const {
    acceptInvitation,
    createWorkspace,
    errorMessage,
    refreshWorkspaces,
    setActiveWorkspace,
    status,
    workspaces,
  } = useWorkspace();
  const [showCreateScreen, setShowCreateScreen] = useState(false);

  useEffect(() => {
    if (workspaces.length === 0) {
      setShowCreateScreen(true);
    }
  }, [workspaces.length]);

  if (status === "loading") {
    return <FullScreenLoader label={t("routing.loading")} />;
  }

  if (showCreateScreen || workspaces.length === 0) {
    return (
      <WorkspaceCreateScreen
        canGoBack={workspaces.length > 0}
        defaultCurrency="USD"
        defaultLocale={session?.user.preferredLocale === "es" ? "es" : "en"}
        defaultTimezone={session?.user.timezone ?? "UTC"}
        errorMessage={errorMessage}
        onBack={() => setShowCreateScreen(false)}
        onCreateWorkspace={createWorkspace}
      />
    );
  }

  return (
    <WorkspaceSelectScreen
      activeWorkspaceId={session?.currentWorkspace?.id ?? null}
      errorMessage={errorMessage}
      onAcceptInvitation={acceptInvitation}
      onCreateWorkspace={() => setShowCreateScreen(true)}
      onRefresh={refreshWorkspaces}
      onSelectWorkspace={setActiveWorkspace}
      onSignOut={async () => {
        await signOut();
        router.replace(routes.public.login);
      }}
      workspaces={workspaces}
    />
  );
}
