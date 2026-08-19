import { useMemo } from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { EntityPickerOption } from "@/components/primitives/entity-picker";
import { useWorkspace } from "@/features/workspace";
import {
  createClient,
  createContact,
  createVenue,
  deleteClient,
  deleteContact,
  deleteVenue,
  getClient,
  getContact,
  getVenue,
  listClients,
  listContacts,
  listVenues,
  updateClient,
  updateContact,
  updateVenue,
} from "@/features/directory/api";
import {
  clientToPickerOption,
  contactToPickerOption,
  venueToPickerOption,
} from "@/features/directory/adapters";
import type {
  ClientFilters,
  ClientMutationInput,
  ContactFilters,
  ContactMutationInput,
  VenueFilters,
  VenueMutationInput,
} from "@/features/directory/types";

function normalizeSearch(value?: string) {
  const trimmed = value?.trim() ?? "";
  return trimmed.length > 0 ? trimmed : "";
}

function getApiContext(
  sessionToken: string | null | undefined,
  workspaceId: string | null | undefined
) {
  if (!sessionToken || !workspaceId) {
    throw new Error("No active workspace session.");
  }

  return {
    sessionToken,
    workspaceId,
  };
}

export const directoryKeys = {
  workspace(workspaceId: string) {
    return ["workspace", workspaceId, "directory"] as const;
  },
  clients(workspaceId: string) {
    return [...this.workspace(workspaceId), "clients"] as const;
  },
  clientList(workspaceId: string, filters: ClientFilters = {}) {
    return [
      ...this.clients(workspaceId),
      "list",
      {
        search: normalizeSearch(filters.search),
        status: filters.status ?? "",
      },
    ] as const;
  },
  clientDetail(workspaceId: string, clientId: string) {
    return [...this.clients(workspaceId), clientId] as const;
  },
  contacts(workspaceId: string) {
    return [...this.workspace(workspaceId), "contacts"] as const;
  },
  contactList(workspaceId: string, filters: ContactFilters = {}) {
    return [
      ...this.contacts(workspaceId),
      "list",
      {
        clientId: filters.clientId ?? "",
        search: normalizeSearch(filters.search),
      },
    ] as const;
  },
  contactDetail(workspaceId: string, contactId: string) {
    return [...this.contacts(workspaceId), contactId] as const;
  },
  venues(workspaceId: string) {
    return [...this.workspace(workspaceId), "venues"] as const;
  },
  venueList(workspaceId: string, filters: VenueFilters = {}) {
    return [
      ...this.venues(workspaceId),
      "list",
      {
        search: normalizeSearch(filters.search),
        status: filters.status ?? "",
      },
    ] as const;
  },
  venueDetail(workspaceId: string, venueId: string) {
    return [...this.venues(workspaceId), venueId] as const;
  },
};

export function useClients(filters: ClientFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      search: normalizeSearch(filters.search),
      status: filters.status ?? "",
    }),
    [filters.search, filters.status]
  );

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return listClients(context.sessionToken, context.workspaceId, normalizedFilters);
    },
    queryKey: workspaceId
      ? directoryKeys.clientList(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "directory", "clients"],
    retry: 1,
  });
}

export function useClient(clientId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(clientId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      if (!clientId) {
        throw new Error("Missing client id.");
      }

      return getClient(context.sessionToken, context.workspaceId, clientId);
    },
    queryKey:
      workspaceId && clientId
        ? directoryKeys.clientDetail(workspaceId, clientId)
        : ["workspace", "no-workspace", "directory", "clients", "detail"],
    retry: 1,
  });
}

export function useCreateClient() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: ClientMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return createClient(context.sessionToken, context.workspaceId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await queryClient.invalidateQueries({
        queryKey: directoryKeys.clients(workspaceId),
      });
    },
  });
}

export function useUpdateClient(clientId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: ClientMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateClient(context.sessionToken, context.workspaceId, clientId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: directoryKeys.clients(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.clientDetail(workspaceId, clientId),
        }),
      ]);
    },
  });
}

export function useDeleteClient(clientId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      await deleteClient(context.sessionToken, context.workspaceId, clientId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await queryClient.invalidateQueries({
        queryKey: directoryKeys.clients(workspaceId),
      });
    },
  });
}

export function useContacts(filters: ContactFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      clientId: filters.clientId ?? "",
      search: normalizeSearch(filters.search),
    }),
    [filters.clientId, filters.search]
  );

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return listContacts(context.sessionToken, context.workspaceId, normalizedFilters);
    },
    queryKey: workspaceId
      ? directoryKeys.contactList(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "directory", "contacts"],
    retry: 1,
  });
}

export function useContact(contactId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(contactId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      if (!contactId) {
        throw new Error("Missing contact id.");
      }

      return getContact(context.sessionToken, context.workspaceId, contactId);
    },
    queryKey:
      workspaceId && contactId
        ? directoryKeys.contactDetail(workspaceId, contactId)
        : ["workspace", "no-workspace", "directory", "contacts", "detail"],
    retry: 1,
  });
}

export function useCreateContact() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: ContactMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return createContact(context.sessionToken, context.workspaceId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: directoryKeys.contacts(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.clients(workspaceId),
        }),
      ]);
    },
  });
}

export function useUpdateContact(contactId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: ContactMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateContact(context.sessionToken, context.workspaceId, contactId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: directoryKeys.contacts(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.clients(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.contactDetail(workspaceId, contactId),
        }),
      ]);
    },
  });
}

export function useDeleteContact(contactId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      await deleteContact(context.sessionToken, context.workspaceId, contactId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: directoryKeys.contacts(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.clients(workspaceId),
        }),
      ]);
    },
  });
}

export function useVenues(filters: VenueFilters = {}) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;
  const normalizedFilters = useMemo(
    () => ({
      search: normalizeSearch(filters.search),
      status: filters.status ?? "",
    }),
    [filters.search, filters.status]
  );

  return useQuery({
    enabled: Boolean(session?.token) && session?.mode === "api" && Boolean(workspaceId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      return listVenues(context.sessionToken, context.workspaceId, normalizedFilters);
    },
    queryKey: workspaceId
      ? directoryKeys.venueList(workspaceId, normalizedFilters)
      : ["workspace", "no-workspace", "directory", "venues"],
    retry: 1,
  });
}

export function useVenue(venueId?: string | null) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled:
      Boolean(session?.token) &&
      session?.mode === "api" &&
      Boolean(workspaceId) &&
      Boolean(venueId),
    queryFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      if (!venueId) {
        throw new Error("Missing venue id.");
      }

      return getVenue(context.sessionToken, context.workspaceId, venueId);
    },
    queryKey:
      workspaceId && venueId
        ? directoryKeys.venueDetail(workspaceId, venueId)
        : ["workspace", "no-workspace", "directory", "venues", "detail"],
    retry: 1,
  });
}

export function useCreateVenue() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: VenueMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return createVenue(context.sessionToken, context.workspaceId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await queryClient.invalidateQueries({
        queryKey: directoryKeys.venues(workspaceId),
      });
    },
  });
}

export function useUpdateVenue(venueId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async (input: VenueMutationInput) => {
      const context = getApiContext(session?.token, workspaceId);
      return updateVenue(context.sessionToken, context.workspaceId, venueId, input);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await Promise.all([
        queryClient.invalidateQueries({
          queryKey: directoryKeys.venues(workspaceId),
        }),
        queryClient.invalidateQueries({
          queryKey: directoryKeys.venueDetail(workspaceId, venueId),
        }),
      ]);
    },
  });
}

export function useDeleteVenue(venueId: string) {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const queryClient = useQueryClient();
  const workspaceId = activeWorkspace?.id ?? null;

  return useMutation({
    mutationFn: async () => {
      const context = getApiContext(session?.token, workspaceId);
      await deleteVenue(context.sessionToken, context.workspaceId, venueId);
    },
    onSuccess: async () => {
      if (!workspaceId) {
        return;
      }

      await queryClient.invalidateQueries({
        queryKey: directoryKeys.venues(workspaceId),
      });
    },
  });
}

export function useClientPickerOptions() {
  const clientsQuery = useClients();

  return useMemo<EntityPickerOption<string>[]>(
    () => (clientsQuery.data?.data ?? []).map(clientToPickerOption),
    [clientsQuery.data]
  );
}

export function useContactPickerOptions(clientId?: string | null) {
  const contactsQuery = useContacts({
    clientId: clientId ?? undefined,
  });

  return useMemo<EntityPickerOption<string>[]>(
    () => (contactsQuery.data?.data ?? []).map(contactToPickerOption),
    [contactsQuery.data]
  );
}

export function useVenuePickerOptions() {
  const venuesQuery = useVenues();

  return useMemo<EntityPickerOption<string>[]>(
    () => (venuesQuery.data?.data ?? []).map(venueToPickerOption),
    [venuesQuery.data]
  );
}
