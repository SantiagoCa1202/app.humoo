import { Redirect } from "expo-router";

import { FullScreenLoader } from "@/components/patterns/FullScreenLoader";
import { resolveBootstrapHref, useRouteAccessState } from "@/navigation/route-access";
import { routes } from "@/navigation/routes";
import { useTranslation } from "react-i18next";

export default function IndexRoute() {
  const { t } = useTranslation("app");
  const accessState = useRouteAccessState();

  if (accessState.isBootstrapping) {
    return <FullScreenLoader label={t("routing.loading")} />;
  }

  return <Redirect href={accessState.sessionStatus === "unauthenticated" ? routes.public.welcome : resolveBootstrapHref(accessState)} />;
}
