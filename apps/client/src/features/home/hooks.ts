import { useQuery } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { getCommandCenter } from "@/features/home/api";
import { commandCenterKeys } from "@/features/home/queryKeys";
import { useWorkspace } from "@/features/workspace";

function getApiContext(sessionToken: string | null | undefined, workspaceId: string | null | undefined) {
  if (!sessionToken || !workspaceId) {
    throw new Error("No active workspace session.");
  }

  return { sessionToken, workspaceId };
}

export function useCommandCenter() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return getCommandCenter(context.sessionToken, context.workspaceId);
    },
    queryKey:
      workspaceId
        ? commandCenterKeys.workspace(workspaceId)
        : ["workspace", "no-workspace", "command-center"],
  });
}
