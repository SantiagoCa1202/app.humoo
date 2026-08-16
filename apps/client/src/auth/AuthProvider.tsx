import { createContext, useEffect, useMemo, useState } from "react";

import { useQueryClient } from "@tanstack/react-query";

import {
  acceptInvitationWithApi,
  loginWithApi,
  logoutFromApi,
  refreshApiSession,
  registerWithApi,
  requestPasswordResetWithApi,
  resetPasswordWithApi,
} from "@/auth/api";
import { clearSession, readSession, writeSession } from "@/auth/sessionStorage";
import type {
  AppSession,
  AuthUser,
  CreateOrganizationInput,
  ForgotPasswordResult,
  ResetPasswordInput,
  SignInInput,
  SignUpInput,
  UpdateProfileInput,
  WorkspaceMembership,
  WorkspaceSummary,
} from "@/auth/types";
import { isApiConfigured, runtimeConfig } from "@/config/runtime";
import { setPreferredLanguage } from "@/i18n";

type AuthContextValue = {
  session: AppSession | null;
  isBootstrapping: boolean;
  refreshSession: () => Promise<void>;
  signIn: (input: SignInInput) => Promise<void>;
  signUp: (input: SignUpInput) => Promise<void>;
  requestPasswordReset: (email: string) => Promise<ForgotPasswordResult>;
  resetPassword: (input: ResetPasswordInput) => Promise<void>;
  acceptInvitation: (token: string) => Promise<void>;
  signOut: () => Promise<void>;
  createOrganization: (input: CreateOrganizationInput) => Promise<void>;
  updateProfile: (input: UpdateProfileInput) => Promise<void>;
};

export const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();
  const [session, setSession] = useState<AppSession | null>(null);
  const [isBootstrapping, setIsBootstrapping] = useState(true);

  useEffect(() => {
    void bootstrapSession();
  }, []);

  const value = useMemo<AuthContextValue>(
    () => ({
      session,
      isBootstrapping,
      refreshSession: async () => {
        if (!session?.token || session.mode !== "api") {
          return;
        }

        const nextSession = await refreshApiSession(
          session.token,
          session.currentWorkspace?.id ?? null,
          session.createdAt
        );

        await persistSession(nextSession);
      },
      signIn: async (input) => {
        if (isApiConfigured) {
          const nextSession = await loginWithApi(input);
          await persistSession(nextSession);
          return;
        }

        ensureLocalModeEnabled();

        const normalizedEmail = input.email.trim().toLowerCase();
        const existingSession = await readSession();
        const nextSession = buildLocalSession({
          createdAt: existingSession?.createdAt,
          email: normalizedEmail,
          firstName:
            existingSession?.user.firstName ??
            displayNameFromEmail(normalizedEmail),
          lastName: existingSession?.user.lastName ?? "Chef",
          timezone: existingSession?.user.timezone ?? "America/New_York",
        });

        await persistSession(nextSession);
      },
      signUp: async (input) => {
        if (isApiConfigured) {
          const nextSession = await registerWithApi(input);
          await persistSession(nextSession);
          return;
        }

        ensureLocalModeEnabled();

        const nextSession = buildLocalSession({
          email: input.email.trim().toLowerCase(),
          firstName: input.firstName.trim(),
          lastName: input.lastName.trim(),
          timezone: "America/New_York",
        });

        await persistSession(nextSession);
      },
      requestPasswordReset: async (email) => {
        if (isApiConfigured) {
          return requestPasswordResetWithApi(email);
        }

        ensureLocalModeEnabled();

        return {
          message: "Recovery request captured locally.",
        };
      },
      resetPassword: async (input) => {
        if (isApiConfigured) {
          await resetPasswordWithApi(input);
          return;
        }

        ensureLocalModeEnabled();

        if (!input.token.trim()) {
          throw new Error("A reset token is required.");
        }
      },
      acceptInvitation: async (token) => {
        if (!session) {
          throw new Error("No active session.");
        }

        if (session.mode !== "api" || !session.token) {
          throw new Error(
            "Invitation acceptance requires a real API session."
          );
        }

        const nextSession = await acceptInvitationWithApi(
          token,
          session.token,
          session.createdAt
        );

        await persistSession(nextSession);
      },
      signOut: async () => {
        const activeSession = session;

        try {
          if (
            activeSession?.mode === "api" &&
            activeSession.token &&
            isApiConfigured
          ) {
            await logoutFromApi(activeSession.token);
          }
        } finally {
          await clearSession();
          setSession(null);
          queryClient.clear();
        }
      },
      createOrganization: async (input) => {
        if (!session) {
          throw new Error("No active session.");
        }

        if (session.mode === "api") {
          throw new Error(
            "La creacion de workspaces todavia no existe en la API. Asigna la membresia desde backend y luego refresca esta pantalla."
          );
        }

        const workspace = buildLocalWorkspace(session.user, input);
        const membership = buildLocalMembership(session.user.id, workspace);
        const nextSession: AppSession = {
          ...session,
          memberships: [membership],
          currentWorkspace: workspace,
          currentMembership: membership,
        };

        await persistSession(nextSession);
      },
      updateProfile: async (input) => {
        if (!session) {
          throw new Error("No active session.");
        }

        if (session.mode === "api") {
          throw new Error(
            "La actualizacion de perfil todavia no tiene endpoint en la API."
          );
        }

        const trimmedFirstName = input.firstName.trim();
        const trimmedLastName = input.lastName.trim();
        const nextUser: AuthUser = {
          ...session.user,
          name: [trimmedFirstName, trimmedLastName].filter(Boolean).join(" "),
          firstName: trimmedFirstName,
          lastName: trimmedLastName,
          timezone: input.timezone.trim(),
        };

        const nextSession: AppSession = {
          ...session,
          user: nextUser,
        };

        await persistSession(nextSession);
      },
    }),
    [isBootstrapping, queryClient, session]
  );

  async function bootstrapSession() {
    try {
      const storedSession = await readSession();

      if (!storedSession) {
        setSession(null);
        return;
      }

      const normalizedSession = migrateStoredSession(storedSession);

      if (
        normalizedSession.mode === "api" &&
        normalizedSession.token &&
        isApiConfigured
      ) {
        try {
          const refreshedSession = await refreshApiSession(
            normalizedSession.token,
            normalizedSession.currentWorkspace?.id ?? null,
            normalizedSession.createdAt
          );

          await persistSession(refreshedSession);
        } catch {
          await clearSession();
          setSession(null);
        }

        return;
      }

      await persistSession(normalizedSession);
    } finally {
      setIsBootstrapping(false);
    }
  }

  async function persistSession(nextSession: AppSession) {
    await writeSession(nextSession);
    setSession(nextSession);
    await setPreferredLanguage(normalizeLanguage(nextSession.user.preferredLocale));
  }

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

function ensureLocalModeEnabled() {
  if (!runtimeConfig.enableLocalAuthFallback) {
    throw new Error("El modo local esta deshabilitado en esta configuracion.");
  }
}

function buildLocalSession(input: {
  email: string;
  firstName: string;
  lastName: string;
  timezone: string;
  createdAt?: string;
}): AppSession {
  const firstName = input.firstName.trim() || "Humoo";
  const lastName = input.lastName.trim();

  return {
    mode: "local-fallback",
    token: null,
    user: {
      id: `usr_${Date.now()}`,
      name: [firstName, lastName].filter(Boolean).join(" "),
      firstName,
      lastName,
      email: input.email,
      preferredLocale: "en",
      timezone: input.timezone,
    },
    memberships: [],
    currentWorkspace: null,
    currentMembership: null,
    permissions: [],
    createdAt: input.createdAt ?? new Date().toISOString(),
  };
}

function buildLocalWorkspace(
  user: AuthUser,
  input: CreateOrganizationInput
): WorkspaceSummary {
  return {
    id: `wrk_${Date.now()}`,
    name: input.name.trim(),
    slug: input.name.trim().toLowerCase().replace(/\s+/g, "-"),
    defaultLocale: user.preferredLocale,
    timezone: input.timezone.trim(),
    currency: input.currencyCode.trim().toUpperCase(),
    status: "active",
  };
}

function buildLocalMembership(
  userId: string,
  workspace: WorkspaceSummary
): WorkspaceMembership {
  return {
    id: `mbr_${Date.now()}`,
    workspaceId: workspace.id,
    userId,
    roleId: null,
    roleKey: "owner",
    roleName: "Owner",
    status: "active",
    joinedAt: new Date().toISOString(),
    workspace,
  };
}

function displayNameFromEmail(email: string): string {
  const local = email.split("@")[0] ?? "humoo";
  const cleaned = local.replace(/[^a-zA-Z0-9]/g, " ").trim();

  if (!cleaned) {
    return "Humoo";
  }

  return cleaned
    .split(/\s+/)
    .map((segment) => segment.charAt(0).toUpperCase() + segment.slice(1))
    .join(" ");
}

function migrateStoredSession(session: AppSession): AppSession {
  if (
    session.currentWorkspace !== undefined &&
    session.currentMembership !== undefined &&
    Array.isArray(session.memberships) &&
    session.token !== undefined
  ) {
    return session;
  }

  const rawSession = session as unknown as Record<string, unknown>;
  const rawUser = (rawSession.user as Record<string, unknown> | undefined) ?? {};
  const activeOrganizationId =
    typeof rawSession.activeOrganizationId === "string"
      ? rawSession.activeOrganizationId
      : null;
  const organizations = Array.isArray(rawSession.organizations)
    ? rawSession.organizations
    : [];
  const legacyWorkspaceRecord =
    organizations.find((item) => {
      if (!item || typeof item !== "object") {
        return false;
      }

      return (
        typeof (item as Record<string, unknown>).id === "string" &&
        (item as Record<string, unknown>).id === activeOrganizationId
      );
    }) ?? null;

  const firstName = stringOrFallback(rawUser.firstName, "Humoo");
  const lastName = stringOrFallback(rawUser.lastName, "");
  const workspace = legacyWorkspaceRecord
    ? mapLegacyWorkspace(legacyWorkspaceRecord as Record<string, unknown>, rawUser)
    : null;
  const legacyMembership = workspace
    ? buildLocalMembership(stringOrFallback(rawUser.id, "legacy"), workspace)
    : null;

  return {
    mode: rawSession.mode === "api" ? "api" : "local-fallback",
    token: typeof rawSession.token === "string" ? rawSession.token : null,
    user: {
      id: stringOrFallback(rawUser.id, `usr_${Date.now()}`),
      name:
        stringOrFallback(rawUser.name, "") ||
        [firstName, lastName].filter(Boolean).join(" "),
      firstName,
      lastName,
      email: stringOrFallback(rawUser.email, ""),
      preferredLocale: stringOrFallback(rawUser.preferredLocale, "en"),
      timezone: stringOrFallback(rawUser.timezone, "UTC"),
    },
    memberships: legacyMembership ? [legacyMembership] : [],
    currentWorkspace: workspace,
    currentMembership: legacyMembership,
    permissions: Array.isArray(rawSession.permissions)
      ? rawSession.permissions.filter(
          (permission): permission is string => typeof permission === "string"
        )
      : [],
    createdAt: stringOrFallback(
      rawSession.createdAt,
      new Date().toISOString()
    ),
  };
}

function mapLegacyWorkspace(
  workspace: Record<string, unknown>,
  user: Record<string, unknown>
): WorkspaceSummary {
  return {
    id: stringOrFallback(workspace.id, `wrk_${Date.now()}`),
    name: stringOrFallback(workspace.name, "Humoo Workspace"),
    slug: stringOrFallback(workspace.slug, "humoo-workspace"),
    defaultLocale: stringOrFallback(user.preferredLocale, "en"),
    timezone: stringOrFallback(workspace.timezone, "UTC"),
    currency: stringOrFallback(workspace.currencyCode, "USD"),
    status: "active",
  };
}

function stringOrFallback(value: unknown, fallback: string): string {
  return typeof value === "string" && value.trim() ? value : fallback;
}

function normalizeLanguage(language: string): "en" | "es" {
  return language === "es" ? "es" : "en";
}
