import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { createEvent, listEvents } from "@/features/events/api";
import type { CreateEventInput } from "@/features/events/types";

export function useEvents() {
  const { session } = useAuth();

  return useQuery({
    queryKey: ["events", session?.currentWorkspace?.id ?? "no-workspace"],
    queryFn: () => {
      if (!session) {
        throw new Error("No active session.");
      }

      return listEvents(session);
    },
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(session.currentWorkspace?.id),
    retry: 1,
  });
}

export function useCreateEvent() {
  const { session } = useAuth();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: async (input: CreateEventInput) => {
      if (!session) {
        throw new Error("No active session.");
      }

      return createEvent(session, input);
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({
        queryKey: ["events", session?.currentWorkspace?.id ?? "no-workspace"],
      });
    },
  });
}
