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
  updateProfileWithApi,
} from "@/auth/api";
import {
  hydrateAuthCredential,
  subscribeAuthTransport,
} from "@/auth/auth-transport";
import { clearSession, writeSession } from "@/auth/sessionStorage";
import type {
  AppSession,
  ForgotPasswordResult,
  ResetPasswordInput,
  SignInInput,
  SignUpInput,
  UpdateProfileInput,
} from "@/auth/types";
import { isApiConfigured } from "@/config/runtime";
import { setPreferredLanguage } from "@/i18n";
import i18n from "@/i18n";

type AuthContextValue = {
  session: AppSession | null;
  isBootstrapping: boolean;
  refreshSession: (preferredWorkspaceId?: string | null) => Promise<void>;
  signIn: (input: SignInInput) => Promise<void>;
  signUp: (input: SignUpInput) => Promise<void>;
  requestPasswordReset: (email: string) => Promise<ForgotPasswordResult>;
  resetPassword: (input: ResetPasswordInput) => Promise<void>;
  acceptInvitation: (token: string) => Promise<void>;
  signOut: () => Promise<void>;
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

  useEffect(() => {
    return subscribeAuthTransport((event) => {
      if (event.type !== "session-expired") {
        return;
      }

      void clearSession();
      setSession(null);
      queryClient.clear();
    });
  }, [queryClient]);

  const value = useMemo<AuthContextValue>(
    () => ({
      session,
      isBootstrapping,
      refreshSession: async (preferredWorkspaceId = null) => {
        if (!session?.token) {
          return;
        }

        const nextSession = await refreshApiSession(
          session.token,
          preferredWorkspaceId ?? session.currentWorkspace?.id ?? null,
          session.createdAt
        );

        await persistSession(nextSession);
      },
      signIn: async (input) => {
        ensureApiConfigured();
        const nextSession = await loginWithApi(input);
        await persistSession(nextSession);
      },
      signUp: async (input) => {
        ensureApiConfigured();
        const nextSession = await registerWithApi(input);
        await persistSession(nextSession);
      },
      requestPasswordReset: async (email) => {
        ensureApiConfigured();
        return requestPasswordResetWithApi(email);
      },
      resetPassword: async (input) => {
        ensureApiConfigured();
        await resetPasswordWithApi(input);
      },
      acceptInvitation: async (token) => {
        if (!session?.token) {
          throw new Error(i18n.t("auth:noActiveSession"));
        }

        const nextSession = await acceptInvitationWithApi(
          token,
          session.token,
          session.createdAt
        );

        await persistSession(nextSession);
      },
      signOut: async () => {
        const activeToken = session?.token ?? null;

        try {
          if (activeToken && isApiConfigured) {
            await logoutFromApi(activeToken);
          }
        } finally {
          await clearSession();
          setSession(null);
          queryClient.clear();
        }
      },
      updateProfile: async (input) => {
        if (!session?.token) {
          throw new Error(i18n.t("auth:noActiveSession"));
        }

        await updateProfileWithApi(
          session.token,
          input,
          i18n.language === "es" ? "es" : "en",
        );

        const nextSession = await refreshApiSession(
          session.token,
          session.currentWorkspace?.id ?? null,
          session.createdAt,
        );

        await persistSession(nextSession);
      },
    }),
    [isBootstrapping, queryClient, session]
  );

  async function bootstrapSession() {
    try {
      const credential = await hydrateAuthCredential();

      if (!credential?.token || !isApiConfigured) {
        setSession(null);
        return;
      }

      try {
        const refreshedSession = await refreshApiSession(
          credential.token,
          null,
          credential.createdAt
        );

        await persistSession(refreshedSession);
      } catch {
        await clearSession();
        setSession(null);
      }
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

function ensureApiConfigured() {
  if (!isApiConfigured) {
    throw new Error(i18n.t("network.errors.apiNotConfigured"));
  }
}

function normalizeLanguage(language: string): "en" | "es" {
  return language === "es" ? "es" : "en";
}
