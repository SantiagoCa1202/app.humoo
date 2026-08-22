import { useQuery } from "@tanstack/react-query";
import { useEffect, useState } from "react";

import { useAuth } from "@/auth/useAuth";
import { searchGlobal } from "@/features/search/api";
import { useWorkspace } from "@/features/workspace";

export const globalSearchKeys = {
  query: (workspaceId: string, query: string) =>
    ["workspace", workspaceId, "global-search", query] as const,
};

function useDebouncedValue(value: string, delayMs: number) {
  const [debouncedValue, setDebouncedValue] = useState(value);

  useEffect(() => {
    const timeout = setTimeout(() => setDebouncedValue(value), delayMs);

    return () => clearTimeout(timeout);
  }, [delayMs, value]);

  return debouncedValue;
}

export function useGlobalSearch(input: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const query = input.trim();
  const debouncedQuery = useDebouncedValue(query, 250);
  const enabled =
    session?.mode === "api" &&
    Boolean(session.token) &&
    Boolean(workspaceId) &&
    debouncedQuery.length >= 2;

  return useQuery({
    enabled,
    queryFn: ({ signal }) => {
      if (!session?.token || !workspaceId) {
        throw new Error("No active workspace session.");
      }

      return searchGlobal(session.token, workspaceId, debouncedQuery, signal);
    },
    queryKey: workspaceId
      ? globalSearchKeys.query(workspaceId, debouncedQuery)
      : ["workspace", "no-workspace", "global-search"],
    retry: 1,
  });
}
