import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { useWorkspace } from "@/features/workspace";
import { createEvent, listEvents } from "@/features/events/api";
import type { CreateEventInput } from "@/features/events/types";

export function useEvents() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();

  return useQuery({
    queryKey: ["events", activeWorkspace?.id ?? "no-workspace"],
    queryFn: () => {
      if (!session?.token || !activeWorkspace?.id) {
        throw new Error("No active session.");
      }

      return listEvents(session.token, activeWorkspace.id);
    },
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(activeWorkspace?.id),
    retry: 1,
  });
}

export function useCreateEvent() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input: CreateEventInput) => {
      if (!session?.token || !activeWorkspace?.id) {
        throw new Error("No active session.");
      }

      return createEvent(session.token, activeWorkspace.id, input);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["events", activeWorkspace?.id ?? "no-workspace"],
      });
    },
  });
}
