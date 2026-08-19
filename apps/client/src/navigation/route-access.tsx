import { Redirect, type Href } from "expo-router";
import { useTranslation } from "react-i18next";

import { useAuth } from "@/auth/useAuth";
import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import { routes } from "@/navigation/routes";

export type SessionStatus = "loading" | "authenticated" | "unauthenticated";
export type RouteGroupMode = "public" | "app" | "onboarding";

export type RouteAccessState = {
  isBootstrapping: boolean;
  sessionStatus: SessionStatus;
};

export function useRouteAccessState(): RouteAccessState {
  const { isBootstrapping, session } = useAuth();

  if (isBootstrapping) {
    return {
      isBootstrapping: true,
      sessionStatus: "loading",
    };
  }

  if (!session) {
    return {
      isBootstrapping: false,
      sessionStatus: "unauthenticated",
    };
  }

  return {
    isBootstrapping: false,
    sessionStatus: "authenticated",
  };
}

export function resolveBootstrapHref(state: RouteAccessState): Href {
  if (state.sessionStatus !== "authenticated") {
    return routes.public.welcome;
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
      return <Redirect href={routes.public.welcome} />;
    }

    return <>{children}</>;
  }

  if (accessState.sessionStatus !== "authenticated") {
    return <Redirect href={routes.public.welcome} />;
  }

  return <>{children}</>;
}
