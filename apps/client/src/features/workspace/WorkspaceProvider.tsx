import AsyncStorage from "@react-native-async-storage/async-storage";
import { createContext, useEffect, useMemo, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import type { WorkspaceMembership } from "@/auth/types";
import {
  createWorkspace as createWorkspaceRequest,
  listWorkspaces,
} from "@/features/workspace/api";
import type {
  CreateWorkspaceInput,
  WorkspaceAccess,
  WorkspaceStatus,
} from "@/features/workspace/types";

const STORAGE_KEY = "humoo:last-workspace-id";

type WorkspaceContextValue = {
  status: WorkspaceStatus;
  workspaces: WorkspaceAccess[];
  activeWorkspace: WorkspaceAccess["workspace"] | null;
  activeMembership: WorkspaceMembership | null;
  permissions: string[];
  errorMessage: string | null;
  setActiveWorkspace: (workspaceId: string) => Promise<void>;
  refreshWorkspaces: () => Promise<void>;
  createWorkspace: (input: CreateWorkspaceInput) => Promise<void>;
  acceptInvitation: (token: string) => Promise<void>;
  hasPermission: (permissionKey: string) => boolean;
};

export const WorkspaceContext = createContext<WorkspaceContextValue | null>(null);

export function WorkspaceProvider({ children }: { children: React.ReactNode }) {
  const queryClient = useQueryClient();
  const { acceptInvitation: acceptInvitationWithSession, refreshSession, session } =
    useAuth();
  const [status, setStatus] = useState<WorkspaceStatus>("loading");
  const [workspaces, setWorkspaces] = useState<WorkspaceAccess[]>([]);
  const [activeWorkspaceId, setActiveWorkspaceId] = useState<string | null>(null);
  const [errorMessage, setErrorMessage] = useState<string | null>(null);

  useEffect(() => {
    if (!session?.token) {
      setStatus("loading");
      setWorkspaces([]);
      setActiveWorkspaceId(null);
      setErrorMessage(null);
      void AsyncStorage.removeItem(STORAGE_KEY);
      return;
    }

    let cancelled = false;

    void hydrateWorkspaceState(() => cancelled);

    return () => {
      cancelled = true;
    };
  }, [session?.createdAt, session?.currentWorkspace?.id, session?.token]);

  const activeAccess =
    workspaces.find((workspace) => workspace.workspace.id === activeWorkspaceId) ?? null;
  const activeMembership =
    session?.memberships.find((membership) => membership.workspaceId === activeWorkspaceId) ??
    null;
  const permissions = activeAccess?.permissions ?? [];

  const value = useMemo<WorkspaceContextValue>(
    () => ({
      status,
      workspaces,
      activeWorkspace: activeAccess?.workspace ?? null,
      activeMembership,
      permissions,
      errorMessage,
      setActiveWorkspace: async (workspaceId) => {
        const nextWorkspace = workspaces.find(
          (workspace) => workspace.workspace.id === workspaceId
        );

        if (!session?.token || !nextWorkspace) {
          throw new Error("The selected workspace is no longer available.");
        }

        try {
          setStatus("loading");
          setErrorMessage(null);
          await AsyncStorage.setItem(STORAGE_KEY, workspaceId);
          await refreshSession(workspaceId);
          queryClient.clear();
        } catch (error) {
          setErrorMessage(
            error instanceof Error ? error.message : "Unable to switch workspace."
          );
          setStatus(workspaces.length > 0 ? "ready" : "workspace_required");
          throw error;
        }
      },
      refreshWorkspaces: async () => {
        if (!session?.token) {
          return;
        }

        try {
          setStatus("loading");
          setErrorMessage(null);
          await refreshSession(activeWorkspaceId);
        } catch (error) {
          setErrorMessage(
            error instanceof Error ? error.message : "Unable to refresh workspaces."
          );
          setStatus(workspaces.length > 0 ? "ready" : "workspace_required");
          throw error;
        }
      },
      createWorkspace: async (input) => {
        if (!session?.token) {
          throw new Error("No active session.");
        }

        try {
          setStatus("loading");
          setErrorMessage(null);
          const access = await createWorkspaceRequest(session.token, input);
          await AsyncStorage.setItem(STORAGE_KEY, access.workspace.id);
          await refreshSession(access.workspace.id);
          queryClient.clear();
        } catch (error) {
          setErrorMessage(
            error instanceof Error ? error.message : "Unable to create workspace."
          );
          setStatus(workspaces.length > 0 ? "ready" : "workspace_required");
          throw error;
        }
      },
      acceptInvitation: async (token) => {
        try {
          setStatus("loading");
          setErrorMessage(null);
          await acceptInvitationWithSession(token);
          queryClient.clear();
        } catch (error) {
          setErrorMessage(
            error instanceof Error ? error.message : "Unable to accept invitation."
          );
          setStatus(workspaces.length > 0 ? "ready" : "workspace_required");
          throw error;
        }
      },
      hasPermission: (permissionKey) => permissions.includes(permissionKey),
    }),
    [
      acceptInvitationWithSession,
      activeAccess?.workspace,
      activeMembership,
      activeWorkspaceId,
      errorMessage,
      permissions,
      queryClient,
      refreshSession,
      session?.token,
      status,
      workspaces,
    ]
  );

  async function hydrateWorkspaceState(isCancelled: () => boolean) {
    if (!session?.token) {
      return;
    }

    try {
      setStatus("loading");
      setErrorMessage(null);

      const nextWorkspaces = await listWorkspaces(session.token);

      if (isCancelled()) {
        return;
      }

      setWorkspaces(nextWorkspaces);

      if (nextWorkspaces.length === 0) {
        setActiveWorkspaceId(null);
        setStatus("workspace_required");
        await AsyncStorage.removeItem(STORAGE_KEY);
        return;
      }

      const storedWorkspaceId = await AsyncStorage.getItem(STORAGE_KEY);

      if (isCancelled()) {
        return;
      }

      const nextWorkspaceId = resolveWorkspaceId(
        nextWorkspaces,
        storedWorkspaceId,
        session.currentWorkspace?.id ?? null
      );

      if (!nextWorkspaceId) {
        setActiveWorkspaceId(null);
        setStatus("workspace_required");
        return;
      }

      setActiveWorkspaceId(nextWorkspaceId);
      await AsyncStorage.setItem(STORAGE_KEY, nextWorkspaceId);

      if (session.currentWorkspace?.id && session.currentWorkspace.id !== nextWorkspaceId) {
        await refreshSession(nextWorkspaceId);

        if (isCancelled()) {
          return;
        }
      }

      setStatus("ready");
    } catch (error) {
      if (isCancelled()) {
        return;
      }

      setErrorMessage(error instanceof Error ? error.message : "Unable to load workspaces.");
      setStatus("error");
    }
  }

  return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}

function resolveWorkspaceId(
  workspaces: WorkspaceAccess[],
  storedWorkspaceId: string | null,
  sessionWorkspaceId: string | null
): string | null {
  const validIds = new Set(workspaces.map((workspace) => workspace.workspace.id));

  if (storedWorkspaceId && validIds.has(storedWorkspaceId)) {
    return storedWorkspaceId;
  }

  if (sessionWorkspaceId && validIds.has(sessionWorkspaceId)) {
    return sessionWorkspaceId;
  }

  if (workspaces.length === 1) {
    return workspaces[0]?.workspace.id ?? null;
  }

  return workspaces[0]?.workspace.id ?? null;
}
