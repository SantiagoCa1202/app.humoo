import { Redirect, type Href } from "expo-router";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import { useWorkspace } from "@/features/workspace";
import { routes } from "@/navigation/routes";

export type SessionStatus = "loading" | "authenticated" | "unauthenticated";
export type RouteGroupMode = "public" | "app" | "onboarding";

export type RouteAccessState = {
  isBootstrapping: boolean;
  sessionStatus: SessionStatus;
  workspaceStatus: "loading" | "ready" | "workspace_required" | "error";
};

export function useRouteAccessState(): RouteAccessState {
  const { isBootstrapping, session } = useAuth();
  const workspace = useWorkspace();

  if (isBootstrapping) {
    return {
      isBootstrapping: true,
      sessionStatus: "loading",
      workspaceStatus: "loading",
    };
  }

  if (!session) {
    return {
      isBootstrapping: false,
      sessionStatus: "unauthenticated",
      workspaceStatus: "loading",
    };
  }

  return {
    isBootstrapping: workspace.status === "loading",
    sessionStatus: "authenticated",
    workspaceStatus: workspace.status,
  };
}

export function resolveBootstrapHref(state: RouteAccessState): Href {
  if (state.sessionStatus !== "authenticated") {
    return routes.public.login;
  }

  if (state.workspaceStatus !== "ready") {
    return routes.onboarding.organization;
  }

  return routes.app.chat;
}

export function RouteGroupGate({
  children,
  mode,
}: {
  children: React.ReactNode;
  mode: RouteGroupMode;
}) {
  const { t } = useTranslation("app");
  const accessState = useRouteAccessState();

  if (accessState.isBootstrapping) {
    return <FullScreenLoader label={t("routing.loading")} />;
  }

  if (mode === "public") {
    if (accessState.sessionStatus === "authenticated") {
      return <Redirect href={resolveBootstrapHref(accessState)} />;
    }

    return <>{children}</>;
  }

  if (mode === "onboarding") {
    if (accessState.sessionStatus !== "authenticated") {
      return <Redirect href={routes.public.login} />;
    }

    if (accessState.workspaceStatus === "ready") {
      return <Redirect href={routes.app.chat} />;
    }

    return <>{children}</>;
  }

  if (accessState.sessionStatus !== "authenticated") {
    return <Redirect href={routes.public.login} />;
  }

  if (accessState.workspaceStatus !== "ready") {
    return <Redirect href={routes.onboarding.organization} />;
  }

  return <>{children}</>;
}
