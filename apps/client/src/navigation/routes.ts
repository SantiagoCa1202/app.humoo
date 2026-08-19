import type { Href } from "expo-router";

export const routes = {
  app: {
    calendar: "/(app)/calendar" as Href,
    chat: "/(app)/chat" as Href,
    clientCreate: "/(app)/clients/create" as Href,
    clientDetail: "/(app)/clients/[id]" as Href,
    clientEdit: "/(app)/clients/[id]/edit" as Href,
    clients: "/(app)/clients" as Href,
    contactCreate: "/(app)/contacts/create" as Href,
    contactEdit: "/(app)/contacts/[id]/edit" as Href,
    contacts: "/(app)/contacts" as Href,
    index: "/(app)" as Href,
    operations: "/(app)/operations" as Href,
    profile: "/(app)/profile" as Href,
    settings: "/(app)/settings" as Href,
    venueCreate: "/(app)/venues/create" as Href,
    venueDetail: "/(app)/venues/[id]" as Href,
    venueEdit: "/(app)/venues/[id]/edit" as Href,
    venues: "/(app)/venues" as Href,
  },
  onboarding: {
    organization: "/(onboarding)/organization" as Href,
  },
  public: {
    forgotPassword: "/(public)/forgot-password" as Href,
    login: "/(public)/login" as Href,
    register: "/(public)/register" as Href,
    resetPassword: "/(public)/reset-password" as Href,
    welcome: "/(public)/welcome" as Href,
  },
  root: "/" as Href,
} as const;

export type AppRouteHref =
  | (typeof routes.app)[keyof typeof routes.app]
  | (typeof routes.public)[keyof typeof routes.public]
  | (typeof routes.onboarding)[keyof typeof routes.onboarding]
  | typeof routes.root;
