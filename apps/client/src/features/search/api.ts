import { apiRequest } from "@/api/client";

import type { GlobalSearchResponse } from "@/features/search/types";

export async function searchGlobal(
  authToken: string,
  workspaceId: string,
  query: string,
  signal?: AbortSignal
): Promise<GlobalSearchResponse> {
  const response = await apiRequest<{ data: GlobalSearchResponse }>("/search", {
    authToken,
    query: { limit: 50, q: query },
    signal,
    workspaceId,
  });

  return response.data;
}
