import { useInfiniteQuery, useQuery } from "@tanstack/react-query";

import { useAuth } from "@/auth/useAuth";
import { getBilling, getInvoices, getPlans } from "@/features/billing/api";
import type { InvoicesPage } from "@/features/billing/types";
import { useWorkspace } from "@/features/workspace";

export const billingKeys = {
  invoices: (workspaceId: string) => ["workspace", workspaceId, "billing", "invoices"] as const,
  plans: (workspaceId: string) => ["workspace", workspaceId, "billing", "plans"] as const,
  snapshot: (workspaceId: string) => ["workspace", workspaceId, "billing"] as const,
};

function context(token: string | null | undefined, workspaceId: string | null) {
  if (!token || !workspaceId) {
    throw new Error("Billing API context is unavailable.");
  }

  return { token, workspaceId };
}

export function useBilling() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    queryFn: () => {
      const current = context(session?.token, workspaceId);
      return getBilling(current.token, current.workspaceId);
    },
    queryKey: workspaceId ? billingKeys.snapshot(workspaceId) : ["billing", "no-workspace"],
  });
}

export function useBillingPlans() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useQuery({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    queryFn: () => {
      const current = context(session?.token, workspaceId);
      return getPlans(current.token, current.workspaceId);
    },
    queryKey: workspaceId ? billingKeys.plans(workspaceId) : ["billing", "plans", "no-workspace"],
  });
}

export function useBillingInvoices() {
  const { session } = useAuth();
  const { activeWorkspace } = useWorkspace();
  const workspaceId = activeWorkspace?.id ?? null;

  return useInfiniteQuery<InvoicesPage, Error>({
    enabled: session?.mode === "api" && Boolean(session.token) && Boolean(workspaceId),
    getNextPageParam: (page) => page.nextCursor ?? undefined,
    initialPageParam: null as string | null,
    queryFn: ({ pageParam }) => {
      const current = context(session?.token, workspaceId);
      return getInvoices(current.token, current.workspaceId, pageParam as string | null);
    },
    queryKey: workspaceId ? billingKeys.invoices(workspaceId) : ["billing", "invoices", "no-workspace"],
  });
}
